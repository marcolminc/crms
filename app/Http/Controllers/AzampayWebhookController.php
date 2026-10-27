<?php

namespace App\Http\Controllers;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentGatewayEvent;
use App\Models\PaymentIntent;
use App\Services\AzampayClient;
use App\Services\OnlinePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AzamPay's server-to-server notification for a mobile money push or a hosted
 * checkout. Authenticity comes from the registered callback URL (and the optional
 * shared secret header), never from the session. Nothing in the payload is trusted
 * for money: the settlement path re-asks the gateway what actually happened.
 */
final class AzampayWebhookController extends Controller
{
    public function __invoke(Request $request, AzampayClient $gateway, OnlinePaymentService $checkout): JsonResponse
    {
        if (! $gateway->webhookAuthenticates($request->header('x-webhook-secret'))) {
            abort(403);
        }

        // AzamPay names our reference "utilityref" and its own "reference"; the
        // published SDK samples disagree on the status key's casing, so read both.
        $txRef = $request->input('utilityref');
        $reference = $request->input('reference');
        $status = strtolower((string) ($request->input('transactionStatus') ?? $request->input('transactionstatus') ?? ''));

        // Journal the callback verbatim before acting on it (design 5.3). It is
        // written only after authentication, so the row is trustworthy evidence.
        $event = PaymentGatewayEvent::create([
            'gateway_ref' => is_string($reference) && $reference !== '' ? substr($reference, 0, 64) : null,
            'event_type' => $status !== '' ? substr($status, 0, 40) : 'callback',
            'raw_payload' => $request->all(),
            'signature_valid' => true,
            'received_at' => now(),
        ]);

        if (! is_string($txRef) || $txRef === '') {
            Log::warning('AzamPay webhook carried no utility reference.', ['payload' => $request->all()]);

            return response()->json(['status' => 'ignored']);
        }

        $intent = PaymentIntent::findByTxRef($txRef);

        if ($intent === null) {
            Log::warning('AzamPay webhook arrived for an unknown checkout.', ['utilityref' => $txRef]);
            $event->update(['processed' => true]);

            return response()->json(['status' => 'ignored']);
        }

        $event->update(['payment_intent_id' => $intent->id]);

        if ($intent->status === PaymentIntentStatus::Recorded) {
            $event->update(['processed' => true]);

            return response()->json(['status' => GatewayCaptureOutcome::AlreadyRecorded->value]);
        }

        // Only a terminal failure from the gateway closes the attempt; anything else
        // (including an in-flight status) is settled by verifying with AzamPay.
        if (in_array($status, ['failed', 'failure', 'cancelled', 'canceled', 'declined', 'rejected', 'invalid', 'expired'], true)) {
            $checkout->abandon($intent);
            $event->update(['processed' => true]);

            return response()->json(['status' => GatewayCaptureOutcome::Declined->value]);
        }

        try {
            $outcome = $checkout->settleFromWebhook($txRef, is_string($reference) ? $reference : '');
        } catch (Throwable $exception) {
            // A bug on our side must not make AzamPay redeliver forever.
            Log::error('AzamPay webhook could not be processed.', ['error' => $exception->getMessage()]);
            $event->update(['processed' => true]);

            return response()->json(['status' => 'ignored']);
        }

        if ($outcome === GatewayCaptureOutcome::GatewayUnavailable) {
            // Left unprocessed on purpose: the redelivery (or the reconciler) gets another turn.
            return response()->json(['status' => 'retry'], 503);
        }

        $event->update(['processed' => true]);

        return response()->json(['status' => $outcome->value]);
    }
}

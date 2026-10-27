<?php

namespace App\Services;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\RentalStatus;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Rental;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opens checkouts for a rental balance — a mobile money push or a hosted card page —
 * and turns a gateway confirmation into a ledger row exactly once, whichever callback
 * arrives first.
 */
final class OnlinePaymentService
{
    private const AMOUNT_TOLERANCE = 0.005;

    public function __construct(
        private readonly AzampayClient $gateway,
        private readonly PaymentRecordingService $payments,
        private readonly NotificationService $notifications,
    ) {}

    public function enabled(): bool
    {
        return $this->gateway->isEnabled();
    }

    /**
     * Opens a checkout for the balance the customer owes right now, reusing a live
     * checkout instead of asking the gateway for a second charge. Mobile money goes
     * out as a push against the customer's number; card opens a hosted page.
     */
    public function startCheckout(Rental $rental, PaymentMethod $method): ?PaymentIntent
    {
        if (! $this->enabled() || $rental->status === RentalStatus::Cancelled) {
            return null;
        }

        $amount = $rental->balanceDue();
        if ($amount <= 0) {
            return null;
        }

        $push = in_array($method, PaymentMethod::mobileMoney(), true);
        $account = $push ? $this->normalizeMobileAccount($rental->customer?->user?->phone) : null;

        if ($push && $account === null) {
            return null;
        }

        $currency = (string) config('services.azampay.currency', 'TZS');

        $live = PaymentIntent::liveFor($rental);
        if ($live !== null) {
            if ($this->reusable($live, $amount, $method)) {
                return $live;
            }

            $live->update([
                'status' => PaymentIntentStatus::Failed,
                'failure_reason' => GatewayCaptureOutcome::Declined->value,
            ]);
        }

        $intent = PaymentIntent::create([
            'rental_id' => $rental->id,
            'tx_ref' => $this->externalReference($rental),
            'amount' => $amount,
            'currency' => $currency,
            'payment_method' => $method,
            'status' => PaymentIntentStatus::Pending,
        ]);

        $opened = $push
            ? $this->gateway->initiateMobileMoneyCheckout(new GatewayCheckoutRequest(
                txRef: $intent->tx_ref,
                amount: $amount,
                currency: $currency,
                redirectUrl: route('customer.payments.waiting', $intent),
                email: (string) $rental->customer?->user?->email,
                name: (string) $rental->customer?->display_name,
                phoneNumber: $account,
                provider: $method->azampayProvider(),
            ))
            : $this->gateway->initiateHostedCheckout(new GatewayCheckoutRequest(
                txRef: $intent->tx_ref,
                amount: $amount,
                currency: $currency,
                redirectUrl: route('customer.payments.return', $intent),
                email: (string) $rental->customer?->user?->email,
                name: (string) $rental->customer?->display_name,
                phoneNumber: $rental->customer?->user?->phone,
                provider: $method->azampayProvider(),
            ));

        if ($opened === null) {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'failure_reason' => GatewayCaptureOutcome::GatewayUnavailable->value,
            ]);

            return null;
        }

        // The push answers with the gateway's transaction id; the hosted page answers
        // with the URL the payer is sent to.
        $intent->update($push
            ? ['gateway_tx_id' => $opened, 'status' => PaymentIntentStatus::Started]
            : ['checkout_url' => $opened, 'status' => PaymentIntentStatus::Started]);

        return $intent;
    }

    /**
     * The browser came back from the hosted checkout. Nothing is trusted from the query
     * string: the gateway is asked what actually happened.
     */
    public function settleFromReturn(PaymentIntent $intent, string $reference = ''): GatewayCaptureOutcome
    {
        return $this->settle($intent, $reference);
    }

    public function settleFromWebhook(string $txRef, string $reference = ''): GatewayCaptureOutcome
    {
        $intent = PaymentIntent::findByTxRef($txRef);

        if ($intent === null) {
            return GatewayCaptureOutcome::UnknownIntent;
        }

        return $this->settle($intent, $reference);
    }

    public function abandon(PaymentIntent $intent): void
    {
        if ($intent->status === PaymentIntentStatus::Recorded) {
            return;
        }

        $intent->update([
            'status' => PaymentIntentStatus::Failed,
            'failure_reason' => GatewayCaptureOutcome::Declined->value,
        ]);
    }

    /**
     * The single funnel every confirmation passes through.
     */
    public function capture(PaymentIntent $intent, GatewayTransactionResult $result): GatewayCaptureOutcome
    {
        if ($intent->payment_id !== null || $intent->gateway_ref !== null) {
            return GatewayCaptureOutcome::AlreadyRecorded;
        }

        if (! $result->successful) {
            return $this->refuse($intent, GatewayCaptureOutcome::Declined);
        }

        if ($result->txRef !== $intent->tx_ref) {
            return $this->refuse($intent, GatewayCaptureOutcome::ReferenceMismatch, true);
        }

        $rental = $intent->rental;

        if ($rental === null || $rental->status === RentalStatus::Cancelled) {
            return $this->refuse($intent, GatewayCaptureOutcome::RentalClosed, true);
        }

        if ($result->currency !== $intent->currency) {
            return $this->refuse($intent, GatewayCaptureOutcome::CurrencyMismatch, true);
        }

        if (abs($result->amount - (float) $intent->amount) > self::AMOUNT_TOLERANCE) {
            return $this->refuse($intent, GatewayCaptureOutcome::AmountMismatch, true);
        }

        if ($rental->balanceDue() < self::AMOUNT_TOLERANCE) {
            return $this->refuse($intent, GatewayCaptureOutcome::OverBalance, true);
        }

        $reference = $result->gatewayRef ?? 'AZAMPAY-TX-'.$result->transactionId;
        $recordable = min((float) $intent->amount, $rental->balanceDue());

        try {
            $payment = DB::transaction(function () use ($intent, $rental, $result, $reference, $recordable): ?Payment {
                if (! PaymentIntent::claim($intent->id, [
                    'gateway_ref' => $reference,
                    'gateway_tx_id' => $result->transactionId,
                    'verified_at' => now(),
                ])) {
                    return null;
                }

                $payment = $this->payments->record($rental, new PaymentDraft(
                    amount: $recordable,
                    method: $intent->payment_method,
                    paidAt: now(),
                    // The ledger column means "mobile money reference", so a card charge
                    // keeps its gateway reference in the notes instead.
                    reference: in_array($intent->payment_method, PaymentMethod::mobileMoney(), true) ? $reference : null,
                    notes: 'Paid online with AzamPay · '.$reference,
                ));

                $intent->refresh()->update([
                    'payment_id' => $payment->id,
                    'status' => PaymentIntentStatus::Recorded,
                ]);

                return $payment;
            });
        } catch (QueryException $exception) {
            if (! $this->isDuplicateGatewayReference($exception)) {
                throw $exception;
            }

            return GatewayCaptureOutcome::AlreadyRecorded;
        }

        if ($payment === null) {
            return GatewayCaptureOutcome::AlreadyRecorded;
        }

        $this->payments->announce($payment, route('customer.rentals.index'));

        if ($recordable < (float) $intent->amount - self::AMOUNT_TOLERANCE) {
            $this->alertFleet(
                'Online payment captured below the checkout amount',
                'Rental '.$rental->id.' took '.number_format($recordable, 0).' TZS of the '
                    .number_format((float) $intent->amount, 0).' TZS AzamPay confirmed.'
            );
        }

        return GatewayCaptureOutcome::Recorded;
    }

    private function settle(PaymentIntent $intent, string $reference): GatewayCaptureOutcome
    {
        if (! $this->enabled()) {
            return GatewayCaptureOutcome::GatewayUnavailable;
        }

        // Ask about the gateway's own reference; when a leg of the flow never carried
        // one, fall back to the id stored at push time, then to our external id.
        $lookup = $reference !== '' ? $reference : (string) ($intent->gateway_tx_id ?? $intent->tx_ref);

        $result = $this->gateway->verifyTransaction($lookup);

        if ($result === null) {
            return GatewayCaptureOutcome::GatewayUnavailable;
        }

        return $this->capture($intent, $result);
    }

    private function reusable(PaymentIntent $intent, float $amount, PaymentMethod $method): bool
    {
        if ($intent->payment_method !== $method
            || abs((float) $intent->amount - $amount) > self::AMOUNT_TOLERANCE) {
            return false;
        }

        // A push stays live while its USSD prompt is outstanding on the handset; a
        // hosted page is only reusable while its URL is there to send the payer to.
        return in_array($method, PaymentMethod::mobileMoney(), true)
            ? $intent->status === PaymentIntentStatus::Started
            : filled($intent->checkout_url);
    }

    private function refuse(PaymentIntent $intent, GatewayCaptureOutcome $outcome, bool $needsAttention = false): GatewayCaptureOutcome
    {
        $intent->update([
            'status' => PaymentIntentStatus::Failed,
            'failure_reason' => $outcome->value,
        ]);

        if ($needsAttention) {
            $this->alertFleet('AzamPay confirmation refused', $outcome->label().' — intent '.$intent->id.' ('.$intent->tx_ref.').');
        }

        return $outcome;
    }

    private function alertFleet(string $title, string $message): void
    {
        $this->notifications->forUsers(
            $this->notifications->fleetTeam(),
            $title,
            $message,
            route('admin.payments.index'),
            'payment_attention',
        );
    }

    private function isDuplicateGatewayReference(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && (str_contains($exception->getMessage(), 'gateway_ref') || str_contains($exception->getMessage(), 'payment_id'));
    }

    /**
     * The gateway's external id. AzamPay allows 128 characters for a push but only 30
     * for the hosted page, so every checkout uses the short form.
     */
    private function externalReference(Rental $rental): string
    {
        return 'CRMS'.$rental->id.'-'.substr((string) Str::ulid(), 0, 13);
    }

    /**
     * AzamPay dials the MSISDN country-code prefixed: 2557XXXXXXXX.
     */
    private function normalizeMobileAccount(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone) ?? '';

        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            return '255'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && preg_match('/^[67]/', $digits)) {
            return '255'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '255')) {
            return $digits;
        }

        return strlen($digits) >= 9 ? $digits : null;
    }
}

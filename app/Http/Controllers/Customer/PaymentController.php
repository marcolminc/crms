<?php

namespace App\Http\Controllers\Customer;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreOnlinePaymentRequest;
use App\Models\PaymentIntent;
use App\Models\Rental;
use App\Services\OnlinePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PaymentController extends Controller
{
    public function store(StoreOnlinePaymentRequest $request, Rental $rental, OnlinePaymentService $checkout)
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return redirect()
                ->route('profile.edit')
                ->with('error', 'Please complete your customer profile before paying online.');
        }

        abort_unless($rental->customer_id === $customer->id, 404);

        $intent = $checkout->startCheckout($rental, PaymentMethod::from($request->validated('payment_method')));

        if ($intent === null) {
            return redirect()->route('customer.rentals.index')
                ->with('error', 'Online payment is unavailable right now. Please pay at our office instead.');
        }

        // A mobile money push has no page to leave for: the customer approves the
        // prompt on their handset, so we send them to a waiting screen instead.
        if ($intent->isAwaitingPhoneApproval() || ($intent->status === PaymentIntentStatus::Pending
            && in_array($intent->payment_method, PaymentMethod::mobileMoney(), true))) {
            return redirect()->route('customer.payments.waiting', $intent);
        }

        if (blank($intent->checkout_url)) {
            return redirect()->route('customer.rentals.index')
                ->with('error', 'Online payment is unavailable right now. Please pay at our office instead.');
        }

        return redirect()->away($intent->checkout_url);
    }

    /**
     * The waiting screen for a mobile money push. It polls the status endpoint below
     * and falls through when the AzamPay callback records the payment.
     */
    public function waiting(Request $request, PaymentIntent $paymentIntent)
    {
        $this->ownCustomerOrFourOhFour($request, $paymentIntent);

        if (! $paymentIntent->isAwaitingPhoneApproval()) {
            return redirect()->route('customer.rentals.index');
        }

        return view('customer.payments.waiting', [
            'intent' => $paymentIntent,
            'rental' => $paymentIntent->rental,
        ]);
    }

    /**
     * JSON read-out for the waiting screen's polling. Reports the intent only.
     */
    public function status(Request $request, PaymentIntent $paymentIntent): JsonResponse
    {
        $this->ownCustomerOrFourOhFour($request, $paymentIntent);

        return response()->json([
            'status' => $paymentIntent->status->value,
            'finished' => $paymentIntent->status === PaymentIntentStatus::Recorded,
            'failed' => $paymentIntent->status === PaymentIntentStatus::Failed,
        ]);
    }

    /**
     * "I have approved the prompt." The customer's tap is only a hint: settlement
     * still asks the gateway what happened, so a premature click records nothing.
     */
    public function confirm(Request $request, PaymentIntent $paymentIntent, OnlinePaymentService $checkout)
    {
        $this->ownCustomerOrFourOhFour($request, $paymentIntent);

        $outcome = $checkout->settleFromReturn($paymentIntent);

        $key = $outcome === GatewayCaptureOutcome::Recorded || $outcome === GatewayCaptureOutcome::AlreadyRecorded
            ? 'status'
            : 'error';

        return redirect()->route('customer.rentals.index')->with($key, $this->messageFor($outcome));
    }

    /**
     * The browser came back from the hosted (card) checkout. AzamPay's redirect
     * parameters are not fully documented, so anything usable is passed on as a
     * gateway reference and the verify step decides; with nothing to go on the
     * callback remains the source of truth.
     */
    public function complete(Request $request, PaymentIntent $paymentIntent, OnlinePaymentService $checkout)
    {
        $this->ownCustomerOrFourOhFour($request, $paymentIntent);

        // The gateway echoes the reference we sent; anything else is not our checkout.
        $echoed = $request->query('tx_ref') ?? $request->query('externalId') ?? $request->query('externalid');
        abort_unless($echoed === $paymentIntent->tx_ref, 404);

        $status = strtolower((string) $request->query('status'));
        $reference = (string) ($request->query('transaction_id') ?? $request->query('reference') ?? '');

        if (in_array($status, ['cancelled', 'canceled', 'failed', 'invalid', 'failure'], true)) {
            $checkout->abandon($paymentIntent);

            return redirect()->route('customer.rentals.index')
                ->with('error', 'That checkout was closed before the payment went through.');
        }

        $outcome = $checkout->settleFromReturn($paymentIntent, $reference);

        if ($outcome === GatewayCaptureOutcome::GatewayUnavailable && $reference === '') {
            // No usable reference came back with the redirect; the callback will
            // confirm the payment on its own.
            return redirect()->route('customer.rentals.index')
                ->with('status', 'Your payment is being confirmed. We will update your balance as soon as AzamPay reports it.');
        }

        $key = $outcome === GatewayCaptureOutcome::Recorded || $outcome === GatewayCaptureOutcome::AlreadyRecorded
            ? 'status'
            : 'error';

        return redirect()->route('customer.rentals.index')->with($key, $this->messageFor($outcome));
    }

    private function ownCustomerOrFourOhFour(Request $request, PaymentIntent $paymentIntent): void
    {
        $customer = $request->user()->customer;

        abort_unless($customer && $paymentIntent->rental?->customer_id === $customer->id, 404);
    }

    private function messageFor(GatewayCaptureOutcome $outcome): string
    {
        return match ($outcome) {
            GatewayCaptureOutcome::Recorded,
            GatewayCaptureOutcome::AlreadyRecorded => 'Thank you, your online payment has been recorded against the rental.',
            GatewayCaptureOutcome::GatewayUnavailable => 'We could not reach AzamPay to confirm your payment. It will be recorded as soon as we can verify it.',
            GatewayCaptureOutcome::Declined => 'The payment request was not approved on your phone. Nothing has been added to your account — you can try again.',
            default => 'AzamPay reported '.$outcome->label().'. Nothing has been added to your account — please contact the office if money left your wallet.',
        };
    }
}

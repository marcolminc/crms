<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gives money back through AzamPay's disbursement rail (design §8.3, 5.6):
 * refunds of collected payments and, later, deposit releases. The gateway is
 * asked first and only a synchronously confirmed payout reaches the ledger —
 * a refund row therefore always represents money that actually left the
 * merchant wallet. History is appended, never edited: the original payment
 * keeps its row and its amount, refunds hang off it via parent_payment_id.
 */
final class RefundService
{
    public function __construct(
        private readonly AzampayClient $gateway,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * What this payment can still be refunded for through AzamPay. Mobile money
     * only: the disbursement rail pays out to wallets, not to cards.
     */
    public function refundsOnline(Payment $payment): bool
    {
        return $this->gateway->disburseEnabled()
            && $payment->type->isCollected()
            && $payment->paid_at !== null
            && $payment->status !== PaymentStatus::Refunded
            && in_array($payment->payment_method, PaymentMethod::mobileMoney(), true);
    }

    /**
     * @throws DomainException when the refund cannot be honoured as stated
     */
    public function refund(Payment $payment, float $amount, string $reason, ?User $approvedBy = null): Payment
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new DomainException('A refund must say why.');
        }

        if (! $this->refundsOnline($payment)) {
            throw new DomainException('This payment cannot be refunded through AzamPay; settle it at the office instead.');
        }

        if ($amount <= 0.005 || $amount > $payment->refundableAmount() + 0.005) {
            throw new DomainException('The refund is larger than what is still outstanding on that payment.');
        }

        $customer = $payment->rental?->customer?->user;
        $account = $this->normalizeMobileAccount($customer?->phone);
        $provider = $payment->payment_method->azampayProvider();

        if ($account === null || $provider === null) {
            throw new DomainException('We have no mobile money account to refund this payment to.');
        }

        $payout = new GatewayPayoutRequest(
            externalId: 'CRMSF'.$payment->id.'-'.substr((string) Str::ulid(), 0, 13),
            amount: round($amount, 2),
            currency: (string) config('services.azampay.currency', 'TZS'),
            destinationAccount: $account,
            destinationName: (string) ($customer->name ?? ''),
            destinationProvider: $provider,
            remarks: Str::limit($reason, 90, ''),
        );

        // Ask the gateway first. If it does not confirm the payout, nothing is
        // written and the caller is told the refund did not happen.
        $reference = $this->gateway->disburse($payout);

        if ($reference === null) {
            throw new DomainException('AzamPay did not accept the refund; no money has been sent.');
        }

        $fullyRefunded = $amount >= $payment->refundableAmount() - 0.005;

        return DB::transaction(function () use ($payment, $amount, $reason, $reference, $fullyRefunded, $approvedBy): Payment {
            $refund = $payment->rental->payments()->create([
                'receipt_number' => Payment::nextReceiptNumber(1),
                'type' => PaymentType::Refund,
                'parent_payment_id' => $payment->id,
                'amount' => round($amount, 2),
                'payment_method' => $payment->payment_method,
                'status' => PaymentStatus::Paid,
                'transaction_reference' => $reference,
                'recorded_by' => $approvedBy?->id,
                'paid_at' => now(),
                'notes' => 'Refund via AzamPay · '.$reference.' · '.$reason,
            ]);

            if ($fullyRefunded) {
                $payment->update(['status' => PaymentStatus::Refunded]);
            }

            $this->notifications->forCustomer(
                $payment->rental->customer,
                'Refund sent',
                'A refund of '.number_format($amount, 2).' '.(string) config('services.azampay.currency', 'TZS').' was sent to your mobile money wallet.',
                route('customer.rentals.index'),
                'payment_refunded',
            );

            return $refund;
        });
    }

    private function normalizeMobileAccount(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '255'.substr($digits, 1);
        }

        if (preg_match('/^[67]\d{8}$/', $digits)) {
            return '255'.$digits;
        }

        return preg_match('/^255\d{9}$/', $digits) ? $digits : null;
    }
}

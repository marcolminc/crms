<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Models\Payment;
use App\Models\Rental;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The single place a row is ever added to the payments ledger.
 */
final class PaymentRecordingService
{
    private const RECEIPT_CLASH_ATTEMPTS = 5;

    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @throws DomainException when the draft would break a money invariant
     */
    public function record(Rental $rental, PaymentDraft $draft): Payment
    {
        if ($rental->status === RentalStatus::Cancelled) {
            throw new DomainException('A cancelled rental cannot take payments.');
        }

        if ($draft->amount <= 0) {
            throw new DomainException('A payment must be greater than zero.');
        }

        if ($draft->amount > $rental->balanceDue()) {
            throw new DomainException('The payment is larger than the outstanding balance.');
        }

        return $this->insertWithReceiptRetry($rental, $draft, $this->deriveStatus($rental, $draft->amount, $draft->paidAt));
    }

    /**
     * An unpaid draft stays Pending, which is how the ledger records a promise without
     * pretending the money has arrived.
     */
    public function deriveStatus(Rental $rental, float $amount, ?Carbon $paidAt): PaymentStatus
    {
        if ($paidAt === null) {
            return PaymentStatus::Pending;
        }

        return $amount >= $rental->balanceDue() ? PaymentStatus::Paid : PaymentStatus::Partial;
    }

    public function announce(Payment $payment, string $link): void
    {
        $rental = $payment->rental;
        $rental->refresh();

        $this->notifications->forCustomer(
            $rental->customer,
            'Payment received',
            'We received '.number_format((float) $payment->amount, 0).' TZS for rental '.$rental->id
                .'. Remaining balance: '.number_format($rental->balanceDue(), 0).' TZS.',
            $link,
            'payment_recorded',
        );
    }

    private function insertWithReceiptRetry(Rental $rental, PaymentDraft $draft, PaymentStatus $status): Payment
    {
        return DB::transaction(function () use ($rental, $draft, $status): Payment {
            $clash = null;

            for ($attempt = 0; $attempt < self::RECEIPT_CLASH_ATTEMPTS; $attempt++) {
                try {
                    return $rental->payments()->create([
                        'receipt_number' => Payment::nextReceiptNumber($attempt),
                        'amount' => $draft->amount,
                        'payment_method' => $draft->method,
                        'status' => $status,
                        'transaction_reference' => $draft->reference,
                        'recorded_by' => $draft->recordedBy,
                        'paid_at' => $draft->paidAt,
                        'notes' => $draft->notes,
                    ]);
                } catch (QueryException $exception) {
                    if (! $this->isReceiptNumberClash($exception)) {
                        throw $exception;
                    }

                    $clash = $exception;
                }
            }

            throw $clash;
        });
    }

    private function isReceiptNumberClash(QueryException $exception): bool
    {
        return (string) $exception->getCode() === '23000'
            && str_contains($exception->getMessage(), 'receipt_number');
    }
}

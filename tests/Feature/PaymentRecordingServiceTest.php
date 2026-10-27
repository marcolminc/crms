<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use App\Services\PaymentDraft;
use App\Services\PaymentRecordingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PaymentRecordingServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PaymentRecordingService $ledger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledger = $this->app->make(PaymentRecordingService::class);
    }

    private function rentalOwing(float $amount): Rental
    {
        $rental = Rental::factory()->active()->create([
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['total_amount' => $amount])->save();

        return $rental;
    }

    /**
     * @return array<string, array{float, string|null, PaymentStatus}>
     */
    public static function statusCases(): array
    {
        return [
            'a promise to pay' => [200000.0, null, PaymentStatus::Pending],
            'the balance in full' => [200000.0, '2026-11-01', PaymentStatus::Paid],
            'less than the balance' => [50000.0, '2026-11-01', PaymentStatus::Partial],
            'one shilling short' => [199999.0, '2026-11-01', PaymentStatus::Partial],
        ];
    }

    #[DataProvider('statusCases')]
    public function test_the_status_is_derived_from_the_amount_and_the_paid_date(float $amount, ?string $paidAt, PaymentStatus $expected): void
    {
        $rental = $this->rentalOwing(200000);

        $this->assertSame(
            $expected,
            $this->ledger->deriveStatus($rental, $amount, $paidAt === null ? null : Carbon::parse($paidAt)),
        );
    }

    public function test_a_recorded_draft_becomes_a_ledger_row_with_the_derived_status(): void
    {
        $staff = User::factory()->counterAgent()->create();
        $rental = $this->rentalOwing(200000);

        $payment = $this->ledger->record($rental, new PaymentDraft(
            amount: 50000,
            method: PaymentMethod::Cash,
            paidAt: Carbon::parse('2026-11-01'),
            recordedBy: $staff->id,
            notes: 'Part payment at the counter',
        ));

        $this->assertSame(PaymentStatus::Partial, $payment->status);
        $this->assertSame($rental->id, $payment->rental_id);
        $this->assertSame($staff->id, $payment->recorded_by);
        $this->assertSame('Part payment at the counter', $payment->notes);
        $this->assertNull($payment->transaction_reference);
        $this->assertEqualsWithDelta(150000.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_a_pending_row_stays_inert_for_the_balance(): void
    {
        $rental = $this->rentalOwing(200000);

        $payment = $this->ledger->record($rental, new PaymentDraft(
            amount: 200000,
            method: PaymentMethod::Cash,
        ));

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->paidAmount(), 0.01);
        $this->assertEqualsWithDelta(200000.0, $rental->fresh()->balanceDue(), 0.01);
        $this->assertFalse($rental->fresh()->isSettled());
    }

    public function test_a_draft_larger_than_the_balance_never_reaches_the_ledger(): void
    {
        $rental = $this->rentalOwing(200000);

        try {
            $this->ledger->record($rental, new PaymentDraft(
                amount: 200001,
                method: PaymentMethod::Cash,
                paidAt: Carbon::parse('2026-11-01'),
            ));
            $this->fail('The ledger accepted a payment larger than the balance.');
        } catch (DomainException $exception) {
            $this->assertSame('The payment is larger than the outstanding balance.', $exception->getMessage());
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_the_ledger_refuses_a_draft_that_is_not_money(): void
    {
        $rental = $this->rentalOwing(200000);

        foreach ([0.0, -5000.0] as $amount) {
            try {
                $this->ledger->record($rental, new PaymentDraft(
                    amount: $amount,
                    method: PaymentMethod::Cash,
                    paidAt: Carbon::parse('2026-11-01'),
                ));
                $this->fail('The ledger accepted a payment of '.$amount.' TZS.');
            } catch (DomainException $exception) {
                $this->assertSame('A payment must be greater than zero.', $exception->getMessage());
            }
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_cancelled_rental_is_closed_to_the_ledger(): void
    {
        $rental = $this->rentalOwing(200000);
        $rental->update(['status' => RentalStatus::Cancelled]);

        try {
            $this->ledger->record($rental, new PaymentDraft(
                amount: 10000,
                method: PaymentMethod::Cash,
                paidAt: Carbon::parse('2026-11-01'),
            ));
            $this->fail('The ledger accepted a payment for a cancelled rental.');
        } catch (DomainException $exception) {
            $this->assertSame('A cancelled rental cannot take payments.', $exception->getMessage());
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_receipt_number_clash_is_retried_with_the_next_sequence(): void
    {
        $rental = $this->rentalOwing(200000);
        $sequence = 'RC-'.today()->format('Ymd').'-0001';

        $squatter = Payment::factory()->cash()->create([
            'rental_id' => $rental->id,
            'receipt_number' => $sequence,
            'amount' => 1000,
        ]);
        // Filed yesterday, so today's sequence still starts at 0001 and collides.
        $squatter->forceFill(['created_at' => now()->subDay()])->save();

        $payment = $this->ledger->record($rental, new PaymentDraft(
            amount: 50000,
            method: PaymentMethod::Cash,
            paidAt: Carbon::parse('2026-11-01'),
        ));

        $this->assertSame('RC-'.today()->format('Ymd').'-0002', $payment->receipt_number);
        $this->assertNotSame($squatter->receipt_number, $payment->receipt_number);
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_the_receipt_link_in_the_announcement_belongs_to_the_caller(): void
    {
        $rental = $this->rentalOwing(200000);

        $online = $this->ledger->record($rental, new PaymentDraft(
            amount: 100000,
            method: PaymentMethod::Mpesa,
            paidAt: Carbon::parse('2026-11-01'),
            reference: 'ABC123456',
        ));
        $this->ledger->announce($online, route('customer.rentals.index'));

        $counter = $this->ledger->record($rental->fresh(), new PaymentDraft(
            amount: 100000,
            method: PaymentMethod::Cash,
            paidAt: Carbon::parse('2026-11-02'),
        ));
        $this->ledger->announce($counter, route('admin.payments.receipt', $counter));

        // recordAlert stores links host-relative so they resolve on every
        // hostname the app is reached through.
        $this->assertDatabaseHas('notifications', [
            'user_id' => $rental->customer->user_id,
            'type' => 'payment_recorded',
            'link' => (string) parse_url(route('customer.rentals.index'), PHP_URL_PATH),
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $rental->customer->user_id,
            'type' => 'payment_recorded',
            'link' => (string) parse_url(route('admin.payments.receipt', $counter), PHP_URL_PATH),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function staff(): User
    {
        return User::factory()->rentalStaff()->create();
    }

    private function rentalWithTotal(float $total): Rental
    {
        $rental = Rental::factory()->active()->create([
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['total_amount' => $total])->save();

        return $rental;
    }

    public function test_a_cash_payment_within_balance_is_recorded_and_settles_the_rental(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(200000);

        $response = $this->actingAs($staff)->post(route('admin.payments.store', $rental), [
            'amount' => 200000,
            'payment_method' => 'cash',
        ]);

        $payment = Payment::query()->firstOrFail();
        $response->assertRedirect(route('admin.payments.receipt', $payment));

        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertMatchesRegularExpression('/^RC-\d{8}-\d{4}$/', $payment->receipt_number);
        $this->assertSame($staff->id, $payment->recorded_by);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
        $this->assertDatabaseHas('notifications', ['user_id' => $rental->customer->user_id, 'type' => 'payment_recorded']);
    }

    public function test_a_part_payment_marks_the_row_partial_and_keeps_the_balance(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(200000);

        $this->actingAs($staff)->post(route('admin.payments.store', $rental), [
            'amount' => 50000,
            'payment_method' => 'cash',
        ])->assertSessionMissing('errors');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(PaymentStatus::Partial, $payment->status);
        $this->assertEqualsWithDelta(150000.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_overpayment_beyond_the_outstanding_balance_is_rejected(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(200000);

        $this->actingAs($staff)
            ->post(route('admin.payments.store', $rental), [
                'amount' => 250000,
                'payment_method' => 'cash',
            ])
            ->assertSessionHasErrors([
                'amount' => 'The payment is larger than the outstanding balance of 200,000 TZS. Record 200,000 TZS or less.',
            ]);

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_mobile_money_payments_need_a_transaction_reference(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(200000);

        $this->actingAs($staff)
            ->post(route('admin.payments.store', $rental), [
                'amount' => 100000,
                'payment_method' => 'mpesa',
            ])
            ->assertSessionHasErrors([
                'transaction_reference' => 'Mobile money payments need the transaction reference so we can trace them.',
            ]);

        $this->actingAs($staff)
            ->post(route('admin.payments.store', $rental), [
                'amount' => 100000,
                'payment_method' => 'mpesa',
                'transaction_reference' => 'ABC123456',
            ])
            ->assertSessionMissing('errors');

        $this->assertSame('ABC123456', Payment::query()->firstOrFail()->transaction_reference);
    }

    public function test_a_payment_without_a_paid_date_stays_pending_and_does_not_reduce_the_balance(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(200000);

        $this->actingAs($staff)->post(route('admin.payments.store', $rental), [
            'amount' => 200000,
            'payment_method' => 'cash',
            'paid_at' => '',
        ])->assertSessionMissing('errors');

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertEqualsWithDelta(200000.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_cancelled_rentals_are_closed_to_payments(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(200000);
        $rental->update(['status' => RentalStatus::Cancelled]);

        $this->actingAs($staff)->get(route('admin.payments.create', $rental))
            ->assertRedirect(route('admin.rentals.index'));

        $this->actingAs($staff)->post(route('admin.payments.store', $rental), [
            'amount' => 10000,
            'payment_method' => 'cash',
        ])->assertRedirect(route('admin.rentals.index'));

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_the_payment_form_is_blocked_once_a_rental_is_settled(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(100000);
        $rental->payments()->create([
            'receipt_number' => 'RC-SETTLE-1',
            'amount' => 100000,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->actingAs($staff)->get(route('admin.payments.create', $rental))
            ->assertRedirect(route('admin.rentals.show', $rental))
            ->assertSessionHas('error');
    }

    public function test_receipt_page_renders_for_staff(): void
    {
        $staff = $this->staff();
        $rental = $this->rentalWithTotal(120000);

        $this->actingAs($staff)->post(route('admin.payments.store', $rental), [
            'amount' => 120000,
            'payment_method' => 'cash',
        ]);

        $payment = Payment::query()->firstOrFail();

        $this->actingAs($staff)
            ->get(route('admin.payments.receipt', $payment))
            ->assertOk()
            ->assertSee($payment->receipt_number);

        $this->actingAs($staff)->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee($rental->customer->display_name);
    }

    public function test_receipt_numbers_are_unique_across_payments(): void
    {
        $staff = $this->staff();
        $a = $this->rentalWithTotal(100000);
        $b = $this->rentalWithTotal(100000);

        $this->actingAs($staff)->post(route('admin.payments.store', $a), ['amount' => 100000, 'payment_method' => 'cash']);
        $this->actingAs($staff)->post(route('admin.payments.store', $b), ['amount' => 100000, 'payment_method' => 'cash']);

        $this->assertSame(2, Payment::query()->distinct('receipt_number')->count('receipt_number'));
    }
}

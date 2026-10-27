<?php

namespace Tests\Feature\Models;

use App\Enums\PaymentStatus;
use App\Models\Rental;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RentalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recalculate_keeps_money_columns_in_step_with_rate_and_period(): void
    {
        $rental = new Rental([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-04',
            'days' => 4,
            'rate_per_day' => 150000,
            'additional_charges' => 20000,
            'discount' => 10000,
        ]);

        $rental->recalculate();

        $this->assertSame(600000.0, (float) $rental->subtotal);
        $this->assertSame(610000.0, (float) $rental->total_amount);
    }

    public function test_total_never_goes_negative_when_discount_exceeds_subtotal(): void
    {
        $rental = new Rental([
            'days' => 2,
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 500000,
        ]);

        $rental->recalculate();

        $this->assertSame(0.0, (float) $rental->total_amount);
    }

    public function test_paid_amount_ignores_unpaid_and_refunded_rows(): void
    {
        $rental = Rental::factory()->create(['rate_per_day' => 100000, 'days' => 1, 'additional_charges' => 0, 'discount' => 0]);
        $rental->forceFill(['total_amount' => 300000])->save();

        $rental->payments()->create([
            'receipt_number' => 'RC-TEST-1',
            'amount' => 100000,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $rental->payments()->create([
            'receipt_number' => 'RC-TEST-2',
            'amount' => 80000,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Refunded,
            'paid_at' => now(),
        ]);
        $rental->payments()->create([
            'receipt_number' => 'RC-TEST-3',
            'amount' => 90000,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Pending,
            'paid_at' => null,
        ]);

        $this->assertSame(100000.0, $rental->paidAmount());
        $this->assertSame(200000.0, $rental->balanceDue());
        $this->assertFalse($rental->isSettled());
    }

    public function test_balance_never_drops_below_zero_and_settled_reads_true(): void
    {
        $rental = Rental::factory()->create(['rate_per_day' => 100000, 'days' => 1, 'additional_charges' => 0, 'discount' => 0]);
        $rental->forceFill(['total_amount' => 100000])->save();

        $rental->payments()->create([
            'receipt_number' => 'RC-TEST-4',
            'amount' => 150000,
            'payment_method' => 'cash',
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->assertSame(0.0, $rental->balanceDue());
        $this->assertTrue($rental->isSettled());
    }

    public function test_open_scope_only_returns_active_or_overdue_rentals(): void
    {
        Rental::factory()->active()->create();
        Rental::factory()->overdue()->create();
        Rental::factory()->completed()->create();
        Rental::factory()->cancelled()->create();

        $this->assertSame(2, Rental::query()->open()->count());
    }
}

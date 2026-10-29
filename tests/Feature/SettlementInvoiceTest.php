<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\RentalStatus;
use App\Models\ChargeApproval;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * UC11 / UC15 / FR9: settlement is captured at return (a manual late-return
 * charge beside the existing damage charge) and presented on a printable
 * invoice.
 */
class SettlementInvoiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function counterAgent(): User
    {
        return User::factory()->counterAgent()->create();
    }

    private function manager(): User
    {
        return User::factory()->branchManager()->create();
    }

    private function activeRental(): Rental
    {
        $rental = Rental::factory()->active()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days' => 2,
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['subtotal' => 200000, 'total_amount' => 200000])->save();

        return $rental->fresh();
    }

    public function test_a_late_return_charge_is_captured_at_return_and_added_to_the_total(): void
    {
        $rental = $this->activeRental();

        $this->actingAs($this->counterAgent())
            ->post(route('admin.returns.store', $rental), [
                'condition' => 'good',
                'additional_charges' => 0,
                'late_fee' => 40000,
            ])
            ->assertSessionMissing('errors');

        $fresh = $rental->fresh();
        $this->assertSame(RentalStatus::Completed, $fresh->status);
        $this->assertEqualsWithDelta(40000, (float) $fresh->late_fee, 0.01);
        // 200,000 hire + 40,000 late.
        $this->assertEqualsWithDelta(240000, (float) $fresh->total_amount, 0.01);
    }

    public function test_a_material_late_charge_is_escalated_and_a_rejection_reverses_it(): void
    {
        config(['crms.approval_threshold' => 20000]);

        $agent = $this->counterAgent();
        $rental = $this->activeRental();

        $this->actingAs($agent)
            ->post(route('admin.returns.store', $rental), [
                'condition' => 'good',
                'late_fee' => 50000,
            ])
            ->assertSessionMissing('errors');

        $approval = ChargeApproval::query()
            ->where('rental_id', $rental->id)
            ->where('type', ApprovalType::Late->value)
            ->first();

        $this->assertNotNull($approval, 'A material late charge must raise an approval request.');
        $this->assertSame(ApprovalStatus::Pending, $approval->status);

        $this->actingAs($this->manager())
            ->put(route('admin.approvals.reject', $approval), ['note' => 'Customer was only minutes late.'])
            ->assertSessionMissing('errors');

        $fresh = $rental->fresh();
        $this->assertEqualsWithDelta(0, (float) $fresh->late_fee, 0.01);
        $this->assertEqualsWithDelta(200000, (float) $fresh->total_amount, 0.01);
    }

    public function test_the_invoice_lists_each_charge_and_the_balance_due(): void
    {
        $rental = Rental::factory()->completed()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-01',
            'days' => 1,
            'rate_per_day' => 100000,
        ]);
        $rental->driver_fee = 30000;
        $rental->additional_charges = 20000;
        $rental->late_fee = 50000;
        $rental->discount = 10000;
        $rental->recalculate();
        $rental->save();

        $this->actingAs($this->counterAgent())
            ->get(route('admin.rentals.invoice', $rental))
            ->assertOk()
            ->assertSee('Settlement invoice')
            ->assertSee('Office driver')
            ->assertSee('Damage &amp; other charges', false)
            ->assertSee('Late return')
            ->assertSee('Discount')
            ->assertSee('190,000') // total = 100k + 20k + 30k + 50k - 10k
            ->assertSee('Balance due');
    }

    public function test_the_invoice_shows_a_refund_due_when_the_rental_is_over_paid(): void
    {
        $rental = $this->activeRental();
        $rental->update(['status' => RentalStatus::Completed]);

        // Collect more than the total: a charge was reversed after the customer had paid.
        $payment = Payment::factory()->mobileMoney()->create(['rental_id' => $rental->id]);
        $payment->forceFill(['amount' => 300000])->save();

        $this->actingAs($this->counterAgent())
            ->get(route('admin.rentals.invoice', $rental))
            ->assertOk()
            ->assertSee('Refund due to customer')
            ->assertSee('100,000');
    }

    public function test_a_customer_can_open_their_own_invoice_but_not_anothers(): void
    {
        $rental = $this->activeRental();
        $other = $this->activeRental();
        $customer = $rental->customer->user;

        $this->actingAs($customer)
            ->get(route('customer.rentals.invoice', $rental))
            ->assertOk()
            ->assertSee('Settlement invoice');

        $this->actingAs($customer)
            ->get(route('customer.rentals.invoice', $other))
            ->assertNotFound();
    }
}

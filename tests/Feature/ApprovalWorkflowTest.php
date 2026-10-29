<?php

namespace Tests\Feature;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Models\ChargeApproval;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * UC14: material discounts and damage charges are applied but escalated to a
 * branch manager, who can approve (keep) or reject (reverse) them.
 */
class ApprovalWorkflowTest extends TestCase
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

    private function rentalWithMoney(float $rate, int $days, float $discount = 0, float $charges = 0): Rental
    {
        $rental = Rental::factory()->active()->create();

        // Set the money columns deterministically for the reversal assertions.
        $rental->rate_per_day = $rate;
        $rental->days = $days;
        $rental->start_date = '2026-11-01';
        $rental->end_date = '2026-11-01';
        $rental->discount = $discount;
        $rental->additional_charges = $charges;
        $rental->recalculate();
        $rental->save();

        return $rental->fresh();
    }

    public function test_a_material_discount_applied_through_an_update_is_escalated_to_managers(): void
    {
        $agent = $this->counterAgent();
        $manager = $this->manager();

        $rental = Rental::factory()->active()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days' => 2,
            'rate_per_day' => 300000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['subtotal' => 600000, 'total_amount' => 600000])->save();

        $this->actingAs($agent)
            ->put(route('admin.rentals.update', $rental), [
                'end_date' => '2026-11-02',
                'discount' => 250000,
                'additional_charges' => 0,
            ])
            ->assertSessionMissing('errors');

        $approval = ChargeApproval::query()->where('rental_id', $rental->id)->where('type', ApprovalType::Discount->value)->first();

        $this->assertNotNull($approval, 'A material discount must raise an approval request.');
        $this->assertSame(ApprovalStatus::Pending, $approval->status);
        $this->assertSame($agent->id, $approval->requested_by);
        $this->assertEqualsWithDelta(250000, (float) $approval->amount, 0.01);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $manager->id,
            'type' => 'charge_approval_requested',
        ]);
    }

    public function test_a_small_discount_is_not_escalated(): void
    {
        $agent = $this->counterAgent();
        $this->manager();

        $rental = Rental::factory()->active()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days' => 2,
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['subtotal' => 200000, 'total_amount' => 200000])->save();

        $this->actingAs($agent)
            ->put(route('admin.rentals.update', $rental), [
                'end_date' => '2026-11-02',
                'discount' => 10000,
                'additional_charges' => 0,
            ])
            ->assertSessionMissing('errors');

        $this->assertSame(0, ChargeApproval::query()->count());
    }

    public function test_a_manager_can_approve_and_the_discount_stands(): void
    {
        $agent = $this->counterAgent();
        $rental = $this->rentalWithMoney(300000, 1, 250000);

        $approval = ChargeApproval::factory()->discount()->create([
            'rental_id' => $rental->id,
            'amount' => 250000,
            'requested_by' => $agent->id,
        ]);

        $this->actingAs($this->manager())
            ->put(route('admin.approvals.approve', $approval))
            ->assertSessionMissing('errors');

        $this->assertSame(ApprovalStatus::Approved, $approval->fresh()->status);
        // The discount is untouched on approval.
        $this->assertEqualsWithDelta(250000, (float) $rental->fresh()->discount, 0.01);
    }

    public function test_rejecting_a_discount_reverses_it_on_the_rental(): void
    {
        $agent = $this->counterAgent();
        $rental = $this->rentalWithMoney(400000, 1, 250000);

        $approval = ChargeApproval::factory()->discount()->create([
            'rental_id' => $rental->id,
            'amount' => 250000,
            'requested_by' => $agent->id,
        ]);

        $this->actingAs($this->manager())
            ->put(route('admin.approvals.reject', $approval), ['note' => 'Not authorised for this customer.'])
            ->assertSessionMissing('errors');

        $fresh = $rental->fresh();
        $this->assertSame(ApprovalStatus::Rejected, $approval->fresh()->status);
        $this->assertEqualsWithDelta(0, (float) $fresh->discount, 0.01);
        // Total goes back up to the full subtotal once the discount is reversed.
        $this->assertEqualsWithDelta(400000, (float) $fresh->total_amount, 0.01);

        $this->assertDatabaseHas('notifications', ['user_id' => $agent->id, 'type' => 'charge_approval_reviewed']);
        $this->assertDatabaseHas('notifications', ['user_id' => $rental->customer->user_id, 'type' => 'rental_total_adjusted']);
    }

    public function test_rejecting_a_damage_charge_reverses_the_additional_charge(): void
    {
        $agent = $this->counterAgent();
        $rental = $this->rentalWithMoney(200000, 1, 0, 300000);

        $approval = ChargeApproval::factory()->damage()->create([
            'rental_id' => $rental->id,
            'amount' => 300000,
            'requested_by' => $agent->id,
        ]);

        $this->actingAs($this->manager())
            ->put(route('admin.approvals.reject', $approval), ['note' => 'No evidence of damage.'])
            ->assertSessionMissing('errors');

        $fresh = $rental->fresh();
        $this->assertEqualsWithDelta(0, (float) $fresh->additional_charges, 0.01);
        $this->assertEqualsWithDelta(200000, (float) $fresh->total_amount, 0.01);
    }

    public function test_rejection_requires_a_reason(): void
    {
        $approval = ChargeApproval::factory()->discount()->create();

        $this->actingAs($this->manager())
            ->put(route('admin.approvals.reject', $approval), ['note' => ''])
            ->assertSessionHasErrors('note');

        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
    }

    public function test_a_counter_agent_cannot_open_the_approvals_queue(): void
    {
        $this->actingAs($this->counterAgent())
            ->get(route('admin.approvals.index'))
            ->assertForbidden();
    }

    public function test_a_non_manager_cannot_approve(): void
    {
        $approval = ChargeApproval::factory()->discount()->create();

        $this->actingAs($this->counterAgent())
            ->put(route('admin.approvals.approve', $approval))
            ->assertForbidden();

        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()->status);
    }
}

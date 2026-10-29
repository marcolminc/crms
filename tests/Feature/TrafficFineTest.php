<?php

namespace Tests\Feature;

use App\Enums\FineLiability;
use App\Enums\FineStatus;
use App\Models\Rental;
use App\Models\TrafficFine;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * UC25: traffic fines are recorded at return inspection and then assigned a
 * liability - billed to the customer (added to the rental) or absorbed by the
 * company. Only agents, managers and finance may handle them.
 */
class TrafficFineTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function rental(): Rental
    {
        $rental = Rental::factory()->active()->create();
        $rental->rate_per_day = 100000;
        $rental->days = 1;
        $rental->discount = 0;
        $rental->additional_charges = 0;
        $rental->recalculate();
        $rental->save();

        return $rental->fresh();
    }

    public function test_an_agent_can_record_a_pending_fine_on_a_rental(): void
    {
        $agent = User::factory()->counterAgent()->create();
        $rental = $this->rental();

        $this->actingAs($agent)
            ->post(route('admin.fines.store', $rental), [
                'reference' => 'TRC-778',
                'description' => 'Speeding',
                'amount' => 50000,
                'issued_at' => '2026-10-20',
            ])
            ->assertRedirect(route('admin.fines.create', $rental));

        $fine = TrafficFine::query()->where('rental_id', $rental->id)->first();
        $this->assertNotNull($fine);
        $this->assertSame(FineStatus::Pending, $fine->status);
        $this->assertNull($fine->liability);
        $this->assertEqualsWithDelta(50000, (float) $fine->amount, 0.01);

        $this->assertDatabaseHas('audit_logs', ['action' => 'fine.recorded', 'subject_id' => $fine->id]);
    }

    public function test_billing_a_fine_adds_it_to_the_rental_and_notifies_the_customer(): void
    {
        $manager = User::factory()->branchManager()->create();
        $rental = $this->rental();

        $fine = TrafficFine::factory()->create([
            'rental_id' => $rental->id,
            'vehicle_id' => $rental->vehicle_id,
            'amount' => 50000,
            'status' => FineStatus::Pending,
        ]);

        $this->actingAs($manager)
            ->put(route('admin.fines.bill', $fine))
            ->assertSessionMissing('errors');

        $fine->refresh();
        $rental->refresh();

        $this->assertSame(FineStatus::Billed, $fine->status);
        $this->assertSame(FineLiability::Customer, $fine->liability);
        $this->assertEqualsWithDelta(50000, (float) $rental->additional_charges, 0.01);
        $this->assertEqualsWithDelta(150000, (float) $rental->total_amount, 0.01);

        $this->assertDatabaseHas('notifications', ['user_id' => $rental->customer->user_id, 'type' => 'fine_billed']);
    }

    public function test_absorbing_a_fine_leaves_the_rental_unchanged(): void
    {
        $finance = User::factory()->finance()->create();
        $rental = $this->rental();

        $fine = TrafficFine::factory()->create([
            'rental_id' => $rental->id,
            'amount' => 120000,
            'status' => FineStatus::Pending,
        ]);

        $this->actingAs($finance)
            ->put(route('admin.fines.absorb', $fine))
            ->assertSessionMissing('errors');

        $fine->refresh();
        $this->assertSame(FineStatus::Absorbed, $fine->status);
        $this->assertSame(FineLiability::Company, $fine->liability);
        $this->assertEqualsWithDelta(0, (float) $rental->fresh()->additional_charges, 0.01);
    }

    public function test_a_decided_fine_cannot_be_decided_again(): void
    {
        $manager = User::factory()->branchManager()->create();
        $rental = $this->rental();

        $fine = TrafficFine::factory()->create([
            'rental_id' => $rental->id,
            'amount' => 50000,
            'status' => FineStatus::Pending,
        ]);

        $this->actingAs($manager)->put(route('admin.fines.bill', $fine))->assertSessionMissing('errors');

        $this->actingAs($manager)
            ->put(route('admin.fines.absorb', $fine))
            ->assertSessionHas('error');

        $this->assertSame(FineStatus::Billed, $fine->fresh()->status);
    }

    public function test_fleet_staff_cannot_handle_traffic_fines(): void
    {
        $fleet = User::factory()->fleetStaff()->create();
        $rental = $this->rental();

        $this->actingAs($fleet)->get(route('admin.fines.index'))->assertForbidden();

        $this->actingAs($fleet)->post(route('admin.fines.store', $rental), [
            'amount' => 50000,
        ])->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RentalFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function staff(): User
    {
        return User::factory()->rentalStaff()->create();
    }

    private function customer(): Customer
    {
        $user = User::factory()->customer()->create();

        return Customer::factory()->create(['user_id' => $user->id]);
    }

    public function test_staff_can_hire_out_a_vehicle_and_the_status_machine_moves(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create(['rate_per_day' => 150000]);

        $response = $this->actingAs($staff)->post('/admin/rentals', [
            'customer' => $customer->id,
            'vehicle' => $vehicle->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
            'pickup_location' => 'Mwanza City Office',
        ]);

        $rental = Rental::query()->firstOrFail();
        $response->assertRedirect(route('admin.rentals.show', $rental));

        $this->assertSame(RentalStatus::Active, $rental->status);
        $this->assertSame(3, $rental->days);
        $this->assertSame(450000.0, (float) $rental->subtotal);
        $this->assertSame(450000.0, (float) $rental->total_amount);
        $this->assertSame($staff->id, $rental->staff_id);
        $this->assertSame(VehicleStatus::Rented, $vehicle->fresh()->status);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $customer->user_id,
            'type' => 'rental_started',
        ]);
    }

    public function test_converting_a_confirmed_reservation_completes_it(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create(['rate_per_day' => 200000]);
        $reservation = Reservation::factory()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($staff)->post('/admin/rentals', [
            'reservation' => $reservation->id,
            'customer' => $customer->id,
            'vehicle' => $vehicle->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
        ])->assertSessionMissing('errors');

        $rental = Rental::query()->firstOrFail();
        $this->assertSame($reservation->id, $rental->reservation_id);
        $this->assertSame(ReservationStatus::Completed, $reservation->fresh()->status);
    }

    public function test_overlapping_active_rental_blocks_a_second_hire_out(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create();

        Rental::factory()->active()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-11-05',
            'end_date' => '2026-11-10',
            'days' => 6,
        ]);
        $vehicle->update(['status' => VehicleStatus::Rented]);

        $this->actingAs($staff)
            ->post('/admin/rentals', [
                'customer' => $customer->id,
                'vehicle' => $vehicle->id,
                'start_date' => '2026-11-08',
                'end_date' => '2026-11-12',
            ])
            ->assertSessionHasErrors('vehicle');

        $this->assertDatabaseCount('rentals', 1);
    }

    public function test_rental_cannot_start_in_the_past(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($staff)
            ->post('/admin/rentals', [
                'customer' => $customer->id,
                'vehicle' => $vehicle->id,
                'start_date' => now()->subDays(3)->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('start_date');
    }

    public function test_a_vehicle_under_maintenance_cannot_be_hired_out(): void
    {
        $staff = $this->staff();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->underMaintenance()->create();

        $this->actingAs($staff)
            ->post('/admin/rentals', [
                'customer' => $customer->id,
                'vehicle' => $vehicle->id,
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-02',
            ])
            ->assertSessionHasErrors('vehicle');
    }

    public function test_editing_a_rentals_period_recalculates_the_totals(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days' => 2,
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['subtotal' => 200000, 'total_amount' => 200000])->save();

        $this->actingAs($staff)
            ->put(route('admin.rentals.update', $rental), [
                'end_date' => '2026-11-05',
                'additional_charges' => 50000,
                'discount' => 10000,
            ])
            ->assertSessionMissing('errors');

        $rental->refresh();
        $this->assertSame(5, $rental->days);
        $this->assertSame(500000.0, (float) $rental->subtotal);
        $this->assertSame(540000.0, (float) $rental->total_amount);
    }

    public function test_cancelling_a_rental_returns_the_vehicle_to_the_fleet(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();
        $vehicle = $rental->vehicle;

        $this->actingAs($staff)
            ->put(route('admin.rentals.cancel', $rental), ['reason' => 'Customer changed plans'])
            ->assertSessionMissing('errors');

        $this->assertSame(RentalStatus::Cancelled, $rental->fresh()->status);
        $this->assertStringContainsString('Customer changed plans', $rental->fresh()->notes);
        $this->assertSame(VehicleStatus::Available, $vehicle->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $rental->customer->user_id, 'type' => 'rental_cancelled']);
    }

    public function test_cancelling_a_rental_defers_to_scheduled_maintenance(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();
        Maintenance::factory()->create([
            'vehicle_id' => $rental->vehicle_id,
            'status' => MaintenanceStatus::InProgress,
            'start_date' => '2026-01-01',
            'end_date' => null,
        ]);

        $this->actingAs($staff)
            ->put(route('admin.rentals.cancel', $rental), ['reason' => 'Breakdown reported'])
            ->assertSessionMissing('errors');

        $this->assertSame(VehicleStatus::Maintenance, $rental->vehicle->fresh()->status);
    }

    public function test_completed_rentals_cannot_be_cancelled_twice(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->completed()->create();

        $this->actingAs($staff)
            ->put(route('admin.rentals.cancel', $rental), ['reason' => 'Too late'])
            ->assertSessionHas('error');

        $this->assertSame(RentalStatus::Completed, $rental->fresh()->status);
    }

    public function test_rentals_index_filters_and_totals_are_consistent(): void
    {
        $staff = $this->staff();
        $vehicle = Vehicle::factory()->available()->create(['plate_number' => 'T999 XYZ']);
        $rental = Rental::factory()->active()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days' => 2,
            'rate_per_day' => 100000,
        ]);
        $rental->forceFill(['total_amount' => 200000])->save();
        $rental->payments()->create([
            'receipt_number' => 'RC-FILTER-1',
            'amount' => 50000,
            'payment_method' => 'cash',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        Rental::factory()->completed()->create();

        $this->actingAs($staff)->get('/admin/rentals?status=active')
            ->assertOk()
            ->assertSee('T999 XYZ');
    }
}

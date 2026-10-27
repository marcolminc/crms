<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\VehicleReturn;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReturnFlowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function staff(): User
    {
        return User::factory()->counterAgent()->create();
    }

    public function test_returning_a_vehicle_completes_the_rental_and_frees_it(): void
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
        $vehicle = $rental->vehicle;
        $vehicle->update(['status' => VehicleStatus::Rented, 'mileage' => 50000]);

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), [
                'condition' => 'good',
                'fuel_level' => 'full',
                'mileage_at_return' => 52000,
                'additional_charges' => 30000,
            ])
            ->assertRedirect(route('admin.rentals.show', $rental));

        $this->assertSame(RentalStatus::Completed, $rental->fresh()->status);
        $this->assertSame(230000.0, (float) $rental->fresh()->total_amount);
        $this->assertSame(VehicleStatus::Available, $vehicle->fresh()->status);
        $this->assertSame(52000, $vehicle->fresh()->mileage);
        $this->assertDatabaseHas('vehicle_returns', [
            'rental_id' => $rental->id,
            'condition' => 'good',
            'processed_by' => $staff->id,
        ]);
        $this->assertDatabaseHas('notifications', ['user_id' => $rental->customer->user_id, 'type' => 'rental_completed']);
    }

    public function test_a_damaged_return_sends_the_vehicle_to_maintenance(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), [
                'condition' => 'damaged',
                'damage_notes' => 'Cracked rear bumper',
                'additional_charges' => 150000,
            ])
            ->assertSessionMissing('errors');

        $this->assertSame(VehicleStatus::Maintenance, $rental->vehicle->fresh()->status);
        $this->assertSame(
            150000.0,
            (float) VehicleReturn::query()->where('rental_id', $rental->id)->value('additional_charges')
        );
    }

    public function test_a_returned_vehicle_with_a_live_reservation_goes_straight_back_to_reserved(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();
        Reservation::factory()->create([
            'vehicle_id' => $rental->vehicle_id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), ['condition' => 'excellent'])
            ->assertSessionMissing('errors');

        $this->assertSame(VehicleStatus::Reserved, $rental->vehicle->fresh()->status);
    }

    public function test_a_rental_cannot_be_returned_twice(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), ['condition' => 'good'])
            ->assertSessionMissing('errors');

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), ['condition' => 'good'])
            ->assertSessionHas('error')
            ->assertRedirect(route('admin.rentals.show', $rental));

        $this->assertDatabaseCount('vehicle_returns', 1);
    }

    public function test_return_mileage_below_the_recorded_odometer_is_rejected(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();
        $rental->vehicle->update(['mileage' => 80000]);

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), [
                'condition' => 'good',
                'mileage_at_return' => 70000,
            ])
            ->assertSessionHasErrors('mileage_at_return');

        $this->assertDatabaseCount('vehicle_returns', 0);
        $this->assertSame(RentalStatus::Active, $rental->fresh()->status);
    }

    public function test_condition_is_mandatory(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), [])
            ->assertSessionHasErrors('condition');
    }

    public function test_completed_rentals_are_closed_to_further_returns(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->completed()->create();

        $this->actingAs($staff)->get(route('admin.returns.create', $rental))
            ->assertRedirect(route('admin.rentals.show', $rental))
            ->assertSessionHas('error');

        $this->actingAs($staff)->post(route('admin.returns.store', $rental), ['condition' => 'good'])
            ->assertRedirect(route('admin.rentals.show', $rental))
            ->assertSessionHas('error');
    }
}

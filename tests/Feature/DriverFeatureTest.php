<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * FR15 / UC23 / UC24: the office-driver feature - pricing a chauffeured
 * booking, assigning a free chauffeur to a rental, and the driver's own
 * trip-log portal.
 */
class DriverFeatureTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ------------------------------------------------------------------
    // FR15 — driver priced at booking
    // ------------------------------------------------------------------

    public function test_a_booking_with_a_driver_is_priced_higher_than_self_drive(): void
    {
        $vehicle = Vehicle::factory()->available()->create(['rate_per_day' => 100000]);
        $customer = Customer::factory()->create();
        $driverRate = (float) config('crms.driver_rate_per_day');

        $this->actingAs($customer->user)
            ->post(route('customer.reservations.store'), [
                'vehicle' => $vehicle->id,
                'start_date' => now()->addDays(3)->toDateString(),
                'end_date' => now()->addDays(4)->toDateString(),
                'needs_driver' => '1',
            ])
            ->assertSessionHasNoErrors();

        $reservation = Reservation::latest()->first();
        $this->assertTrue($reservation->needs_driver);
        // 2 days * (hire + chauffeur)
        $this->assertEqualsWithDelta(2 * (100000 + $driverRate), (float) $reservation->estimated_total, 0.01);
    }

    // ------------------------------------------------------------------
    // UC23 — assign a driver to a rental
    // ------------------------------------------------------------------

    public function test_an_agent_can_assign_a_free_driver_and_both_parties_are_notified(): void
    {
        $rental = Rental::factory()->active()->create([
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->recalculate();
        $rental->save();

        $driver = User::factory()->driver()->create();
        $agent = User::factory()->counterAgent()->create();

        $baseTotal = (float) $rental->total_amount;

        $this->actingAs($agent)
            ->put(route('admin.rentals.assign-driver', $rental), ['driver' => $driver->id])
            ->assertSessionHasNoErrors();

        $rental->refresh();
        $expectedFee = (float) config('crms.driver_rate_per_day') * $rental->days;

        $this->assertSame($driver->id, $rental->driver_id);
        $this->assertEqualsWithDelta($expectedFee, (float) $rental->driver_fee, 0.01);
        $this->assertEqualsWithDelta($baseTotal + $expectedFee, (float) $rental->total_amount, 0.01);

        $this->assertDatabaseHas('notifications', ['user_id' => $driver->id, 'type' => 'driver_assignment']);
        $this->assertDatabaseHas('notifications', ['user_id' => $rental->customer->user_id, 'type' => 'driver_assigned']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rental.driver_assigned']);
    }

    public function test_a_driver_already_busy_over_the_same_dates_is_not_assigned(): void
    {
        $rental = Rental::factory()->active()->create([
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $busyDriver = User::factory()->driver()->create();
        // Same driver is already driving an overlapping open hire.
        Rental::factory()->active()->create([
            'driver_id' => $busyDriver->id,
            'start_date' => now()->addDays(1)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        $this->actingAs(User::factory()->counterAgent()->create())
            ->put(route('admin.rentals.assign-driver', $rental), ['driver' => $busyDriver->id])
            ->assertSessionHasErrors('driver');

        $this->assertNull($rental->fresh()->driver_id);
    }

    public function test_a_finance_user_cannot_assign_drivers(): void
    {
        $rental = Rental::factory()->active()->create();
        $driver = User::factory()->driver()->create();

        $this->actingAs(User::factory()->finance()->create())
            ->put(route('admin.rentals.assign-driver', $rental), ['driver' => $driver->id])
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // UC24 — the driver's own trip-log portal
    // ------------------------------------------------------------------

    public function test_a_driver_can_log_a_daily_trip_on_their_assignment(): void
    {
        $driver = User::factory()->driver()->create();
        $rental = Rental::factory()->active()->create([
            'driver_id' => $driver->id,
            'odometer_at_pickup' => 50000,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($driver)
            ->post(route('driver.rentals.trips.store', $rental), [
                'trip_date' => now()->toDateString(),
                'started_at' => '07:30',
                'ended_at' => '16:45',
                'odometer_start' => 50000,
                'odometer_end' => 50180,
                'incident_notes' => 'Minor scrape in the car park.',
            ])
            ->assertSessionHasNoErrors();

        $trip = $rental->trips()->first();
        $this->assertNotNull($trip);
        $this->assertSame($driver->id, $trip->driver_id);
        $this->assertSame(180, $trip->distanceCovered());
    }

    public function test_a_driver_cannot_log_a_trip_on_another_drivers_rental(): void
    {
        $rental = Rental::factory()->active()->create([
            'driver_id' => User::factory()->driver()->create()->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);
        $other = User::factory()->driver()->create();

        $this->actingAs($other)
            ->post(route('driver.rentals.trips.store', $rental), ['trip_date' => now()->toDateString()])
            ->assertNotFound();
    }

    public function test_a_driver_can_toggle_duty_status(): void
    {
        $driver = User::factory()->driver()->create(['is_on_duty' => false]);

        $this->actingAs($driver)->put(route('driver.availability'))->assertRedirect();
        $this->assertTrue($driver->fresh()->is_on_duty);

        $this->actingAs($driver)->put(route('driver.availability'));
        $this->assertFalse($driver->fresh()->is_on_duty);
    }

    public function test_a_customer_cannot_reach_the_driver_portal(): void
    {
        $customer = Customer::factory()->create()->user;

        $this->actingAs($customer)->get(route('driver.dashboard'))->assertForbidden();
    }
}

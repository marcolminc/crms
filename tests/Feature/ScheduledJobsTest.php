<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * UC22: the automated jobs. Pending-payment checks and overdue flags already
 * have their own suites; this covers the two forward/backward sweeps added for
 * the scheduler - expiring stale reservation holds and sending the day-before
 * pick-up / return reminders.
 */
class ScheduledJobsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_expire_holds_closes_a_stale_pending_reservation_and_releases_the_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Reserved]);
        $reservation = Reservation::factory()->pending()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);

        Artisan::call('reservations:expire-holds');

        $this->assertSame(ReservationStatus::Expired, $reservation->fresh()->status);
        $this->assertSame(VehicleStatus::Available, $vehicle->fresh()->status);
        $this->assertDatabaseHas('notifications', ['type' => 'reservation_expired']);
    }

    public function test_expire_holds_leaves_a_reservation_that_has_not_started_yet(): void
    {
        $reservation = Reservation::factory()->pending()->create([
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(4)->toDateString(),
        ]);

        Artisan::call('reservations:expire-holds');

        $this->assertSame(ReservationStatus::Pending, $reservation->fresh()->status);
    }

    public function test_expire_holds_only_targets_pending_bookings(): void
    {
        $confirmed = Reservation::factory()->confirmed()->create([
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        Artisan::call('reservations:expire-holds');

        $this->assertSame(ReservationStatus::Confirmed, $confirmed->fresh()->status);
    }

    public function test_reminders_notify_the_day_before_a_pickup(): void
    {
        // The factory creates a customer with a linked, sign-in-able user.
        $reservation = Reservation::factory()->confirmed()->create([
            'start_date' => now()->addDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        Artisan::call('rentals:send-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $reservation->customer->user_id,
            'type' => 'pickup_reminder',
        ]);
    }

    public function test_reminders_notify_the_day_before_a_return_is_due(): void
    {
        $rental = Rental::factory()->active()->create([
            'end_date' => now()->addDay()->toDateString(),
        ]);

        Artisan::call('rentals:send-reminders');

        $this->assertDatabaseHas('notifications', [
            'user_id' => $rental->customer->user_id,
            'type' => 'return_reminder',
        ]);
    }

    public function test_a_return_is_not_reminded_when_it_is_further_out(): void
    {
        $rental = Rental::factory()->active()->create([
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        Artisan::call('rentals:send-reminders');

        $this->assertDatabaseMissing('notifications', [
            'user_id' => $rental->customer->user_id,
            'type' => 'return_reminder',
        ]);
    }
}

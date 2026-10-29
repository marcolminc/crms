<?php

namespace Tests\Feature;

use App\Enums\FuelLevel;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Covers readme #23 (pick-up/return handover records), #24/#25 (overdue alerts
 * routed by role and involvement, never to the administrator) and #26 (the
 * administrator's dashboard carries oversight, not the hire-desk queues).
 */
class HandoverAndAlertsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function desk(): User
    {
        return User::factory()->counterAgent()->create();
    }

    private function customer(): Customer
    {
        $user = User::factory()->customer()->create();

        return Customer::factory()->create(['user_id' => $user->id]);
    }

    private function jpeg(string $name): UploadedFile
    {
        return UploadedFile::fake()->create($name, 90, 'image/jpeg');
    }

    // ----------------------------------------------------------------
    // #23 - pick-up handover
    // ----------------------------------------------------------------

    public function test_pickup_records_odometer_fuel_and_named_slot_photos(): void
    {
        Storage::fake('public');
        $staff = $this->desk();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create(['mileage' => 40000]);

        $this->actingAs($staff)->post('/admin/rentals', [
            'customer' => $customer->id,
            'vehicle' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'odometer_at_pickup' => 40120,
            'fuel_at_pickup' => 'full',
            'pickup_photos' => ['front' => $this->jpeg('nose.jpg'), 'back' => $this->jpeg('tail.jpg')],
        ])->assertSessionMissing('errors');

        $rental = Rental::query()->firstOrFail();
        $this->assertSame(40120, $rental->odometer_at_pickup);
        $this->assertSame(FuelLevel::Full, $rental->fuel_at_pickup);
        $this->assertSame(['front', 'back'], array_keys($rental->pickup_photos));
        Storage::disk('public')->assertExists($rental->pickup_photos['front']);

        // The fleet odometer follows the pick-up reading.
        $this->assertSame(40120, $vehicle->fresh()->mileage);

        $this->actingAs($staff)->get(route('admin.rentals.show', $rental))
            ->assertOk()
            ->assertSee('Pick-up handover')
            ->assertSee('40,120 km')
            ->assertSee('Full');
    }

    public function test_an_out_of_range_fuel_level_is_refused(): void
    {
        $staff = $this->desk();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create(['mileage' => 10000]);

        $this->actingAs($staff)->post('/admin/rentals', [
            'customer' => $customer->id,
            'vehicle' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'fuel_at_pickup' => 'nearly full',
        ])->assertSessionHasErrors('fuel_at_pickup');
    }

    public function test_pickup_odometer_below_the_vehicle_mileage_is_rejected(): void
    {
        $staff = $this->desk();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create(['mileage' => 50000]);

        $this->actingAs($staff)->post('/admin/rentals', [
            'customer' => $customer->id,
            'vehicle' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'odometer_at_pickup' => 49000,
        ])->assertSessionHasErrors('odometer_at_pickup');

        $this->assertDatabaseCount('rentals', 0);
    }

    public function test_a_non_image_in_a_pickup_slot_is_refused_by_name(): void
    {
        Storage::fake('public');
        $staff = $this->desk();
        $customer = $this->customer();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($staff)->post('/admin/rentals', [
            'customer' => $customer->id,
            'vehicle' => $vehicle->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
            'pickup_photos' => ['interior' => UploadedFile::fake()->create('sheet.pdf', 10, 'application/pdf')],
        ])->assertSessionHasErrors('pickup_photos.interior');

        $this->assertDatabaseCount('rentals', 0);
    }

    // ----------------------------------------------------------------
    // #23 - return handover photos
    // ----------------------------------------------------------------

    public function test_return_records_condition_photos(): void
    {
        Storage::fake('public');
        $staff = $this->desk();
        $rental = Rental::factory()->active()->create();
        $rental->vehicle->update(['mileage' => 80000]);

        $this->actingAs($staff)
            ->post(route('admin.returns.store', $rental), [
                'condition' => 'good',
                'mileage_at_return' => 82000,
                'photos' => ['front' => $this->jpeg('return-front.jpg')],
            ])
            ->assertSessionMissing('errors');

        $return = $rental->fresh()->vehicleReturn;
        $this->assertSame(['front'], array_keys($return->photos));
        Storage::disk('public')->assertExists($return->photos['front']);

        $this->actingAs($staff)->get(route('admin.rentals.show', $rental))
            ->assertOk()
            ->assertSee('Return photos');
    }

    // ----------------------------------------------------------------
    // #24/#25 - overdue alerts by role and involvement, never the admin
    // ----------------------------------------------------------------

    public function test_flag_overdue_moves_the_rental_and_notifies_the_desk_but_not_the_admin(): void
    {
        $handler = User::factory()->counterAgent()->create();
        $manager = User::factory()->branchManager()->create();
        $admin = User::factory()->admin()->create();
        $otherAgent = User::factory()->counterAgent()->create();
        $customer = $this->customer();

        $rental = Rental::factory()->create([
            'customer_id' => $customer->id,
            'staff_id' => $handler->id,
            'status' => RentalStatus::Active,
            'start_date' => now()->subDays(8)->toDateString(),
            'end_date' => now()->subDays(2)->toDateString(),
        ]);

        $this->artisan('rentals:flag-overdue')->assertSuccessful();

        $this->assertSame(RentalStatus::Overdue, $rental->fresh()->status);

        // Involved staff and the branch manager are chased; the admin and an
        // uninvolved counter agent are not.
        $this->assertDatabaseHas('notifications', ['user_id' => $handler->id, 'title' => 'Overdue rental']);
        $this->assertDatabaseHas('notifications', ['user_id' => $manager->id, 'title' => 'Overdue rental']);
        $this->assertSame(0, Notification::query()->where('user_id', $admin->id)->count());
        $this->assertSame(0, Notification::query()->where('user_id', $otherAgent->id)->count());

        // The customer holding the car is told too.
        $this->assertDatabaseHas('notifications', ['user_id' => $customer->user_id, 'title' => 'Rental overdue']);
    }

    public function test_flag_overdue_is_idempotent_and_leaves_current_rentals_alone(): void
    {
        $handler = User::factory()->counterAgent()->create();
        $customer = $this->customer();

        $late = Rental::factory()->create([
            'customer_id' => $customer->id,
            'staff_id' => $handler->id,
            'status' => RentalStatus::Active,
            'start_date' => now()->subDays(6)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
        ]);
        $current = Rental::factory()->create([
            'customer_id' => $customer->id,
            'staff_id' => $handler->id,
            'status' => RentalStatus::Active,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
        ]);

        $this->artisan('rentals:flag-overdue')->assertSuccessful();
        $this->artisan('rentals:flag-overdue')->assertSuccessful();

        $this->assertSame(RentalStatus::Overdue, $late->fresh()->status);
        $this->assertSame(RentalStatus::Active, $current->fresh()->status);

        // The second run must not double-notify the desk.
        $this->assertSame(1, Notification::query()->where('user_id', $handler->id)->where('title', 'Overdue rental')->count());
    }

    // ----------------------------------------------------------------
    // #26 - dashboard split by role
    // ----------------------------------------------------------------

    public function test_administrator_dashboard_shows_oversight_not_the_hire_desk(): void
    {
        $admin = User::factory()->admin()->create();
        Vehicle::factory()->available()->create();
        Customer::factory()->count(2)->create();

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('Administration overview')
            ->assertSee('Accounts by role')
            ->assertSee('New customers this month')
            ->assertDontSee('Due back today')
            ->assertDontSee('Start a rental');
    }

    public function test_desk_dashboard_keeps_the_operational_queues(): void
    {
        $agent = User::factory()->counterAgent()->create();
        $rental = Rental::factory()->create([
            'status' => RentalStatus::Active,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        $this->actingAs($agent)->get('/admin')
            ->assertOk()
            ->assertSee('Staff overview')
            ->assertSee('Due back today')
            ->assertSee($rental->vehicle->plate_number)
            ->assertDontSee('Administration overview');
    }

    // ----------------------------------------------------------------
    // Pick-ups due: confirmed bookings ready to convert on their start day
    // ----------------------------------------------------------------

    public function test_desk_dashboard_surfaces_pickups_due_for_conversion(): void
    {
        $agent = User::factory()->counterAgent()->create();
        $vehicle = Vehicle::factory()->available()->create();
        $reservation = Reservation::factory()->confirmed()->create([
            'vehicle_id' => $vehicle->id,
            'status' => ReservationStatus::Confirmed,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($agent)->get('/admin')
            ->assertOk()
            ->assertSee('Pick-ups due')
            ->assertSee('Convert to rental')
            ->assertSee($reservation->customer->display_name);
    }

    public function test_pickups_due_excludes_bookings_that_have_not_started_yet(): void
    {
        User::factory()->counterAgent()->create();
        Reservation::factory()->create([
            'status' => ReservationStatus::Confirmed,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
        ]);

        $this->actingAs(User::factory()->counterAgent()->create())->get('/admin')
            ->assertOk()
            ->assertDontSee('Convert to rental');
    }
}

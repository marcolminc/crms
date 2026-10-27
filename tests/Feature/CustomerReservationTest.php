<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Notification;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CustomerReservationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function customerWithProfile(): array
    {
        $user = User::factory()->customer()->create();
        $customer = Customer::factory()->create(['user_id' => $user->id]);

        return [$user, $customer];
    }

    public function test_customer_can_book_a_free_vehicle_for_a_future_window(): void
    {
        [$user, $customer] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->available()->create(['rate_per_day' => 120000]);

        $staff = User::factory()->counterAgent()->create();
        User::factory()->counterAgent()->create(['is_active' => false]);

        $response = $this->actingAs($user)->post('/reservations', [
            'vehicle' => $vehicle->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-04',
            'notes' => 'Airport pickup please',
        ]);

        $reservation = $customer->reservations()->firstOrFail();
        $response->assertRedirect(route('customer.reservations.show', $reservation));

        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertSame(480000.0, (float) $reservation->estimated_total);
        $this->assertSame('Airport pickup please', $reservation->notes);

        // The active fleet team is notified; deactivated staff are not.
        $this->assertDatabaseHas('notifications', ['user_id' => $staff->id, 'type' => 'reservation']);
        $this->assertSame(1, Notification::query()->where('type', 'reservation')->count());
    }

    public function test_a_customer_without_a_profile_is_redirected_to_complete_it(): void
    {
        $user = User::factory()->customer()->create();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($user)
            ->post('/reservations', [
                'vehicle' => $vehicle->id,
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-02',
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_pickup_in_the_past_is_rejected(): void
    {
        [$user] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($user)
            ->post('/reservations', [
                'vehicle' => $vehicle->id,
                'start_date' => now()->subDays(2)->toDateString(),
                'end_date' => now()->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['start_date' => 'The pick-up date cannot be in the past.']);

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_dropoff_before_pickup_is_rejected(): void
    {
        [$user] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($user)
            ->post('/reservations', [
                'vehicle' => $vehicle->id,
                'start_date' => '2026-12-05',
                'end_date' => '2026-12-01',
            ])
            ->assertSessionHasErrors([
                'end_date' => 'The drop-off date must be the same day as, or later than, the pick-up date.',
            ]);
    }

    public function test_bookings_longer_than_ninety_days_are_rejected(): void
    {
        [$user] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($user)
            ->post('/reservations', [
                'vehicle' => $vehicle->id,
                'start_date' => '2027-01-01',
                'end_date' => '2027-05-01',
            ])
            ->assertSessionHasErrors(['end_date' => 'A booking cannot be longer than 90 days.']);
    }

    public function test_double_booking_the_same_vehicle_for_overlapping_dates_is_prevented(): void
    {
        [$user] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($user)->post('/reservations', [
            'vehicle' => $vehicle->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
        ])->assertSessionMissing('errors');

        $this->actingAs($user)
            ->post('/reservations', [
                'vehicle' => $vehicle->id,
                'start_date' => '2026-12-04',
                'end_date' => '2026-12-08',
            ])
            ->assertSessionHasErrors([
                'end_date' => 'That vehicle is not free between the selected dates. Please choose a different period.',
            ]);

        $this->assertDatabaseCount('reservations', 1);
    }

    public function test_vehicles_outside_service_are_not_bookable_even_by_id(): void
    {
        [$user] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->outOfService()->create();

        $this->actingAs($user)
            ->post('/reservations', [
                'vehicle' => $vehicle->id,
                'start_date' => '2026-12-01',
                'end_date' => '2026-12-02',
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_customer_can_cancel_a_future_reservation_and_the_slot_frees_up(): void
    {
        [$user, $customer] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->available()->create();
        $reservation = $customer->reservations()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-12',
            'estimated_total' => 0,
            'status' => ReservationStatus::Pending,
        ]);

        $this->actingAs($user)
            ->put(route('customer.reservations.cancel', $reservation))
            ->assertRedirect(route('customer.reservations.index'));

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);

        // A second customer can now take the freed slot.
        [$otherUser] = $this->customerWithProfile();
        $this->actingAs($otherUser)->post('/reservations', [
            'vehicle' => $vehicle->id,
            'start_date' => '2026-12-10',
            'end_date' => '2026-12-12',
        ])->assertSessionMissing('errors');
    }

    public function test_reservation_that_has_already_started_cannot_be_cancelled(): void
    {
        [$user, $customer] = $this->customerWithProfile();
        $reservation = $customer->reservations()->create([
            'vehicle_id' => Vehicle::factory()->available()->create()->id,
            'start_date' => now()->subDay()->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'estimated_total' => 0,
            'status' => ReservationStatus::Confirmed,
        ]);

        $this->actingAs($user)
            ->put(route('customer.reservations.cancel', $reservation))
            ->assertSessionHas('error');

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
    }

    public function test_customers_cannot_open_someone_elses_reservation(): void
    {
        [$user] = $this->customerWithProfile();
        [$otherUser, $otherCustomer] = $this->customerWithProfile();

        $foreign = $otherCustomer->reservations()->create([
            'vehicle_id' => Vehicle::factory()->available()->create()->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
            'estimated_total' => 0,
            'status' => ReservationStatus::Pending,
        ]);

        $this->actingAs($user)->get(route('customer.reservations.show', $foreign))->assertNotFound();
        $this->actingAs($user)->put(route('customer.reservations.cancel', $foreign))->assertNotFound();
    }

    public function test_reservation_list_can_be_filtered_by_status(): void
    {
        [$user, $customer] = $this->customerWithProfile();
        $vehicle = Vehicle::factory()->available()->create();

        $customer->reservations()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
            'estimated_total' => 0,
            'status' => ReservationStatus::Pending,
        ]);
        $customer->reservations()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-02',
            'estimated_total' => 0,
            'status' => ReservationStatus::Cancelled,
        ]);

        $this->actingAs($user)->get('/reservations?status=pending')
            ->assertOk()
            ->assertSee('1 Dec 2026')
            ->assertDontSee('1 Jan 2027');
    }
}

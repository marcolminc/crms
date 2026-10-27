<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StaffReservationWorkflowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function staff(): User
    {
        return User::factory()->counterAgent()->create();
    }

    private function reservation(string $status = 'pending', array $attributes = []): Reservation
    {
        $customer = Customer::factory()->create(['user_id' => User::factory()->customer()->create()->id]);

        return Reservation::factory()->create(array_merge([
            'customer_id' => $customer->id,
            'vehicle_id' => Vehicle::factory()->available()->create()->id,
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
            'status' => $status,
        ], $attributes));
    }

    public function test_confirming_moves_the_reservation_and_the_vehicle(): void
    {
        $staff = $this->staff();
        $reservation = $this->reservation();

        $this->actingAs($staff)
            ->put(route('admin.reservations.confirm', $reservation))
            ->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Confirmed, $reservation->status);
        $this->assertSame($staff->id, $reservation->confirmed_by);
        $this->assertNotNull($reservation->confirmed_at);
        $this->assertSame(VehicleStatus::Reserved, $reservation->vehicle->fresh()->status);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reservation->customer->user_id,
            'type' => 'reservation_confirmed',
        ]);
    }

    public function test_confirming_a_reservation_that_clashes_with_another_is_refused(): void
    {
        $staff = $this->staff();
        $vehicle = Vehicle::factory()->available()->create();
        $this->reservation('confirmed', ['vehicle_id' => $vehicle->id]);
        $clash = $this->reservation('pending', ['vehicle_id' => $vehicle->id]);

        $this->actingAs($staff)
            ->put(route('admin.reservations.confirm', $clash))
            ->assertSessionHas('error');

        $this->assertSame(ReservationStatus::Pending, $clash->fresh()->status);
    }

    public function test_only_pending_reservations_can_be_confirmed(): void
    {
        $staff = $this->staff();
        $reservation = $this->reservation('completed');

        $this->actingAs($staff)
            ->put(route('admin.reservations.confirm', $reservation))
            ->assertSessionHas('error');
    }

    public function test_rejection_requires_a_reason_and_stores_it(): void
    {
        $staff = $this->staff();
        $reservation = $this->reservation();

        $this->actingAs($staff)
            ->put(route('admin.reservations.reject', $reservation), [])
            ->assertSessionHasErrors('reason');

        $this->actingAs($staff)
            ->put(route('admin.reservations.reject', $reservation), ['reason' => 'Vehicle sold'])
            ->assertSessionHas('status');

        $reservation->refresh();
        $this->assertSame(ReservationStatus::Rejected, $reservation->status);
        $this->assertSame('Vehicle sold', $reservation->rejection_reason);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $reservation->customer->user_id,
            'type' => 'reservation_rejected',
        ]);
    }

    public function test_staff_cancel_frees_the_vehicle_when_nothing_else_holds_it(): void
    {
        $staff = $this->staff();
        $reservation = $this->reservation('confirmed');
        $reservation->vehicle->update(['status' => VehicleStatus::Reserved]);

        $this->actingAs($staff)
            ->put(route('admin.reservations.cancel', $reservation), ['reason' => 'Customer called off'])
            ->assertSessionHas('status');

        $this->assertSame(ReservationStatus::Cancelled, $reservation->fresh()->status);
        $this->assertStringContainsString('Customer called off', $reservation->fresh()->notes);
        $this->assertSame(VehicleStatus::Available, $reservation->vehicle->fresh()->status);
    }

    public function test_a_reservation_with_a_linked_rental_cannot_be_cancelled_or_deleted(): void
    {
        $staff = $this->staff();
        $reservation = $this->reservation('completed');
        Rental::factory()->forReservation($reservation)->create();

        $this->actingAs($staff)
            ->put(route('admin.reservations.cancel', $reservation))
            ->assertSessionHas('error');

        $this->actingAs($staff)
            ->delete(route('admin.reservations.destroy', $reservation))
            ->assertSessionHas('error');

        $this->assertModelExists($reservation);
    }

    public function test_only_terminal_reservations_can_be_deleted(): void
    {
        $staff = $this->staff();
        $live = $this->reservation('pending');

        $this->actingAs($staff)
            ->delete(route('admin.reservations.destroy', $live))
            ->assertSessionHas('error');

        $finished = $this->reservation('cancelled');
        $this->actingAs($staff)
            ->delete(route('admin.reservations.destroy', $finished))
            ->assertRedirect(route('admin.reservations.index'));

        $this->assertModelMissing($finished);
    }

    public function test_listing_filters_by_status_and_search(): void
    {
        $staff = $this->staff();
        $vehicle = Vehicle::factory()->available()->create(['plate_number' => 'T500 QRS']);
        $wanted = $this->reservation('pending', ['vehicle_id' => $vehicle->id]);
        $other = $this->reservation('cancelled');

        $this->actingAs($staff)->get('/admin/reservations?status=pending')
            ->assertOk()
            ->assertSee('T500 QRS');

        $this->actingAs($staff)->get('/admin/reservations?q=T500 QRS')
            ->assertOk()
            ->assertSee('T500 QRS');

        $this->actingAs($staff)->get(route('admin.reservations.show', $wanted))->assertOk();
        $this->actingAs($staff)->get(route('admin.reservations.show', $other))->assertOk();
    }
}

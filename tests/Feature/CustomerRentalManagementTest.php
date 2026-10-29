<?php

namespace Tests\Feature;

use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The customer self-service slice of the rental lifecycle:
 * UC07 change reservation dates, UC08/UC09 view the pick-up and return record,
 * UC10 extend an open hire, UC21 rate a returned hire.
 */
class CustomerRentalManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function customerWithRental(array $rentalAttributes = []): array
    {
        $rental = Rental::factory()->create($rentalAttributes);
        $user = $rental->customer->user;

        return [$user, $rental->fresh()];
    }

    // ------------------------------------------------------------------
    // UC07 — change reservation dates
    // ------------------------------------------------------------------

    public function test_a_customer_can_change_the_dates_of_an_open_reservation(): void
    {
        $vehicle = Vehicle::factory()->available()->create(['rate_per_day' => 100000]);
        $customer = Customer::factory()->create();
        $reservation = Reservation::factory()->pending()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        $this->actingAs($customer->user)
            ->put(route('customer.reservations.update', $reservation), [
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(12)->toDateString(),
            ])
            ->assertRedirect(route('customer.reservations.show', $reservation))
            ->assertSessionHasNoErrors();

        $reservation->refresh();
        $this->assertEqualsWithDelta(300000, (float) $reservation->estimated_total, 0.01);
        $this->assertSame(3, $reservation->days());
        $this->assertDatabaseHas('audit_logs', ['action' => 'reservation.customer_modified']);
    }

    public function test_a_reservation_cannot_be_moved_onto_dates_the_vehicle_is_already_booked_for(): void
    {
        $vehicle = Vehicle::factory()->available()->create();
        $customer = Customer::factory()->create();
        $reservation = Reservation::factory()->pending()->create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ]);

        // Another live reservation already holds the vehicle over the requested window.
        Reservation::factory()->confirmed()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => now()->addDays(9)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->actingAs($customer->user)
            ->put(route('customer.reservations.update', $reservation), [
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(12)->toDateString(),
            ])
            ->assertSessionHasErrors('end_date');

        $this->assertSame(
            now()->addDays(5)->toDateString(),
            $reservation->fresh()->start_date->toDateString()
        );
    }

    // ------------------------------------------------------------------
    // UC10 — extend an open rental
    // ------------------------------------------------------------------

    public function test_a_customer_can_extend_an_open_rental_and_is_charged_for_extra_days(): void
    {
        [$user, $rental] = $this->customerWithRental([
            'status' => RentalStatus::Active,
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->recalculate();
        $rental->save();

        $this->actingAs($user)
            ->put(route('customer.rentals.extend', $rental), [
                'end_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $rental->refresh();
        $this->assertEqualsWithDelta(600000, (float) $rental->total_amount, 0.01);
        $this->assertSame(6, $rental->days);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rental.customer_extended']);
        $this->assertDatabaseHas('notifications', ['type' => 'rental_extended']);
    }

    public function test_an_extension_is_blocked_when_the_vehicle_is_already_reserved_ahead(): void
    {
        $rental = Rental::factory()->active()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'end_date' => now()->toDateString(),
        ]);

        Reservation::factory()->confirmed()->create([
            'vehicle_id' => $rental->vehicle_id,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        $this->actingAs($rental->customer->user)
            ->put(route('customer.rentals.extend', $rental), [
                'end_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertSessionHasErrors('end_date');
    }

    public function test_a_completed_rental_cannot_be_extended(): void
    {
        [$user, $rental] = $this->customerWithRental(['status' => RentalStatus::Completed]);

        $this->actingAs($user)
            ->put(route('customer.rentals.extend', $rental), [
                'end_date' => $rental->end_date->copy()->addDays(2)->toDateString(),
            ])
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // UC21 — rate a returned rental
    // ------------------------------------------------------------------

    public function test_a_customer_can_rate_a_completed_rental_once(): void
    {
        [$user, $rental] = $this->customerWithRental(['status' => RentalStatus::Completed]);

        $this->actingAs($user)
            ->post(route('customer.rentals.rate', $rental), [
                'rating' => 4,
                'rating_comment' => 'Smooth pickup, clean car.',
            ])
            ->assertSessionHasNoErrors();

        $rental->refresh();
        $this->assertSame(4, $rental->rating);
        $this->assertSame('Smooth pickup, clean car.', $rental->rating_comment);
        $this->assertNotNull($rental->rated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'rental.rated']);

        // A second rating is refused because the hire is no longer rateable.
        $this->actingAs($user)
            ->post(route('customer.rentals.rate', $rental), ['rating' => 1])
            ->assertSessionHas('error');
        $this->assertSame(4, $rental->fresh()->rating);
    }

    public function test_a_rating_must_be_between_one_and_five(): void
    {
        [$user, $rental] = $this->customerWithRental(['status' => RentalStatus::Completed]);

        $this->actingAs($user)
            ->post(route('customer.rentals.rate', $rental), ['rating' => 7])
            ->assertSessionHasErrors('rating');
    }

    // ------------------------------------------------------------------
    // UC08 / UC09 — the detail page and ownership
    // ------------------------------------------------------------------

    public function test_the_rental_page_shows_the_pickup_and_return_record(): void
    {
        $rental = Rental::factory()->completed()->create([
            'pickup_location' => 'Mwanza City Office',
            'odometer_at_pickup' => 45210,
            'fuel_at_pickup' => 'full',
        ]);
        $rental->vehicleReturn()->create([
            'vehicle_id' => $rental->vehicle_id,
            'returned_at' => now(),
            'condition' => 'good',
            'fuel_level' => 'full',
            'mileage_at_return' => 45610,
            'additional_charges' => 0,
            'processed_by' => User::factory()->counterAgent()->create()->id,
        ]);

        $this->actingAs($rental->customer->user)
            ->get(route('customer.rentals.show', $rental))
            ->assertOk()
            ->assertSee('Vehicle pick-up')
            ->assertSee('Mwanza City Office')
            ->assertSee('Vehicle return')
            ->assertSee('45,610');
    }

    public function test_a_customer_cannot_open_another_customers_rental(): void
    {
        $rental = Rental::factory()->completed()->create();
        $intruder = Customer::factory()->create()->user;

        $this->actingAs($intruder)->get(route('customer.rentals.show', $rental))->assertNotFound();
        $this->actingAs($intruder)->post(route('customer.rentals.rate', $rental), ['rating' => 5])->assertNotFound();
    }
}

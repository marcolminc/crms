<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * One walk through the whole system, the way the office actually uses it:
 * browse, register, book, confirm, hire out, get paid, take the vehicle back.
 */
class RentalLifecycleJourneyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_booking_travels_from_public_catalogue_to_settled_return(): void
    {
        $fleet = Vehicle::factory()->available()->create([
            'make' => 'Toyota',
            'model' => 'Land Cruiser',
            'plate_number' => 'T880 LVC',
            'rate_per_day' => 200000,
            'mileage' => 60000,
        ]);

        $start = now()->toDateString();
        $end = now()->addDays(2)->toDateString();

        // 1. A visitor finds the vehicle while browsing the catalogue.
        $this->get('/vehicles?from='.$start.'&to='.$end)
            ->assertOk()
            ->assertSee('T880 LVC');

        // 2. They register and complete their customer profile.
        $this->post('/register', [
            'name' => 'Grace Isah',
            'email' => 'grace@example.com',
            'phone' => '0765000111',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('customer.dashboard'));

        $customer = User::query()->where('email', 'grace@example.com')->firstOrFail();
        $this->assertSame(UserRole::Customer, $customer->role);

        $this->actingAs($customer)->put('/profile', [
            'name' => 'Grace Isah',
            'email' => 'grace@example.com',
            'phone' => '0765000111',
            'national_id' => '44556677',
            'driving_license_no' => 'DL-445566',
            'city' => 'Mwanza',
            'address' => 'Passage, Mwanza',
            'emergency_contact_name' => 'Peter',
            'emergency_contact_phone' => '0765000222',
        ])->assertSessionHas('status');

        // 3. Booking requires a login, so the staff account is set up next.
        $this->post('/logout');
        $this->post('/reservations', [
            'vehicle' => $fleet->id,
            'start_date' => $start,
            'end_date' => $end,
        ])->assertRedirect(route('login'));

        $this->actingAs($customer)->post('/reservations', [
            'vehicle' => $fleet->id,
            'start_date' => $start,
            'end_date' => $end,
            'notes' => 'Wedding convoy',
        ])->assertSessionMissing('errors');

        $reservation = Reservation::query()->firstOrFail();
        $this->assertSame(ReservationStatus::Pending, $reservation->status);
        $this->assertEqualsWithDelta(600000.0, (float) $reservation->estimated_total, 0.01);

        // 4. Rental staff confirm the request; the vehicle leaves the shelf.
        $staff = User::factory()->counterAgent()->create();
        $this->actingAs($staff)
            ->get(route('admin.reservations.show', $reservation))
            ->assertOk()
            ->assertSee('Grace Isah');

        $this->actingAs($staff)
            ->put(route('admin.reservations.confirm', $reservation))
            ->assertSessionHas('status');

        $this->assertSame(ReservationStatus::Confirmed, $reservation->fresh()->status);
        $this->assertSame(VehicleStatus::Reserved, $fleet->fresh()->status);

        // 5. The customer is handed the keys: the reservation becomes a rental.
        $this->actingAs($staff)->post('/admin/rentals', [
            'reservation' => $reservation->id,
            'customer' => $reservation->customer_id,
            'vehicle' => $fleet->id,
            'start_date' => $start,
            'end_date' => $end,
            'pickup_location' => 'Mwanza City Office',
        ])->assertSessionMissing('errors');

        $rental = Rental::query()->firstOrFail();
        $this->assertSame(RentalStatus::Active, $rental->status);
        $this->assertSame(3, $rental->days);
        $this->assertEqualsWithDelta(600000.0, (float) $rental->total_amount, 0.01);
        $this->assertSame(ReservationStatus::Completed, $reservation->fresh()->status);
        $this->assertSame(VehicleStatus::Rented, $fleet->fresh()->status);

        // 6. Deposit by mobile money (reference mandatory), then the rest in cash.
        $this->actingAs($staff)
            ->post(route('admin.payments.store', $rental), [
                'amount' => 400000,
                'payment_method' => 'mpesa',
            ])
            ->assertSessionHasErrors('transaction_reference');

        $this->actingAs($staff)->post(route('admin.payments.store', $rental), [
            'amount' => 400000,
            'payment_method' => 'mpesa',
            'transaction_reference' => 'QRD77T9',
        ])->assertSessionMissing('errors');

        $this->assertEqualsWithDelta(200000.0, $rental->fresh()->balanceDue(), 0.01);
        $this->assertSame(PaymentStatus::Partial, Payment::query()->latest('id')->firstOrFail()->status);

        // 7. The vehicle comes back scratched: damage is charged and it goes to the garage.
        $this->actingAs($staff)->post(route('admin.returns.store', $rental), [
            'condition' => 'damaged',
            'fuel_level' => 'three_quarter',
            'mileage_at_return' => 61200,
            'damage_notes' => 'Scratched right flank',
            'additional_charges' => 50000,
        ])->assertSessionMissing('errors');

        $rental->refresh();
        $this->assertSame(RentalStatus::Completed, $rental->status);
        $this->assertEqualsWithDelta(650000.0, (float) $rental->total_amount, 0.01);
        $this->assertEqualsWithDelta(250000.0, $rental->balanceDue(), 0.01);
        $this->assertSame(VehicleStatus::Maintenance, $fleet->fresh()->status);
        $this->assertSame(61200, $fleet->fresh()->mileage);

        // 8. The balance is cleared and a printable receipt is issued.
        $this->actingAs($staff)->post(route('admin.payments.store', $rental), [
            'amount' => 250000,
            'payment_method' => 'cash',
        ])->assertSessionMissing('errors');

        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
        $this->assertTrue($rental->fresh()->isSettled());

        $receipt = Payment::query()->where('amount', 250000)->firstOrFail();
        $this->actingAs($staff)
            ->get(route('admin.payments.receipt', $receipt))
            ->assertOk()
            ->assertSee($receipt->receipt_number);

        // 9. The customer can look back over the finished hire and their alerts.
        $this->actingAs($customer)->get('/rentals')->assertOk()->assertSee('T880 LVC');
        $this->actingAs($customer)->get('/notifications')
            ->assertOk()
            ->assertSee('Rental completed');

        // 10. Management closes the month with the reports.
        $manager = User::factory()->branchManager()->create();
        foreach (['fleet', 'rentals', 'payments', 'returns'] as $report) {
            $this->actingAs($manager)
                ->get(route('admin.reports.show', $report))
                ->assertOk();
        }

        // The vehicle cannot be re-booked while the garage job is open.
        $this->actingAs($customer)
            ->post('/reservations', [
                'vehicle' => $fleet->id,
                'start_date' => now()->addDays(10)->toDateString(),
                'end_date' => now()->addDays(12)->toDateString(),
            ])
            ->assertSessionHasErrors('end_date');
    }
}

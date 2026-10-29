<?php

namespace Tests\Feature;

use App\Enums\ReservationStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Readme: lists search dynamically on a single keyword instead of a filter
 * form. The JavaScript layer only auto-submits the GET form, so the server
 * side of every search card is what these tests pin down — including the
 * users page, where the old Role dropdown is gone for good.
 */
class DynamicSearchTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function staff(): User
    {
        return User::factory()->counterAgent()->create();
    }

    public function test_users_search_matches_phone_and_no_longer_filters_by_role(): void
    {
        $admin = $this->admin();
        $amina = User::factory()->customer()->create(['name' => 'Amina Yusuf', 'phone' => '0715777777']);
        User::factory()->customer()->create(['name' => 'Juma Bakari', 'phone' => '0713000000']);

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => '777777']))
            ->assertOk()
            ->assertSee('Amina Yusuf')
            ->assertDontSee('Juma Bakari');

        // The removed role filter must not narrow anything any more.
        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Amina', 'role' => 'admin']))
            ->assertOk()
            ->assertSee('Amina Yusuf');
    }

    public function test_users_page_no_longer_renders_the_role_filter(): void
    {
        $this->actingAs($this->admin())->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('All roles')
            ->assertDontSee('Apply filters');
    }

    public function test_customers_search_matches_the_national_id(): void
    {
        $staff = $this->staff();
        $withId = Customer::factory()->create(['national_id' => '99887766']);
        $withId->user->update(['name' => 'Amina Yusuf']);
        $other = Customer::factory()->create(['national_id' => '11223344']);
        $other->user->update(['name' => 'Neema Joseph']);

        $this->actingAs($staff)->get(route('admin.customers.index', ['q' => '99887766']))
            ->assertOk()
            ->assertSee('Amina Yusuf')
            ->assertDontSee('Neema Joseph');
    }

    public function test_rentals_search_matches_the_plate_number(): void
    {
        $staff = $this->staff();
        $hit = Rental::factory()->active()->create([
            'vehicle_id' => Vehicle::factory()->create(['plate_number' => 'T245 QWE'])->id,
        ]);
        $miss = Rental::factory()->active()->create([
            'vehicle_id' => Vehicle::factory()->create(['plate_number' => 'T999 ZXR'])->id,
        ]);

        $response = $this->actingAs($staff)->get(route('admin.rentals.index', ['q' => 'T245']))
            ->assertOk()
            ->assertSee('T245 QWE')
            ->assertDontSee('T999 ZXR');

        // The matched row shows its own customer, so assert against the other.
        $response->assertSee($hit->customer->user->name)
            ->assertDontSee($miss->customer->user->name);
    }

    public function test_reservations_search_matches_the_customer_name(): void
    {
        $staff = $this->staff();
        $vehicle = Vehicle::factory()->available()->create();

        $booked = Customer::factory()->create();
        $booked->user->update(['name' => 'Fatuma Salum']);
        Reservation::factory()->create([
            'customer_id' => $booked->id,
            'vehicle_id' => $vehicle->id,
            'status' => ReservationStatus::Pending,
        ]);

        $stranger = Customer::factory()->create();
        $stranger->user->update(['name' => 'Daudi Emmanuel']);
        Reservation::factory()->create([
            'customer_id' => $stranger->id,
            'vehicle_id' => $vehicle->id,
            'status' => ReservationStatus::Pending,
        ]);

        $this->actingAs($staff)->get(route('admin.reservations.index', ['q' => 'Fatuma']))
            ->assertOk()
            ->assertSee('Fatuma Salum')
            ->assertDontSee('Daudi Emmanuel');
    }

    public function test_payments_search_matches_the_receipt_number(): void
    {
        $staff = $this->staff();
        $rental = Rental::factory()->active()->create();
        $rental->customer->user->update(['name' => 'Halima Musa']);
        $other = Rental::factory()->active()->create();
        $other->customer->user->update(['name' => 'Juma Athumani']);

        $payment = Payment::factory()->cash()->create([
            'rental_id' => $rental->id,
            'amount' => min(10000, (float) $rental->total_amount),
            'receipt_number' => 'RC-20260101-4242',
        ]);
        Payment::factory()->cash()->create([
            'rental_id' => $other->id,
            'amount' => min(10000, (float) $other->total_amount),
            'receipt_number' => 'RC-20260101-1313',
        ]);

        $this->actingAs($staff)->get(route('admin.payments.index', ['q' => '4242']))
            ->assertOk()
            ->assertSee($payment->receipt_number)
            ->assertDontSee('RC-20260101-1313')
            ->assertSee('Halima Musa')
            ->assertDontSee('Juma Athumani');
    }

    public function test_audit_log_search_matches_the_description(): void
    {
        $admin = $this->admin();
        app(AuditLogger::class)->log('test.op', 'Zebra websocket recalibrated');
        app(AuditLogger::class)->log('test.op', 'Nothing relevant here');

        $this->actingAs($admin)->get(route('admin.audit-log.index', ['q' => 'Zebra']))
            ->assertOk()
            ->assertSee('Zebra websocket recalibrated')
            ->assertDontSee('Nothing relevant here');
    }

    public function test_vehicles_search_matches_the_plate_number(): void
    {
        $staff = $this->staff();
        Vehicle::factory()->available()->create(['plate_number' => 'T501 KLM', 'make' => 'Toyota', 'model' => 'Hiace']);
        Vehicle::factory()->available()->create(['plate_number' => 'T502 NOP', 'make' => 'Nissan', 'model' => 'Note']);

        $this->actingAs($staff)->get(route('admin.vehicles.index', ['q' => 'T501']))
            ->assertOk()
            ->assertSee('T501 KLM')
            ->assertDontSee('T502 NOP');
    }

    public function test_every_list_card_is_marked_for_live_search_without_an_apply_button(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();

        $pages = [
            $this->actingAs($admin)->get(route('admin.users.index')),
            $this->actingAs($admin)->get(route('admin.audit-log.index')),
            $this->actingAs($staff)->get(route('admin.customers.index')),
            $this->actingAs($staff)->get(route('admin.rentals.index')),
            $this->actingAs($staff)->get(route('admin.reservations.index')),
            $this->actingAs($staff)->get(route('admin.payments.index')),
            $this->actingAs($staff)->get(route('admin.vehicles.index')),
        ];

        foreach ($pages as $page) {
            $page->assertOk()
                ->assertSee('data-live-search', false)
                ->assertSee('data-live-clear', false)
                ->assertSee('live-search-card', false)
                ->assertDontSee('Apply filters');
        }
    }
}

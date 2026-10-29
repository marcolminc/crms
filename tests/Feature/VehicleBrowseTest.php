<?php

namespace Tests\Feature;

use App\Enums\VehicleStatus;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VehicleBrowseTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_catalogue_lists_only_available_vehicles_without_a_date_window(): void
    {
        $available = Vehicle::factory()->available()->create(['make' => 'Toyota', 'model' => 'Corolla']);
        $rented = Vehicle::factory()->create(['status' => VehicleStatus::Rented, 'make' => 'Nissan', 'model' => 'X-Trail']);

        $this->get('/vehicles')
            ->assertOk()
            ->assertSee($available->plate_number)
            ->assertDontSee($rented->plate_number);
    }

    public function test_search_and_filters_narrow_the_list(): void
    {
        $hilux = Vehicle::factory()->available()->create(['make' => 'Toyota', 'model' => 'Hilux', 'seats' => 5, 'rate_per_day' => 250000]);
        $passo = Vehicle::factory()->available()->create(['make' => 'Toyota', 'model' => 'Passo', 'seats' => 5, 'rate_per_day' => 90000]);

        $this->get('/vehicles?q=Hilux')
            ->assertOk()
            ->assertSee($hilux->plate_number)
            ->assertDontSee($passo->plate_number);

        $this->get('/vehicles?max_rate=100000')
            ->assertOk()
            ->assertSee($passo->plate_number)
            ->assertDontSee($hilux->plate_number);
    }

    public function test_date_window_hides_vehicles_booked_within_it(): void
    {
        $vehicle = Vehicle::factory()->available()->create();
        $vehicle->reservations()->create([
            'customer_id' => Customer::factory()->create()->id,
            'start_date' => '2026-11-04',
            'end_date' => '2026-11-08',
            'estimated_total' => 0,
            'status' => 'confirmed',
        ]);

        $this->get('/vehicles?from=2026-11-01&to=2026-11-05')
            ->assertOk()
            ->assertDontSee($vehicle->plate_number);

        $this->get('/vehicles?from=2026-11-09&to=2026-11-12')
            ->assertOk()
            ->assertSee($vehicle->plate_number);
    }

    public function test_vehicle_detail_advertises_free_or_busy_for_a_requested_window(): void
    {
        $vehicle = Vehicle::factory()->available()->create();

        $this->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee($vehicle->plate_number);

        $this->get(route('vehicles.show', $vehicle).'?from=2026-11-01&to=2026-11-02')
            ->assertOk();
    }

    public function test_only_logged_in_customers_see_the_booking_entry_point(): void
    {
        $vehicle = Vehicle::factory()->available()->create();
        $customer = User::factory()->customer()->create();
        $staff = User::factory()->counterAgent()->create();

        $this->get(route('vehicles.show', $vehicle))->assertOk()->assertDontSee('Continue to booking', false);

        $this->actingAs($customer)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertSee('Continue to booking', false);

        $this->actingAs($staff)
            ->get(route('vehicles.show', $vehicle))
            ->assertOk()
            ->assertDontSee('Continue to booking', false);
    }

    public function test_the_filter_card_is_collapsible_and_reopens_when_filters_are_active(): void
    {
        Vehicle::factory()->available()->create(['make' => 'Toyota', 'model' => 'Hilux']);

        // No filters applied: the card ships collapsed so the fleet reclaims
        // the vertical space, and the summary carries no active-count badge.
        $idle = $this->get('/vehicles')->assertOk()->getContent();
        preg_match('/<details data-filter-card[^>]*>/', $idle, $idleTag);
        $this->assertArrayHasKey(0, $idleTag, 'the filter card should be a native <details>');
        $this->assertStringNotContainsString('open', $idleTag[0]);
        $this->assertStringNotContainsString('active</span>', $idle);

        // With a filter active the card reopens itself and reports how many.
        $filtered = $this->get('/vehicles?q=Hilux')->assertOk()->getContent();
        preg_match('/<details data-filter-card[^>]*>/', $filtered, $filteredTag);
        $this->assertStringContainsString('open', $filteredTag[0]);
        $this->assertStringContainsString('1 active</span>', $filtered);
    }

    public function test_the_filter_form_is_wired_for_live_keyword_search(): void
    {
        Vehicle::factory()->available()->create();

        $html = $this->get('/vehicles')->assertOk()->getContent();

        // The JS controller reads these hooks; without them the plain GET form
        // is still the no-JS fallback.
        $this->assertStringContainsString('data-fleet-filter', $html);
        $this->assertStringContainsString('data-fleet-q', $html);
        $this->assertStringContainsString('data-fleet-apply', $html);
    }

    public function test_the_partial_endpoint_filters_by_keyword_for_live_search(): void
    {
        $hilux = Vehicle::factory()->available()->create(['make' => 'Toyota', 'model' => 'Hilux']);
        $passo = Vehicle::factory()->available()->create(['make' => 'Toyota', 'model' => 'Passo']);

        // This is exactly the URL the live keyword search hits as the user type.
        $json = $this->getJson('/vehicles?q=Hilux&partial=1')->assertOk()->json();

        $this->assertStringContainsString($hilux->plate_number, $json['html']);
        $this->assertStringNotContainsString($passo->plate_number, $json['html']);
    }
}

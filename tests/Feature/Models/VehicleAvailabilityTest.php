<?php

namespace Tests\Feature\Models;

use App\Enums\VehicleStatus;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VehicleAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_plain_available_vehicle_is_free(): void
    {
        $vehicle = Vehicle::factory()->available()->create();

        $this->assertTrue($vehicle->isFreeBetween('2026-11-01', '2026-11-05'));
    }

    public function test_overlap_with_a_live_reservation_blocks_the_vehicle(): void
    {
        $vehicle = Vehicle::factory()->available()->create();
        Reservation::factory()->pending()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-11-03',
            'end_date' => '2026-11-07',
        ]);

        $this->assertFalse($vehicle->isFreeBetween('2026-11-01', '2026-11-04'));
        $this->assertTrue($vehicle->isFreeBetween('2026-11-08', '2026-11-10'));
    }

    public function test_cancelled_and_rejected_reservations_do_not_block(): void
    {
        $vehicle = Vehicle::factory()->available()->create();
        Reservation::factory()->cancelled()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);
        Reservation::factory()->rejected()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);

        $this->assertTrue($vehicle->isFreeBetween('2026-11-05', '2026-11-10'));
    }

    public function test_active_or_overdue_rentals_block_but_completed_ones_do_not(): void
    {
        $vehicle = Vehicle::factory()->available()->create();

        Rental::factory()->active()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'days' => 5,
        ]);
        Rental::factory()->completed()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-05',
            'days' => 5,
        ]);

        $this->assertFalse($vehicle->isFreeBetween('2026-12-03', '2026-12-04'));
        $this->assertTrue($vehicle->isFreeBetween('2026-12-06', '2026-12-08'));
        $this->assertTrue($vehicle->isFreeBetween('2027-01-02', '2027-01-03'));
    }

    public function test_scheduled_or_open_maintenance_blocks_and_an_end_date_releases_it(): void
    {
        $vehicle = Vehicle::factory()->available()->create();

        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => 'in_progress',
            'start_date' => '2026-11-01',
            'end_date' => null,
        ]);

        $this->assertFalse($vehicle->isFreeBetween('2026-11-02', '2026-11-09'));

        $vehicle2 = Vehicle::factory()->available()->create();
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle2->id,
            'status' => 'scheduled',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
        ]);

        $this->assertTrue($vehicle2->isFreeBetween('2026-11-04', '2026-11-06'));
    }

    public function test_status_is_not_the_same_thing_as_period_free(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => VehicleStatus::Maintenance]);

        $this->assertFalse($vehicle->isFreeBetween('2026-11-01', '2026-11-02'));
    }

    public function test_available_between_scope_filters_the_fleet(): void
    {
        $free = Vehicle::factory()->available()->create();
        $busy = Vehicle::factory()->available()->create();
        Reservation::factory()->confirmed()->create([
            'vehicle_id' => $busy->id,
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-06',
        ]);
        $rented = Vehicle::factory()->create(['status' => VehicleStatus::Rented]);

        $ids = Vehicle::query()->availableBetween('2026-11-01', '2026-11-05')->pluck('id')->all();

        sort($ids);
        $this->assertSame([$free->id], $ids);
        $this->assertNotContains($busy->id, $ids);
        $this->assertNotContains($rented->id, $ids);
    }
}

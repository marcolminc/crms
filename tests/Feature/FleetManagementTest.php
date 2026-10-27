<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Enums\VehicleStatus;
use App\Models\Maintenance;
use App\Models\Notification;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FleetManagementTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function fleetStaff(): User
    {
        return User::factory()->fleetStaff()->create();
    }

    private function validVehiclePayload(array $overrides = []): array
    {
        return array_merge([
            'plate_number' => 'T123 ABC',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => '2021',
            'type' => 'pickup',
            'seats' => '5',
            'transmission' => 'manual',
            'fuel_type' => 'diesel',
            'colour' => 'White',
            'mileage' => '45000',
            'rate_per_day' => '250000',
            'status' => 'available',
            'features' => 'AC, Reverse camera, Roof rack',
        ], $overrides);
    }

    public function test_a_new_vehicle_lands_in_the_fleet_with_parsed_features(): void
    {
        $this->actingAs($this->fleetStaff())
            ->post('/admin/vehicles', $this->validVehiclePayload())
            ->assertSessionHas('status');

        $vehicle = Vehicle::query()->firstOrFail();
        $this->assertSame('T123 ABC', $vehicle->plate_number);
        $this->assertSame(VehicleStatus::Available, $vehicle->status);
        $this->assertSame(['AC', 'Reverse camera', 'Roof rack'], $vehicle->features);
    }

    public function test_duplicate_plate_numbers_are_refused(): void
    {
        $manager = $this->fleetStaff();
        Vehicle::factory()->create(['plate_number' => 'T123 ABC']);

        $this->actingAs($manager)
            ->post('/admin/vehicles', $this->validVehiclePayload())
            ->assertSessionHasErrors('plate_number');

        $this->assertDatabaseCount('vehicles', 1);
    }

    public function test_ride_hailing_status_cannot_be_set_by_hand(): void
    {
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($this->fleetStaff())
            ->put(route('admin.vehicles.status', $vehicle), ['status' => VehicleStatus::Rented->value])
            ->assertSessionHasErrors('status');

        $this->assertSame(VehicleStatus::Available, $vehicle->fresh()->status);
    }

    public function test_a_vehicle_on_an_open_rental_cannot_be_taken_off_the_road(): void
    {
        $rental = Rental::factory()->active()->create();
        $vehicle = $rental->vehicle;

        $this->actingAs($this->fleetStaff())
            ->put(route('admin.vehicles.status', $vehicle), ['status' => VehicleStatus::OutOfService->value])
            ->assertSessionHas('error');

        $this->assertSame(VehicleStatus::Available, $vehicle->fresh()->status);
    }

    public function test_a_vehicle_cannot_be_marked_available_while_maintenance_is_open(): void
    {
        $vehicle = Vehicle::factory()->underMaintenance()->create();
        Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => MaintenanceStatus::InProgress,
            'end_date' => null,
        ]);

        $this->actingAs($this->fleetStaff())
            ->put(route('admin.vehicles.status', $vehicle), ['status' => VehicleStatus::Available->value])
            ->assertSessionHas('error');

        $this->assertSame(VehicleStatus::Maintenance, $vehicle->fresh()->status);
    }

    public function test_taking_a_vehicle_out_of_service_alerts_the_fleet_team(): void
    {
        $manager = $this->fleetStaff();
        $other = User::factory()->admin()->create();
        User::factory()->finance()->create();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($manager)
            ->put(route('admin.vehicles.status', $vehicle), ['status' => VehicleStatus::OutOfService->value])
            ->assertSessionHas('status');

        $this->assertSame(VehicleStatus::OutOfService, $vehicle->fresh()->status);
        $this->assertDatabaseHas('notifications', ['user_id' => $manager->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $other->id]);
        // Accountants are not part of the fleet team.
        $this->assertSame(2, Notification::query()->count());
    }

    public function test_logging_maintenance_takes_the_vehicle_off_the_shelf(): void
    {
        $manager = $this->fleetStaff();
        $vehicle = Vehicle::factory()->available()->create();

        $this->actingAs($manager)->post('/admin/maintenances', [
            'vehicle_id' => $vehicle->id,
            'title' => 'Brake replacement',
            'start_date' => now()->toDateString(),
            'status' => MaintenanceStatus::InProgress->value,
        ])->assertSessionHas('status');

        $this->assertSame(VehicleStatus::Maintenance, $vehicle->fresh()->status);
        $this->assertDatabaseHas('maintenances', ['vehicle_id' => $vehicle->id, 'status' => 'in_progress']);
    }

    public function test_completing_the_last_maintenance_job_returns_the_vehicle_to_service(): void
    {
        $manager = $this->fleetStaff();
        $vehicle = Vehicle::factory()->underMaintenance()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => MaintenanceStatus::InProgress,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'cost' => null,
        ]);

        $this->actingAs($manager)->put(route('admin.maintenances.update', $maintenance), [
            'vehicle_id' => $vehicle->id,
            'title' => $maintenance->title,
            'start_date' => $maintenance->start_date->toDateString(),
            'end_date' => now()->toDateString(),
            'cost' => '120000',
            'status' => MaintenanceStatus::Completed->value,
        ])->assertSessionHas('status');

        $this->assertSame(MaintenanceStatus::Completed, $maintenance->fresh()->status);
        $this->assertSame(VehicleStatus::Available, $vehicle->fresh()->status);
    }

    public function test_a_vehicle_holding_a_booking_is_kept_from_deletion(): void
    {
        $manager = $this->fleetStaff();
        $vehicle = Vehicle::factory()->available()->create();
        Reservation::factory()->pending()->create(['vehicle_id' => $vehicle->id]);

        $this->actingAs($manager)
            ->delete(route('admin.vehicles.destroy', $vehicle))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($vehicle);

        $free = Vehicle::factory()->available()->create();
        $this->actingAs($manager)
            ->delete(route('admin.vehicles.destroy', $free))
            ->assertSessionHas('status');

        $this->assertSoftDeleted($free);
    }

    public function test_deleting_work_in_progress_is_refused(): void
    {
        $manager = $this->fleetStaff();
        $vehicle = Vehicle::factory()->available()->create();
        $maintenance = Maintenance::factory()->create([
            'vehicle_id' => $vehicle->id,
            'status' => MaintenanceStatus::InProgress,
        ]);

        $this->actingAs($manager)
            ->delete(route('admin.maintenances.destroy', $maintenance))
            ->assertSessionHas('error');

        $this->assertModelExists($maintenance);
    }
}

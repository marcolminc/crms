<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MaintenanceStatus;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateMaintenanceRequest;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Http\Request;

final class MaintenanceController extends Controller
{
    public function index(Request $request)
    {
        $maintenances = Maintenance::query()
            ->with('vehicle')
            ->when($request->filled('vehicle'), fn ($q) => $q->where('vehicle_id', $request->input('vehicle')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.maintenances.index', [
            'maintenances' => $maintenances,
            'vehicles' => $this->vehicleOptions(),
            'statuses' => MaintenanceStatus::options(),
        ]);
    }

    public function create(Request $request)
    {
        return view('admin.maintenances.create', [
            'maintenance' => null,
            'vehicles' => $this->vehicleOptions(),
            'statuses' => MaintenanceStatus::options(),
            'selectedVehicle' => $request->input('vehicle'),
        ]);
    }

    public function store(StoreMaintenanceRequest $request)
    {
        $maintenance = Maintenance::create($request->validated());
        $maintenance->load('vehicle');

        if ($maintenance->isOngoing() && $maintenance->end_date === null) {
            $maintenance->vehicle?->update(['status' => VehicleStatus::Maintenance]);
        }

        return redirect()->route('admin.maintenances.index')->with('status', 'Maintenance record created.');
    }

    public function edit(Maintenance $maintenance)
    {
        return view('admin.maintenances.edit', [
            'maintenance' => $maintenance,
            'vehicles' => $this->vehicleOptions(),
            'statuses' => MaintenanceStatus::options(),
            'selectedVehicle' => $maintenance->vehicle_id,
        ]);
    }

    public function update(UpdateMaintenanceRequest $request, Maintenance $maintenance)
    {
        $maintenance->update($request->validated());
        $maintenance->load('vehicle');

        if ($maintenance->isOngoing() && $maintenance->end_date === null) {
            $maintenance->vehicle?->update(['status' => VehicleStatus::Maintenance]);
        } elseif ($maintenance->status === MaintenanceStatus::Completed) {
            $this->releaseVehicle($maintenance->vehicle, $maintenance);
        }

        return redirect()->route('admin.maintenances.index')->with('status', 'Maintenance record updated.');
    }

    public function destroy(Maintenance $maintenance)
    {
        if ($maintenance->status === MaintenanceStatus::InProgress) {
            return redirect()->back()->with('error', 'Maintenance work in progress cannot be deleted. Mark it completed first.');
        }

        $wasOngoing = $maintenance->isOngoing();
        $vehicle = $maintenance->vehicle;
        $maintenance->delete();

        if ($wasOngoing) {
            $this->releaseVehicle($vehicle, $maintenance);
        }

        return redirect()->route('admin.maintenances.index')->with('status', 'Maintenance record deleted.');
    }

    private function releaseVehicle(?Vehicle $vehicle, Maintenance $maintenance): void
    {
        if ($vehicle === null || $vehicle->status !== VehicleStatus::Maintenance) {
            return;
        }

        $otherOngoing = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereKeyNot($maintenance->id)
            ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
            ->exists();
        if ($otherOngoing) {
            return;
        }

        if (Rental::query()->open()->where('vehicle_id', $vehicle->id)->exists()) {
            return;
        }

        if (Reservation::query()->active()->where('vehicle_id', $vehicle->id)->exists()) {
            return;
        }

        $vehicle->update(['status' => VehicleStatus::Available]);
    }

    private function vehicleOptions()
    {
        return Vehicle::query()->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model']);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vehicle\StoreVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleRequest;
use App\Http\Requests\Vehicle\UpdateVehicleStatusRequest;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class VehicleController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request)
    {
        $vehicles = Vehicle::query()
            ->search($request->input('q'))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('min_rate'), fn ($q) => $q->where('rate_per_day', '>=', (float) $request->input('min_rate')))
            ->when($request->filled('max_rate'), fn ($q) => $q->where('rate_per_day', '<=', (float) $request->input('max_rate')))
            ->orderBy('plate_number')
            ->paginate(15)
            ->withQueryString();

        $counts = Vehicle::query()
            ->selectRaw('status, count(*) aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return view('admin.vehicles.index', [
            'vehicles' => $vehicles,
            'counts' => $counts,
            'types' => VehicleType::options(),
            'statuses' => VehicleStatus::options(),
        ]);
    }

    public function create()
    {
        return view('admin.vehicles.create', [
            'vehicle' => null,
            'types' => VehicleType::options(),
            'statuses' => VehicleStatus::options(),
        ]);
    }

    public function store(StoreVehicleRequest $request, AuditLogger $audit)
    {
        $data = $request->validated();
        unset($data['photos'], $data['remove_images']);
        $data['features'] = $this->parseFeatures($data['features'] ?? null);
        $data['status'] = $data['status'] ?? VehicleStatus::Available->value;

        $images = [];
        foreach (array_keys(Vehicle::SLOTS) as $slot) {
            $file = $request->file("photos.$slot");
            if ($file instanceof UploadedFile) {
                $images[$slot] = $file->store('vehicles', 'public');
            }
        }
        $data['images'] = $images === [] ? null : $images;

        Vehicle::create($data);

        $audit->log('vehicle.created', 'Added vehicle '.$data['plate_number'].' to the fleet.', null, ['plate_number' => $data['plate_number'] ?? null, 'rate_per_day' => $data['rate_per_day'] ?? null]);

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle added to the fleet.');
    }

    public function edit(Vehicle $vehicle)
    {
        return view('admin.vehicles.edit', [
            'vehicle' => $vehicle,
            'types' => VehicleType::options(),
            'statuses' => VehicleStatus::options(),
        ]);
    }

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle, AuditLogger $audit)
    {
        $data = $request->validated();
        unset($data['photos'], $data['remove_images']);
        $data['features'] = $this->parseFeatures($data['features'] ?? null);

        $map = $vehicle->photoMap();
        $removals = [];

        // Ticked tiles drop their slot; anything unknown is ignored.
        foreach ((array) $request->input('remove_images', []) as $path) {
            $slot = is_string($path) ? array_search($path, $map, true) : false;
            if ($slot !== false) {
                unset($map[$slot]);
                $removals[] = $path;
            }
        }

        // A fresh file in a filled slot replaces the old photo outright.
        foreach (array_keys(Vehicle::SLOTS) as $slot) {
            $file = $request->file("photos.$slot");
            if ($file instanceof UploadedFile) {
                if (isset($map[$slot])) {
                    $removals[] = $map[$slot];
                }
                $map[$slot] = $file->store('vehicles', 'public');
            }
        }

        $data['images'] = $map === [] ? null : $map;

        $previousRate = (float) $vehicle->rate_per_day;

        $vehicle->update($data);
        $this->deletePhotos($removals);

        $change = (float) $vehicle->rate_per_day !== $previousRate
            ? " (daily rate changed from {$previousRate} to {$vehicle->rate_per_day} TZS)"
            : '';
        $audit->log('vehicle.updated', "Updated vehicle {$vehicle->plate_number}.".$change, $vehicle, ['rate_per_day' => (float) $vehicle->rate_per_day]);

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle, AuditLogger $audit)
    {
        $openRental = Rental::query()->open()->where('vehicle_id', $vehicle->id)->exists();
        if ($openRental) {
            return redirect()->back()->with('error', "Vehicle {$vehicle->plate_number} cannot be deleted: it has an open rental.");
        }

        $liveReservation = Reservation::query()->active()->where('vehicle_id', $vehicle->id)->exists();
        if ($liveReservation) {
            return redirect()->back()->with('error', "Vehicle {$vehicle->plate_number} cannot be deleted: it has a live reservation.");
        }

        $vehicle->delete();

        $audit->log('vehicle.deleted', "Removed vehicle {$vehicle->plate_number} from the fleet.", null, ['plate_number' => $vehicle->plate_number]);

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle removed from the fleet.');
    }

    public function updateStatus(UpdateVehicleStatusRequest $request, Vehicle $vehicle, AuditLogger $audit)
    {
        $new = VehicleStatus::from($request->validated('status'));
        $previous = $vehicle->status;

        if ($new === $previous) {
            return redirect()->back()->with('status', "Vehicle {$vehicle->plate_number} is already {$previous->label()}.");
        }

        if (in_array($new, [VehicleStatus::Maintenance, VehicleStatus::OutOfService], true)) {
            $openRental = Rental::query()->open()->where('vehicle_id', $vehicle->id)->exists();
            if ($openRental) {
                return redirect()->back()->with('error', "Vehicle {$vehicle->plate_number} is on an open rental and cannot be marked {$new->label()}.");
            }
        }

        if ($new === VehicleStatus::Available) {
            $unfinished = Maintenance::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->exists();
            if ($unfinished) {
                return redirect()->back()->with('error', "Vehicle {$vehicle->plate_number} still has unfinished maintenance and cannot be marked Available.");
            }
        }

        $vehicle->update(['status' => $new]);

        $audit->log('vehicle.status_changed', "Changed vehicle {$vehicle->plate_number} status from {$previous->label()} to {$new->label()}.", $vehicle, ['from' => $previous->value, 'to' => $new->value]);

        if ($new === VehicleStatus::OutOfService) {
            $this->notifications->forUsers(
                $this->notifications->fleetTeam(),
                'Vehicle taken out of service',
                "Vehicle {$vehicle->display_name} was marked Out of Service.",
                route('admin.vehicles.index', ['q' => $vehicle->plate_number]),
            );
        }

        return redirect()->back()->with('status', "Vehicle {$vehicle->plate_number} is now {$new->label()}.");
    }

    private function parseFeatures(?string $features): ?array
    {
        if ($features === null || trim($features) === '') {
            return null;
        }

        $items = array_filter(array_map('trim', explode(',', $features)), fn ($item) => $item !== '');

        return $items === [] ? null : array_values($items);
    }

    /**
     * Only ever removes files from our own vehicles folder on the public disk;
     * legacy free-text paths are simply dropped from the list.
     */
    private function deletePhotos(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && str_starts_with($path, 'vehicles/') && ! str_contains($path, '..')) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}

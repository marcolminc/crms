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

    public function store(StoreVehicleRequest $request)
    {
        $data = $request->validated();
        unset($data['photos'], $data['remove_images']);
        $data['features'] = $this->parseFeatures($data['features'] ?? null);
        $data['status'] = $data['status'] ?? VehicleStatus::Available->value;

        $uploaded = array_map(
            fn (UploadedFile $file): string => $file->store('vehicles', 'public'),
            $this->uploadedPhotos($request),
        );
        $data['images'] = $uploaded === [] ? null : array_values($uploaded);

        Vehicle::create($data);

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

    public function update(UpdateVehicleRequest $request, Vehicle $vehicle)
    {
        $data = $request->validated();
        unset($data['photos'], $data['remove_images']);
        $data['features'] = $this->parseFeatures($data['features'] ?? null);

        $existing = $vehicle->gallery();
        $removals = array_values(array_filter(
            array_map(fn ($p): ?string => is_string($p) ? $p : null, (array) $request->input('remove_images', [])),
            fn (string $path): bool => in_array($path, $existing, true),
        ));
        $kept = array_values(array_diff($existing, $removals));
        $files = $this->uploadedPhotos($request);

        if (count($kept) + count($files) > Vehicle::MAX_PHOTOS) {
            return redirect()->back()->withInput()->with(
                'error',
                'A vehicle carries at most '.Vehicle::MAX_PHOTOS.' photos — remove some before adding more.',
            );
        }

        $uploaded = array_map(fn (UploadedFile $file): string => $file->store('vehicles', 'public'), $files);
        $gallery = array_merge($kept, $uploaded);
        $data['images'] = $gallery === [] ? null : array_values($gallery);

        $vehicle->update($data);
        $this->deletePhotos($removals);

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle)
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

        return redirect()->route('admin.vehicles.index')->with('status', 'Vehicle removed from the fleet.');
    }

    public function updateStatus(UpdateVehicleStatusRequest $request, Vehicle $vehicle)
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
     * @return list<UploadedFile>
     */
    private function uploadedPhotos(StoreVehicleRequest $request): array
    {
        $files = $request->file('photos') ?? [];

        return array_values(array_filter(
            is_array($files) ? $files : [$files],
            fn ($file): bool => $file instanceof UploadedFile && $file->isValid(),
        ));
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

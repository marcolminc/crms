<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Http\Requests\Driver\StoreTripRequest;
use App\Models\Rental;
use Illuminate\Http\Request;

/**
 * UC24: the chauffeur views one of their assignments and logs daily trips
 * (start / end, odometer and any incident) against it.
 */
final class RentalController extends Controller
{
    public function show(Request $request, Rental $rental)
    {
        $this->ensureAssignment($request, $rental);

        $rental->load(['vehicle', 'customer.user']);

        return view('driver.rental', [
            'rental' => $rental,
            'trips' => $rental->trips()->with('driver')->get(),
        ]);
    }

    public function storeTrip(StoreTripRequest $request, Rental $rental)
    {
        $this->ensureAssignment($request, $rental);

        $data = $request->validated();

        $rental->trips()->create([
            'driver_id' => $request->user()->id,
            'trip_date' => $data['trip_date'],
            'started_at' => $data['started_at'] ?? null,
            'ended_at' => $data['ended_at'] ?? null,
            'odometer_start' => $data['odometer_start'] ?? null,
            'odometer_end' => $data['odometer_end'] ?? null,
            'incident_notes' => $data['incident_notes'] ?? null,
        ]);

        return back()->with('status', 'Trip logged for '.\Carbon\Carbon::createFromFormat('Y-m-d', $data['trip_date'])->format('d M Y').'.');
    }

    /**
     * A driver may only open rentals assigned to them.
     */
    private function ensureAssignment(Request $request, Rental $rental): void
    {
        abort_unless($rental->driver_id === $request->user()->id, 404);
    }
}

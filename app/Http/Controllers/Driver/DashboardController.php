<?php

namespace App\Http\Controllers\Driver;

use App\Enums\RentalStatus;
use App\Http\Controllers\Controller;
use App\Models\Rental;
use Illuminate\Http\Request;

/**
 * UC24: the chauffeur's own workspace - the hires they are driving and their
 * on-duty / off-duty switch.
 */
final class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $driver = $request->user();

        $assignments = Rental::query()
            ->where('driver_id', $driver->id)
            ->with(['vehicle', 'customer'])
            ->orderByRaw(
                'CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END',
                [RentalStatus::Active->value, RentalStatus::Overdue->value]
            )
            ->latest('start_date')
            ->limit(20)
            ->get();

        return view('driver.dashboard', [
            'driver' => $driver,
            'assignments' => $assignments,
            'trips' => $driver->trips()->with('rental.vehicle')->limit(10)->get(),
        ]);
    }

    public function toggleAvailability(Request $request)
    {
        $driver = $request->user();
        $driver->update(['is_on_duty' => ! $driver->is_on_duty]);

        return back()->with('status', $driver->is_on_duty
            ? 'You are marked on duty and available for assignment.'
            : 'You are now marked off duty.');
    }
}

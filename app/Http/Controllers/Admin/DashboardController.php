<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    private const COUNTED_STATUSES = [
        PaymentStatus::Pending->value,
        PaymentStatus::Partial->value,
        PaymentStatus::Paid->value,
    ];

    public function index(Request $request)
    {
        $today = today();
        $user = $request->user();

        $openRentals = Rental::query()
            ->open()
            ->with(['vehicle', 'customer.user'])
            ->orderBy('end_date')
            ->get();

        $collectedOnOpen = (float) Payment::query()
            ->whereIn('status', self::COUNTED_STATUSES)
            ->whereIn('rental_id', $openRentals->pluck('id'))
            ->sum('amount');

        return view('admin.dashboard', [
            'user' => $user,
            'vehicleCounts' => Vehicle::query()
                ->selectRaw('status, count(*) aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'pendingReservations' => Reservation::query()->where('status', ReservationStatus::Pending)->count(),
            'confirmedReservations' => Reservation::query()->where('status', ReservationStatus::Confirmed)->count(),
            'openRentals' => $openRentals,
            'overdueRentals' => $openRentals->filter(
                fn (Rental $rental) => $rental->status === RentalStatus::Active && $rental->end_date->lt($today)
            ),
            'startingToday' => Rental::query()
                ->whereDate('start_date', $today)
                ->whereIn('status', [RentalStatus::Active->value, RentalStatus::Overdue->value])
                ->with(['vehicle', 'customer.user'])
                ->orderBy('end_date')
                ->get(),
            'dueBackToday' => $openRentals->filter(
                fn (Rental $rental) => $rental->status === RentalStatus::Active && $rental->end_date->isSameDay($today)
            )->values(),
            'monthRevenue' => (float) Payment::query()
                ->whereIn('status', self::COUNTED_STATUSES)
                ->whereBetween('paid_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->sum('amount'),
            'outstanding' => max(0, (float) $openRentals->sum('total_amount') - $collectedOnOpen),
            'alerts' => $user?->notifications()->limit(5)->get() ?? collect(),
        ]);
    }
}

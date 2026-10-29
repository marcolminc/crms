<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
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

        // readme #26: the administrator runs the system - accounts, roles,
        // fleet and customer oversight - not the day-to-day hire desk. Give
        // them a management dashboard; every other staff role keeps the
        // operational queues (pick-ups, returns, overdue, takings).
        if ($user?->hasRole(UserRole::Admin)) {
            return view('admin.dashboard', $this->adminData($user, $today));
        }

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
            // Confirmed bookings waiting to be turned into a hire today (or a
            // pick-up day that has already slipped). Mirrors the "Convert to
            // rental" gate on the reservation screen.
            'pickupsDue' => Reservation::query()
                ->where('status', ReservationStatus::Confirmed)
                ->whereDoesntHave('rental')
                ->whereDate('start_date', '<=', $today)
                ->whereDate('end_date', '>=', $today)
                ->with(['vehicle', 'customer.user'])
                ->orderBy('start_date')
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

    /**
     * Administration-relevant metrics only: who can use the system, how the
     * fleet and customer base look at a high level, and the reports to run.
     *
     * @return array<string,mixed>
     */
    private function adminData(User $user, $today): array
    {
        return [
            'user' => $user,
            'isAdmin' => true,
            'totalUsers' => User::query()->count(),
            'activeUsers' => User::query()->where('is_active', true)->count(),
            'inactiveUsers' => User::query()->where('is_active', false)->count(),
            'usersByRole' => User::query()
                ->selectRaw('role, count(*) aggregate')
                ->groupBy('role')
                ->pluck('aggregate', 'role'),
            'vehicleCounts' => Vehicle::query()
                ->selectRaw('status, count(*) aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status'),
            'totalVehicles' => Vehicle::query()->count(),
            'totalCustomers' => Customer::query()->count(),
            'newCustomersThisMonth' => Customer::query()
                ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                ->count(),
            'alerts' => $user->notifications()->limit(5)->get(),
        ];
    }
}

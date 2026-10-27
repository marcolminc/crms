<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MaintenanceStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\CancelRentalRequest;
use App\Http\Requests\Rental\StoreRentalRequest;
use App\Http\Requests\Rental\UpdateRentalRequest;
use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RentalController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request)
    {
        $base = Rental::query()
            ->when($request->filled('status'), function ($q) use ($request) {
                if (array_key_exists($request->input('status'), RentalStatus::options())) {
                    $q->where('status', $request->input('status'));
                }
            })
            ->when($request->filled('from'), fn ($q) => $q->where('end_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('start_date', '<=', $request->input('to')))
            ->when($request->boolean('overdue'), function ($q) {
                $q->where(function ($inner) {
                    $inner->where('status', RentalStatus::Overdue)
                        ->orWhere(function ($j) {
                            $j->where('status', RentalStatus::Active)
                                ->whereDate('end_date', '<', Carbon::today()->toDateString());
                        });
                });
            })
            ->when($request->filled('q'), function ($q) use ($request) {
                $like = '%'.$request->input('q').'%';
                $q->where(function ($inner) use ($like) {
                    $inner->whereHas('customer.user', fn ($u) => $u->where('name', 'like', $like))
                        ->orWhereHas('vehicle', function ($v) use ($like) {
                            $v->where('plate_number', 'like', $like)
                                ->orWhere('make', 'like', $like)
                                ->orWhere('model', 'like', $like);
                        });
                });
            });

        $rentals = (clone $base)
            ->with(['customer.user', 'vehicle', 'payments'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $rows = (clone $base)->get(['id', 'total_amount']);
        $paidByRental = Payment::query()
            ->whereIn('rental_id', $rows->pluck('id'))
            ->whereIn('status', ['pending', 'partial', 'paid'])
            ->selectRaw('rental_id, sum(amount) collected')
            ->groupBy('rental_id')
            ->pluck('collected', 'rental_id');

        $collected = (float) $paidByRental->sum();
        $outstanding = 0.0;
        foreach ($rows as $row) {
            $outstanding += max(0, (float) $row->total_amount - (float) ($paidByRental[$row->id] ?? 0));
        }

        return view('admin.rentals.index', [
            'rentals' => $rentals,
            'statuses' => RentalStatus::options(),
            'filteredTotal' => (float) $rows->sum('total_amount'),
            'collected' => $collected,
            'outstanding' => $outstanding,
        ]);
    }

    public function create(Request $request)
    {
        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));
        $selectedCustomer = $request->integer('customer') ?: null;

        $reservation = null;
        if ($request->integer('reservation')) {
            $candidate = Reservation::query()->with('customer.user')->find($request->integer('reservation'));
            if ($candidate
                && $candidate->status === ReservationStatus::Confirmed
                && $candidate->rental()->doesntExist()
                && $candidate->customer_id !== null
                && $candidate->end_date->gte(Carbon::today())) {
                $reservation = $candidate;
                $from = $from ?? $candidate->start_date->toDateString();
                $to = $to ?? $candidate->end_date->toDateString();
                $selectedCustomer = $selectedCustomer ?? $candidate->customer_id;
            }
        }

        $vehicles = $this->offerVehicles($from, $to, $reservation?->id);

        return view('admin.rentals.create', [
            'reservation' => $reservation,
            'customers' => Customer::query()->with('user')->get()->sortBy->display_name,
            'vehicles' => $vehicles,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'selectedCustomer' => $selectedCustomer,
            'selectedVehicle' => $request->integer('vehicle') ?: ($reservation?->vehicle_id),
        ]);
    }

    public function store(StoreRentalRequest $request)
    {
        $data = $request->validated();
        $vehicle = Vehicle::query()->findOrFail($data['vehicle']);
        $start = Carbon::createFromFormat('Y-m-d', $data['start_date'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end_date'])->startOfDay();
        $reservation = isset($data['reservation'])
            ? Reservation::query()->with('customer')->find($data['reservation'])
            : null;

        $rental = DB::transaction(function () use ($data, $vehicle, $start, $end, $reservation) {
            $rental = new Rental([
                'reservation_id' => $reservation?->id,
                'customer_id' => $data['customer'],
                'vehicle_id' => $vehicle->id,
                'staff_id' => auth()->id(),
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'days' => (int) $start->diffInDays($end) + 1,
                'rate_per_day' => $vehicle->rate_per_day,
                'additional_charges' => $data['additional_charges'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'pickup_location' => $data['pickup_location'] ?? null,
                'dropoff_location' => $data['dropoff_location'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => RentalStatus::Active,
            ]);
            $rental->recalculate();
            $rental->save();

            $reservation?->update(['status' => ReservationStatus::Completed]);
            $vehicle->update(['status' => VehicleStatus::Rented]);

            $this->notifications->forCustomer(
                $rental->customer ?? $reservation?->customer,
                'Rental started',
                'Your rental of '.$vehicle->display_name.' has begun ('
                    .$start->format('d M Y').' to '.$end->format('d M Y').').',
                route('customer.rentals.index'),
                'rental_started'
            );

            return $rental;
        });

        return redirect()
            ->route('admin.rentals.show', $rental)
            ->with('status', 'Rental created and the vehicle is now on hire.');
    }

    public function show(Rental $rental)
    {
        $rental->load(['customer.user', 'vehicle', 'payments', 'vehicleReturn', 'reservation']);

        return view('admin.rentals.show', [
            'rental' => $rental,
        ]);
    }

    public function edit(Rental $rental)
    {
        $rental->load(['customer.user', 'vehicle']);

        return view('admin.rentals.edit', [
            'rental' => $rental,
        ]);
    }

    public function update(UpdateRentalRequest $request, Rental $rental)
    {
        $data = $request->validated();
        $start = $rental->start_date->copy()->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end_date'])->startOfDay();

        $rental->end_date = $end->toDateString();
        $rental->days = (int) $start->diffInDays($end) + 1;
        $rental->additional_charges = $data['additional_charges'] ?? 0;
        $rental->discount = $data['discount'] ?? 0;
        $rental->pickup_location = $data['pickup_location'] ?? null;
        $rental->dropoff_location = $data['dropoff_location'] ?? null;
        $rental->notes = $data['notes'] ?? null;
        $rental->recalculate();
        $rental->save();

        return redirect()->route('admin.rentals.show', $rental)->with('status', 'Rental updated.');
    }

    public function cancel(CancelRentalRequest $request, Rental $rental)
    {
        if (! in_array($rental->status, [RentalStatus::Active, RentalStatus::Overdue], true)) {
            return redirect()->back()->with('error', 'Only active or overdue rentals can be cancelled.');
        }

        $reason = $request->validated('reason');
        $notes = $rental->notes ? trim($rental->notes) : '';
        $rental->update([
            'status' => RentalStatus::Cancelled,
            'notes' => $notes === '' ? 'Cancelled: '.$reason : $notes."\nCancelled: ".$reason,
        ]);

        $vehicle = $rental->vehicle;
        if ($vehicle) {
            $ongoing = Maintenance::query()
                ->where('vehicle_id', $vehicle->id)
                ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
                ->exists();
            $vehicle->update(['status' => $ongoing ? VehicleStatus::Maintenance : VehicleStatus::Available]);
        }

        $this->notifications->forCustomer(
            $rental->customer,
            'Rental cancelled',
            'Your rental of '.($vehicle->display_name ?? 'the vehicle').' was cancelled. Reason: '.$reason
                .' Any applicable refund will be handled separately.',
            route('customer.rentals.index'),
            'rental_cancelled'
        );

        return redirect()->back()->with('status', 'Rental cancelled and the customer notified.');
    }

    private function offerVehicles(?string $from, ?string $to, ?int $excludeReservationId)
    {
        $query = Vehicle::query()
            ->whereIn('status', [VehicleStatus::Available->value, VehicleStatus::Reserved->value])
            ->orderBy('plate_number');

        if ($from !== null && $to !== null) {
            $query->whereDoesntHave('reservations', function ($q) use ($from, $to, $excludeReservationId) {
                $q->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                    ->when($excludeReservationId, fn ($inner) => $inner->whereKeyNot($excludeReservationId))
                    ->where('start_date', '<=', $to)
                    ->where('end_date', '>=', $from);
            })->whereDoesntHave('rentals', function ($q) use ($from, $to) {
                $q->whereIn('status', [RentalStatus::Active->value, RentalStatus::Overdue->value])
                    ->where('start_date', '<=', $to)
                    ->where('end_date', '>=', $from);
            })->whereDoesntHave('maintenances', function ($q) use ($from, $to) {
                $q->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
                    ->where('start_date', '<=', $to)
                    ->where(function ($inner) use ($from) {
                        $inner->whereNull('end_date')->orWhere('end_date', '>=', $from);
                    });
            });
        }

        return $query->get();
    }

    private function dateOrNull(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}

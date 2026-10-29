<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalType;
use App\Enums\MaintenanceStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rental\CancelRentalRequest;
use App\Http\Requests\Rental\AssignDriverRequest;
use App\Http\Requests\Rental\StoreRentalRequest;
use App\Http\Requests\Rental\UpdateRentalRequest;
use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\ChargeApprovalService;
use App\Services\NotificationService;
use App\Support\SlotPhotos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RentalController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * FR9: the settlement invoice. Derived from the rental, its payments and its
     * fines; nothing here is stored separately, so the document always reflects
     * the current money columns on the hire.
     */
    public function invoice(Rental $rental)
    {
        $rental->load([
            'vehicle',
            'customer.user',
            'payments.recordedBy',
            'fines',
            'vehicleReturn',
        ]);

        return view('admin.rentals.invoice', [
            'rental' => $rental,
            'paid' => $rental->paidAmount(),
            'balance' => $rental->balanceDue(),
            'surplus' => max(0, $rental->paidAmount() - (float) $rental->total_amount),
        ]);
    }

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

        // The handover odometer should open on the last reading we hold for the
        // vehicle being hired, so the clerk only corrects it, never retypes it.
        $selectedVehicleId = $request->integer('vehicle') ?: ($reservation?->vehicle_id);
        $selectedMileage = $reservation?->vehicle?->mileage
            ?? $vehicles->firstWhere('id', $selectedVehicleId)?->mileage;

        return view('admin.rentals.create', [
            'reservation' => $reservation,
            'customers' => Customer::query()->with('user')->get()->sortBy->display_name,
            'vehicles' => $vehicles,
            'from' => $from ?? '',
            'to' => $to ?? '',
            'selectedCustomer' => $selectedCustomer,
            'selectedVehicle' => $selectedVehicleId ?: null,
            'selectedMileage' => $selectedMileage,
        ]);
    }

    public function store(StoreRentalRequest $request, AuditLogger $audit, ChargeApprovalService $approvals)
    {
        $data = $request->validated();
        $vehicle = Vehicle::query()->findOrFail($data['vehicle']);
        $start = Carbon::createFromFormat('Y-m-d', $data['start_date'])->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end_date'])->startOfDay();
        $reservation = isset($data['reservation'])
            ? Reservation::query()->with('customer')->find($data['reservation'])
            : null;

        // Handover evidence: angle photos taken as the customer drives away.
        $pickupPhotos = SlotPhotos::store($request, 'pickup_photos', 'rentals');
        $odometer = isset($data['odometer_at_pickup']) ? (int) $data['odometer_at_pickup'] : null;

        $rental = DB::transaction(function () use ($data, $vehicle, $start, $end, $reservation, $pickupPhotos, $odometer) {
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
                'odometer_at_pickup' => $odometer,
                'fuel_at_pickup' => $data['fuel_at_pickup'] ?? null,
                'pickup_photos' => $pickupPhotos === [] ? null : $pickupPhotos,
                'notes' => $data['notes'] ?? null,
                'status' => RentalStatus::Active,
            ]);
            $rental->recalculate();
            $rental->save();

            $reservation?->update(['status' => ReservationStatus::Completed]);
            $vehicle->update(array_filter([
                'status' => VehicleStatus::Rented,
                'mileage' => $odometer,
            ], fn ($value) => $value !== null));

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

        $audit->log('rental.created', 'Started rental #'.$rental->id.' of '.$vehicle->display_name.' for '.number_format((float) $rental->total_amount, 0).' TZS.', $rental, [
            'discount' => (float) $rental->discount,
            'additional_charges' => (float) $rental->additional_charges,
            'total_amount' => (float) $rental->total_amount,
        ]);

        // UC14: material discounts and surcharges are escalated to a manager.
        $approvals->maybeRequest($rental, ApprovalType::Discount, (float) $rental->discount, $data['notes'] ?? null);
        $approvals->maybeRequest($rental, ApprovalType::Damage, (float) $rental->additional_charges, $data['notes'] ?? null);

        return redirect()
            ->route('admin.rentals.show', $rental)
            ->with('status', 'Rental created and the vehicle is now on hire.');
    }

    public function show(Rental $rental)
    {
        $rental->load(['customer.user', 'vehicle', 'payments', 'vehicleReturn', 'reservation', 'driver', 'fines']);

        return view('admin.rentals.show', [
            'rental' => $rental,
            'availableDrivers' => $this->availableDrivers($rental),
            'driverRate' => (float) config('crms.driver_rate_per_day'),
            'canAssignDriver' => auth()->user()?->hasRole(
                \App\Enums\UserRole::CounterAgent,
                \App\Enums\UserRole::BranchManager,
                \App\Enums\UserRole::Admin
            ) ?? false,
        ]);
    }

    public function edit(Rental $rental)
    {
        $rental->load(['customer.user', 'vehicle']);

        return view('admin.rentals.edit', [
            'rental' => $rental,
        ]);
    }

    public function update(UpdateRentalRequest $request, Rental $rental, AuditLogger $audit, ChargeApprovalService $approvals)
    {
        $data = $request->validated();
        $start = $rental->start_date->copy()->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $data['end_date'])->startOfDay();

        $previousDiscount = (float) $rental->discount;
        $previousCharges = (float) $rental->additional_charges;

        $rental->end_date = $end->toDateString();
        $rental->days = (int) $start->diffInDays($end) + 1;
        $rental->additional_charges = $data['additional_charges'] ?? 0;
        $rental->discount = $data['discount'] ?? 0;
        $rental->pickup_location = $data['pickup_location'] ?? null;
        $rental->dropoff_location = $data['dropoff_location'] ?? null;
        $rental->notes = $data['notes'] ?? null;
        $rental->recalculate();
        $rental->save();

        $newDiscount = (float) $rental->discount;
        $newCharges = (float) $rental->additional_charges;

        if ($newDiscount !== $previousDiscount || $newCharges !== $previousCharges) {
            $audit->log('rental.updated', 'Updated rental #'.$rental->id.' money terms.', $rental, [
                'discount_before' => $previousDiscount,
                'discount_after' => $newDiscount,
                'charges_before' => $previousCharges,
                'charges_after' => $newCharges,
                'total_amount' => (float) $rental->total_amount,
            ]);
        } else {
            $audit->log('rental.updated', 'Updated rental #'.$rental->id.'.', $rental);
        }

        // UC14: escalate the changed discount / surcharge when it is material.
        if ($newDiscount !== $previousDiscount) {
            $approvals->maybeRequest($rental, ApprovalType::Discount, $newDiscount, $data['notes'] ?? null);
        }
        if ($newCharges !== $previousCharges) {
            $approvals->maybeRequest($rental, ApprovalType::Damage, $newCharges, $data['notes'] ?? null);
        }

        return redirect()->route('admin.rentals.show', $rental)->with('status', 'Rental updated.');
    }

    public function cancel(CancelRentalRequest $request, Rental $rental, AuditLogger $audit)
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

        $audit->log('rental.cancelled', 'Cancelled rental #'.$rental->id.'. Reason: '.$reason, $rental, ['reason' => $reason]);

        return redirect()->back()->with('status', 'Rental cancelled and the customer notified.');
    }

    /**
     * UC23: attach a free chauffeur to the hire (or clear it). The driver is
     * priced at the flat daily chauffeur rate for the length of the hire, and
     * both the driver and the customer are told.
     */
    public function assignDriver(AssignDriverRequest $request, Rental $rental, AuditLogger $audit)
    {
        if (! in_array($rental->status, [RentalStatus::Active, RentalStatus::Overdue], true)) {
            return back()->with('error', 'A driver can only be assigned to an active or overdue rental.');
        }

        $driverId = $request->validated('driver');
        $driverId = $driverId === null ? null : (int) $driverId;

        // Clearing the assignment.
        if ($driverId === null) {
            $previous = $rental->driver;
            $rental->update(['driver_id' => null, 'driver_fee' => 0]);
            $rental->recalculate();
            $rental->save();

            $audit->log('rental.driver_cleared', 'Removed chauffeur from rental #'.$rental->id.'.', $rental);

            return back()->with('status', 'Chauffeur removed'.($previous ? ' ('.$previous->name.').' : '.'));
        }

        if ($this->driverBusyFor($rental, $driverId)) {
            return back()->withInput()->withErrors([
                'driver' => 'That driver is already committed to another hire over these dates.',
            ]);
        }

        $driver = User::query()->findOrFail($driverId);
        $rental->driver_id = $driver->id;
        $rental->driver_fee = (float) config('crms.driver_rate_per_day') * $rental->days;
        $rental->recalculate();
        $rental->save();

        $vehicleName = $rental->vehicle?->display_name ?? 'the vehicle';
        $period = $rental->start_date->format('d M Y').' - '.$rental->end_date->format('d M Y');

        $this->notifications->forUsers(
            [$driver->id],
            'You have been assigned to a hire',
            'You are the chauffeur for '.($rental->customer?->display_name ?? 'a customer')
                .' driving '.$vehicleName.' from '.$period.'. Please log your daily trips.',
            route('driver.rentals.show', $rental),
            'driver_assignment'
        );

        $this->notifications->forCustomer(
            $rental->customer,
            'A driver has been assigned',
            'Our chauffeur '.$driver->name.' will drive you for '.$vehicleName
                .' ('.$period.'). A chauffeur fee of '.number_format($rental->driver_fee, 0).' TZS has been added.',
            route('customer.rentals.show', $rental),
            'driver_assigned'
        );

        $audit->log(
            'rental.driver_assigned',
            'Assigned chauffeur '.$driver->name.' to rental #'.$rental->id.' ('.number_format($rental->driver_fee, 0).' TZS).',
            $rental,
            ['driver_id' => $driver->id, 'driver_fee' => (float) $rental->driver_fee]
        );

        return back()->with('status', $driver->name.' has been assigned as the chauffeur.');
    }

    /**
     * FR15 / UC23: the drivers who are free for this hire's dates - an active
     * chauffeur not already driving another open rental that overlaps.
     */
    public function availableDrivers(Rental $rental)
    {
        return User::query()
            ->where('role', UserRole::Driver)
            ->where('is_active', true)
            ->whereNotIn('id', function ($query) use ($rental) {
                $query->select('driver_id')
                    ->from('rentals')
                    ->where('id', '!=', $rental->id)
                    ->whereNotNull('driver_id')
                    ->whereIn('status', [RentalStatus::Active->value, RentalStatus::Overdue->value])
                    ->where('start_date', '<=', $rental->end_date->toDateString())
                    ->where('end_date', '>=', $rental->start_date->toDateString());
            })
            ->orderByDesc('is_on_duty')
            ->orderBy('name')
            ->get();
    }

    private function driverBusyFor(Rental $rental, int $driverId): bool
    {
        return Rental::query()
            ->where('driver_id', $driverId)
            ->whereKeyNot($rental->id)
            ->whereIn('status', [RentalStatus::Active, RentalStatus::Overdue])
            ->where('start_date', '<=', $rental->end_date->toDateString())
            ->where('end_date', '>=', $rental->start_date->toDateString())
            ->exists();
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

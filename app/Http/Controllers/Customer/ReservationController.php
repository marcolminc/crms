<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\NotificationService;
use App\Services\OnlinePaymentService;
use Carbon\Carbon;
use Illuminate\Http\Request;

final class ReservationController extends Controller
{
    public function index(Request $request)
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return $this->missingProfile();
        }

        $status = $request->query('status');
        $validStatus = is_string($status) && array_key_exists($status, ReservationStatus::options());

        $reservations = $customer->reservations()
            ->with('vehicle')
            ->when($validStatus, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('customer.reservations.index', [
            'reservations' => $reservations,
            'statuses' => ReservationStatus::options(),
            'currentStatus' => $validStatus ? $status : '',
        ]);
    }

    public function create(Request $request)
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return $this->missingProfile();
        }

        $from = $this->dateOrNull($request->query('from'));
        $to = $this->dateOrNull($request->query('to'));
        $selected = (int) $request->query('vehicle') ?: null;

        $vehicles = Vehicle::query()->orderBy('rate_per_day');
        if ($from !== null && $to !== null) {
            $vehicles->availableBetween($from, $to);
        } else {
            $vehicles->where('status', VehicleStatus::Available);
        }

        return view('customer.reservations.create', [
            'vehicles' => $vehicles->get(),
            'selectedVehicle' => $selected,
            'from' => $from ?? '',
            'to' => $to ?? '',
        ]);
    }

    public function store(StoreReservationRequest $request, NotificationService $notifications)
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return $this->missingProfile();
        }

        $vehicle = Vehicle::query()->findOrFail($request->integer('vehicle'));
        $start = Carbon::createFromFormat('Y-m-d', $request->validated('start_date'))->startOfDay();
        $end = Carbon::createFromFormat('Y-m-d', $request->validated('end_date'))->startOfDay();
        $days = (int) $start->diffInDays($end) + 1;

        $reservation = $customer->reservations()->create([
            'vehicle_id' => $vehicle->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'estimated_total' => (float) $vehicle->rate_per_day * $days,
            'status' => ReservationStatus::Pending,
            'notes' => $request->validated('notes'),
        ]);

        $notifications->forUsers(
            $notifications->bookingDesk(),
            'New reservation awaiting confirmation',
            $customer->display_name.' requested '.$vehicle->display_name
                .' from '.$start->format('d M Y').' to '.$end->format('d M Y').'.',
            route('admin.reservations.show', $reservation),
            'reservation'
        );

        return redirect()
            ->route('customer.reservations.show', $reservation)
            ->with('status', 'Your reservation has been received and is awaiting confirmation from our team.');
    }

    public function show(Request $request, Reservation $reservation)
    {
        $this->ensureOwnership($request, $reservation);

        $reservation->load(['vehicle', 'rental']);

        return view('customer.reservations.show', [
            'reservation' => $reservation,
        ]);
    }

    public function cancel(Request $request, Reservation $reservation, NotificationService $notifications)
    {
        $this->ensureOwnership($request, $reservation);

        $cancellable = in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)
            && $reservation->start_date->gte(Carbon::today());

        if (! $cancellable) {
            return back()->with('error', 'This reservation can no longer be cancelled.');
        }

        $reservation->update(['status' => ReservationStatus::Cancelled]);

        $notifications->forUsers(
            $notifications->bookingDesk(),
            'Reservation cancelled',
            ($reservation->customer->display_name ?? 'A customer')
                .' cancelled reservation #'.$reservation->id
                .' for '.($reservation->vehicle->display_name ?? 'a vehicle')
                .' from '.$reservation->start_date->format('d M Y')
                .' to '.$reservation->end_date->format('d M Y').'.',
            route('admin.reservations.show', $reservation),
            'reservation'
        );

        return redirect()
            ->route('customer.reservations.index')
            ->with('status', 'Your reservation has been cancelled.');
    }

    public function rentals(Request $request, OnlinePaymentService $checkout)
    {
        $customer = $request->user()->customer;

        if (! $customer) {
            return $this->missingProfile();
        }

        $rentals = $customer->rentals()
            ->with(['vehicle', 'payments'])
            ->latest()
            ->paginate(10);

        return view('customer.rentals.index', [
            'rentals' => $rentals,
            'onlinePayments' => $checkout->enabled(),
            'onlineMethods' => array_intersect_key(PaymentMethod::options(), array_flip(
                array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::onlineCapable())
            )),
        ]);
    }

    private function ensureOwnership(Request $request, Reservation $reservation): void
    {
        $customer = $request->user()->customer;

        abort_unless($customer && $reservation->customer_id === $customer->id, 404);
    }

    private function missingProfile()
    {
        return redirect()
            ->route('profile.edit')
            ->with('error', 'Please complete your customer profile before managing bookings.');
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

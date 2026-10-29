<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservation\CancelReservationRequest;
use App\Http\Requests\Reservation\RejectReservationRequest;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\Vehicle;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;

final class ReservationController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function index(Request $request)
    {
        $reservations = Reservation::query()
            ->with(['customer.user', 'vehicle'])
            ->when($request->filled('status'), function ($q) use ($request) {
                if (array_key_exists($request->input('status'), ReservationStatus::options())) {
                    $q->where('status', $request->input('status'));
                }
            })
            ->when($request->filled('vehicle'), fn ($q) => $q->where('vehicle_id', $request->integer('vehicle')))
            ->when($request->filled('from'), fn ($q) => $q->where('start_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('start_date', '<=', $request->input('to')))
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
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.reservations.index', [
            'reservations' => $reservations,
            'statuses' => ReservationStatus::options(),
            'vehicles' => Vehicle::query()->orderBy('plate_number')->get(['id', 'plate_number', 'make', 'model']),
        ]);
    }

    public function show(Reservation $reservation)
    {
        $reservation->load([
            'customer.user',
            'vehicle',
            'confirmedBy',
            'rental.payments',
        ]);

        return view('admin.reservations.show', [
            'reservation' => $reservation,
        ]);
    }

    public function confirm(Reservation $reservation, AuditLogger $audit)
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            return redirect()->back()->with('error', 'Only pending reservations can be confirmed.');
        }

        if ($reservation->conflictsWithLiveBookings()) {
            return redirect()->back()->with(
                'error',
                'This vehicle is already booked by another live reservation for the same dates.'
            );
        }

        $reservation->update([
            'status' => ReservationStatus::Confirmed,
            'confirmed_by' => auth()->id(),
            'confirmed_at' => now(),
        ]);

        if ($reservation->vehicle->status === VehicleStatus::Available) {
            $reservation->vehicle->update(['status' => VehicleStatus::Reserved]);
        }

        $this->notifications->forCustomer(
            $reservation->customer,
            'Reservation confirmed',
            'Your reservation for '.$reservation->vehicle->display_name
                .' from '.$reservation->start_date->format('d M Y')
                .' to '.$reservation->end_date->format('d M Y').' has been confirmed.',
            route('customer.reservations.show', $reservation),
            'reservation_confirmed'
        );

        $audit->log('reservation.confirmed', 'Confirmed reservation #'.$reservation->id.' for '.$reservation->vehicle->display_name.'.', $reservation);

        return redirect()->back()->with('status', 'Reservation confirmed and the customer notified.');
    }

    public function reject(RejectReservationRequest $request, Reservation $reservation, AuditLogger $audit)
    {
        if ($reservation->status !== ReservationStatus::Pending) {
            return redirect()->back()->with('error', 'Only pending reservations can be rejected.');
        }

        $reservation->update([
            'status' => ReservationStatus::Rejected,
            'rejection_reason' => $request->validated('reason'),
        ]);

        $this->notifications->forCustomer(
            $reservation->customer,
            'Reservation rejected',
            'Your reservation for '.($reservation->vehicle->display_name ?? 'the vehicle')
                .' could not be accepted. Reason: '.$request->validated('reason'),
            route('customer.reservations.show', $reservation),
            'reservation_rejected'
        );

        $audit->log('reservation.rejected', 'Rejected reservation #'.$reservation->id.'. Reason: '.$request->validated('reason'), $reservation, ['reason' => $request->validated('reason')]);

        return redirect()->back()->with('status', 'Reservation rejected and the customer notified.');
    }

    public function cancel(CancelReservationRequest $request, Reservation $reservation, AuditLogger $audit)
    {
        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            return redirect()->back()->with('error', 'Only pending or confirmed reservations can be cancelled.');
        }

        if ($reservation->rental()->exists()) {
            return redirect()->back()->with('error', 'This reservation already has a linked rental and cannot be cancelled.');
        }

        $reason = $request->validated('reason');
        $notes = $reservation->notes ? trim($reservation->notes) : '';
        $note = $reason ? ($notes === '' ? 'Cancelled: '.$reason : $notes."\nCancelled: ".$reason) : $notes;

        $reservation->update([
            'status' => ReservationStatus::Cancelled,
            'notes' => $note === '' ? null : $note,
        ]);

        $vehicle = $reservation->vehicle;
        if ($vehicle && $vehicle->status === VehicleStatus::Reserved && $this->vehicleHasNoOtherLiveBooking($vehicle)) {
            $vehicle->update(['status' => VehicleStatus::Available]);
        }

        $this->notifications->forCustomer(
            $reservation->customer,
            'Reservation cancelled',
            'Your reservation for '.($vehicle->display_name ?? 'the vehicle')
                .' from '.$reservation->start_date->format('d M Y')
                .' to '.$reservation->end_date->format('d M Y').' has been cancelled.'
                .($reason ? ' Reason: '.$reason : ''),
            route('customer.reservations.show', $reservation),
            'reservation_cancelled'
        );

        $audit->log('reservation.cancelled', 'Cancelled reservation #'.$reservation->id.($reason ? '. Reason: '.$reason : '.'), $reservation, $reason ? ['reason' => $reason] : []);

        return redirect()->back()->with('status', 'Reservation cancelled and the customer notified.');
    }

    public function destroy(Reservation $reservation, AuditLogger $audit)
    {
        $deletable = [ReservationStatus::Cancelled, ReservationStatus::Rejected, ReservationStatus::Completed];

        if (! in_array($reservation->status, $deletable, true)) {
            return redirect()->back()->with('error', 'Only cancelled, rejected or completed reservations can be deleted.');
        }

        if ($reservation->rental()->exists()) {
            return redirect()->back()->with('error', 'This reservation has a linked rental and cannot be deleted.');
        }

        $reservation->delete();

        $audit->log('reservation.deleted', 'Deleted reservation #'.$reservation->id.'.', null);

        return redirect()->route('admin.reservations.index')->with('status', 'Reservation deleted.');
    }

    private function vehicleHasNoOtherLiveBooking($vehicle): bool
    {
        $reservationLive = Reservation::query()->active()->where('vehicle_id', $vehicle->id)->exists();
        $rentalLive = Rental::query()->open()->where('vehicle_id', $vehicle->id)->exists();

        return ! $reservationLive && ! $rentalLive;
    }
}

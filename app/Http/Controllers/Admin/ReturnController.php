<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Return\StoreReturnRequest;
use App\Models\Rental;
use App\Models\Reservation;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ReturnController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function create(Request $request, Rental $rental)
    {
        $rental->load(['vehicle', 'customer.user']);

        if ($rental->vehicleReturn()->exists()) {
            return redirect()->route('admin.rentals.show', $rental)
                ->with('error', 'This rental has already been returned.');
        }

        if (! in_array($rental->status, [RentalStatus::Active, RentalStatus::Overdue], true)) {
            return redirect()->route('admin.rentals.show', $rental)
                ->with('error', 'Only active or overdue rentals can be returned.');
        }

        return view('admin.returns.create', [
            'rental' => $rental,
            'conditions' => VehicleCondition::options(),
        ]);
    }

    public function store(StoreReturnRequest $request, Rental $rental)
    {
        $rental->load(['vehicle', 'customer']);

        if ($rental->vehicleReturn()->exists()) {
            return redirect()->route('admin.rentals.show', $rental)
                ->with('error', 'This rental has already been returned.');
        }

        if (! in_array($rental->status, [RentalStatus::Active, RentalStatus::Overdue], true)) {
            return redirect()->route('admin.rentals.show', $rental)
                ->with('error', 'Only active or overdue rentals can be returned.');
        }

        $data = $request->validated();
        $returnedAt = $request->filled('returned_at')
            ? Carbon::parse((string) $data['returned_at'])
            : now();
        $extra = (float) ($data['additional_charges'] ?? 0);
        $vehicle = $rental->vehicle;

        DB::transaction(function () use ($rental, $vehicle, $data, $returnedAt, $extra) {
            $rental->vehicleReturn()->create([
                'vehicle_id' => $rental->vehicle_id,
                'returned_at' => $returnedAt,
                'condition' => $data['condition'],
                'fuel_level' => $data['fuel_level'] ?? null,
                'mileage_at_return' => $data['mileage_at_return'] ?? null,
                'damage_notes' => $data['damage_notes'] ?? null,
                'additional_charges' => $extra,
                'processed_by' => auth()->id(),
            ]);

            $rental->additional_charges = (float) $rental->additional_charges + $extra;
            $rental->status = RentalStatus::Completed;
            $rental->recalculate();
            $rental->save();

            if ($vehicle) {
                $needsMaintenance = in_array(
                    VehicleCondition::from($data['condition']),
                    VehicleCondition::needsCharge(),
                    true
                );

                $update = [];
                if (isset($data['mileage_at_return'])) {
                    $update['mileage'] = (int) $data['mileage_at_return'];
                }

                if ($needsMaintenance) {
                    $update['status'] = VehicleStatus::Maintenance;
                } else {
                    $liveReservation = Reservation::query()
                        ->where('vehicle_id', $vehicle->id)
                        ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                        ->whereDate('start_date', '<=', now()->toDateString())
                        ->exists();

                    $update['status'] = $liveReservation ? VehicleStatus::Reserved : VehicleStatus::Available;
                }

                $vehicle->update($update);
            }

            $this->notifications->forCustomer(
                $rental->customer,
                'Rental completed',
                'Thanks for returning '.($vehicle->display_name ?? 'the vehicle')
                    .'. Final total: '.number_format((float) $rental->total_amount, 0)
                    .' TZS, balance due: '.number_format($rental->balanceDue(), 0).' TZS.',
                route('customer.rentals.index'),
                'rental_completed'
            );
        });

        return redirect()->route('admin.rentals.show', $rental)
            ->with('status', 'Vehicle return recorded and the rental completed.');
    }
}

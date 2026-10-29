<?php

namespace App\Console\Commands;

use App\Enums\MaintenanceStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Models\Maintenance;
use App\Models\Rental;
use App\Models\Reservation;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * UC22: a pending reservation is a soft hold on a vehicle. If its start date
 * passes while it was never confirmed or picked up, the hold is expired so the
 * car is released back to the fleet and the desk stops chasing a dead booking.
 */
class ExpireReservationHolds extends Command
{
    protected $signature = 'reservations:expire-holds';

    protected $description = 'Expire stale pending reservations whose start date has passed and release the held vehicles';

    public function __construct(private readonly NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today();

        $stale = Reservation::query()
            ->where('status', ReservationStatus::Pending)
            ->whereDate('start_date', '<', $today->toDateString())
            ->with(['vehicle', 'customer.user'])
            ->get();

        $count = 0;
        foreach ($stale as $reservation) {
            $reservation->update(['status' => ReservationStatus::Expired]);

            $this->releaseVehicle($reservation);

            $vehicleName = $reservation->vehicle?->display_name ?? 'the vehicle';

            $this->notifications->forUsers(
                $this->notifications->bookingDesk(),
                'Reservation expired',
                ($reservation->customer?->display_name ?? 'A customer')
                    .'\'s reservation #'.$reservation->id.' for '.$vehicleName
                    .' (start '.$reservation->start_date->format('d M Y').') was never confirmed and has expired.',
                route('admin.reservations.show', $reservation),
                'reservation_expired'
            );

            $this->notifications->forCustomer(
                $reservation->customer,
                'Your reservation expired',
                'Your reservation for '.$vehicleName.' beginning '.$reservation->start_date->format('d M Y')
                    .' was not confirmed in time and has expired. Please book again if you still need it.',
                route('customer.reservations.index'),
                'reservation_expired'
            );

            $count++;
        }

        $this->info("Expired {$count} stale pending reservation(s).");

        return self::SUCCESS;
    }

    /**
     * Put a Reserved vehicle back on the board when nothing else is still
     * holding it for the near term.
     */
    private function releaseVehicle(Reservation $reservation): void
    {
        $vehicle = $reservation->vehicle;

        if ($vehicle === null || $vehicle->status !== VehicleStatus::Reserved) {
            return;
        }

        $stillHeld = Reservation::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereKeyNot($reservation->id)
            ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
            ->whereDate('end_date', '>=', Carbon::today()->toDateString())
            ->exists();

        $onHire = Rental::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [RentalStatus::Active->value, RentalStatus::Overdue->value])
            ->exists();

        $inMaintenance = Maintenance::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
            ->exists();

        if (! $stillHeld && ! $onHire && ! $inMaintenance) {
            $vehicle->update(['status' => VehicleStatus::Available]);
        }
    }
}

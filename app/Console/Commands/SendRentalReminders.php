<?php

namespace App\Console\Commands;

use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Models\Rental;
use App\Models\Reservation;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * UC22: the two forward-looking nudges of the hire lifecycle - a reminder the
 * day before a confirmed booking is due to be picked up, and a reminder the day
 * before an active hire is due back. Each fires on the single "tomorrow" match,
 * so a booking is reminded once, not every day.
 */
class SendRentalReminders extends Command
{
    protected $signature = 'rentals:send-reminders';

    protected $description = 'Remind customers and the desk about pick-ups starting tomorrow and hires due back tomorrow';

    public function __construct(private readonly NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $tomorrow = Carbon::tomorrow()->toDateString();

        $pickups = Reservation::query()
            ->where('status', ReservationStatus::Confirmed)
            ->whereDate('start_date', $tomorrow)
            ->with(['vehicle', 'customer.user'])
            ->get();

        $pickupCount = 0;
        foreach ($pickups as $reservation) {
            $vehicleName = $reservation->vehicle?->display_name ?? 'your vehicle';

            $this->notifications->forCustomer(
                $reservation->customer,
                'Your pick-up is tomorrow',
                'A friendly reminder that your rental of '.$vehicleName.' starts tomorrow, '
                    .$reservation->start_date->format('d M Y').'. Please bring your documents to the desk.',
                route('customer.reservations.show', $reservation),
                'pickup_reminder'
            );

            $this->notifications->forUsers(
                $this->notifications->bookingDesk(),
                'Pick-up due tomorrow',
                ($reservation->customer?->display_name ?? 'A customer')
                    .' picks up '.$vehicleName.' tomorrow ('
                    .$reservation->start_date->format('d M Y').').',
                route('admin.reservations.show', $reservation),
                'pickup_reminder'
            );

            $pickupCount++;
        }

        $returns = Rental::query()
            ->where('status', RentalStatus::Active)
            ->whereDate('end_date', $tomorrow)
            ->with(['vehicle', 'customer.user'])
            ->get();

        $returnCount = 0;
        foreach ($returns as $rental) {
            $vehicleName = $rental->vehicle?->display_name ?? 'the vehicle';

            $this->notifications->forCustomer(
                $rental->customer,
                'Your rental is due back tomorrow',
                'Your '.$vehicleName.' is due back tomorrow, '.$rental->end_date->format('d M Y')
                    .'. Returning on time avoids overdue charges.',
                route('customer.rentals.show', $rental),
                'return_reminder'
            );

            $returnCount++;
        }

        $this->info("Sent {$pickupCount} pick-up reminder(s) and {$returnCount} return reminder(s).");

        return self::SUCCESS;
    }
}

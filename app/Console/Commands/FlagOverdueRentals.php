<?php

namespace App\Console\Commands;

use App\Enums\RentalStatus;
use App\Models\Rental;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * readme #24/#25: an overdue rental is an operational escalation, not a system
 * incident, so it must reach the hire desk - the staff who handled the rental
 * and the branch managers who own it - plus the customer who still has the car.
 * The administrator is deliberately excluded. Flipping the stored status also
 * de-duplicates: a rental only crosses into Overdue once.
 */
class FlagOverdueRentals extends Command
{
    protected $signature = 'rentals:flag-overdue';

    protected $description = 'Mark past-due active rentals as overdue and notify the involved staff, branch managers and customer';

    public function __construct(private readonly NotificationService $notifications)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $newlyOverdue = Rental::query()
            ->where('status', RentalStatus::Active)
            ->whereDate('end_date', '<', $today)
            ->with(['customer.user', 'vehicle', 'staff'])
            ->get();

        $count = 0;
        foreach ($newlyOverdue as $rental) {
            $rental->update(['status' => RentalStatus::Overdue]);

            $vehicleName = $rental->vehicle?->display_name ?? 'the vehicle';
            $dueBack = $rental->end_date->format('d M Y');
            $deskLink = route('admin.rentals.index', ['status' => RentalStatus::Overdue->value]);

            // Involved staff + branch managers, de-duplicated, admin never included.
            $recipientIds = collect([$rental->staff_id])
                ->merge($this->notifications->branchManagers())
                ->filter()
                ->unique()
                ->all();
            $this->notifications->forUsers(
                $recipientIds,
                'Overdue rental',
                sprintf('%s (%s) is overdue for return, due on %s.', $rental->customer?->display_name ?? 'A customer', $vehicleName, $dueBack),
                $deskLink,
                'alert'
            );

            $this->notifications->forCustomer(
                $rental->customer,
                'Rental overdue',
                sprintf('Your %s rental was due back on %s. Please return it as soon as possible.', $vehicleName, $dueBack),
                route('customer.rentals.index'),
                'alert'
            );

            $count++;
        }

        $this->info("Flagged {$count} rental(s) as overdue.");

        return self::SUCCESS;
    }
}

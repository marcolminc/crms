<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Enums\VehicleStatus;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeededDataIntegrityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_demo_database_honours_every_business_invariant(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertDemoDataIsPopulated();
        $this->assertSeededAccountsAreUsable();
        $this->assertMoneyColumnsAgreeWithTheirInputs();
        $this->assertNoVehicleIsDoubleCommitted();
        $this->assertVehicleStatusesMatchTheirRecords();
        $this->assertPaymentReferencesFollowTheMethod();
        $this->assertPaidRowsCarryADate();
        $this->assertAnAvailableVehicleIsBookableInTheFuture();
    }

    private function assertDemoDataIsPopulated(): void
    {
        $this->assertGreaterThan(0, User::query()->count());
        $this->assertGreaterThan(0, Vehicle::query()->count());
        $this->assertGreaterThan(0, Rental::query()->count());
        $this->assertGreaterThan(0, Payment::query()->count());
        $this->assertGreaterThan(0, Reservation::query()->count());
    }

    private function assertSeededAccountsAreUsable(): void
    {
        $this->assertSame(
            0,
            User::query()->where('is_active', false)->count(),
            'Every seeded account must be usable, otherwise the demo locks real people out.'
        );
        $this->assertGreaterThan(
            0,
            User::query()->where('role', UserRole::Admin->value)->count(),
            'The demo needs at least one system administrator.'
        );
    }

    private function assertMoneyColumnsAgreeWithTheirInputs(): void
    {
        foreach (Rental::query()->with('payments')->get() as $rental) {
            $expectedSubtotal = (float) $rental->rate_per_day * $rental->days;
            $expectedTotal = max(0, $expectedSubtotal + (float) $rental->additional_charges - (float) $rental->discount);

            $this->assertEqualsWithDelta($expectedSubtotal, (float) $rental->subtotal, 0.01);
            $this->assertEqualsWithDelta($expectedTotal, (float) $rental->total_amount, 0.01);
            $this->assertLessThanOrEqual(
                (float) $rental->total_amount + 0.01,
                $rental->paidAmount(),
                "Rental {$rental->id} has been paid more than its total."
            );
        }
    }

    private function assertNoVehicleIsDoubleCommitted(): void
    {
        $this->assertSame(
            0,
            $this->overlappingCount('reservations', [
                ReservationStatus::Pending->value,
                ReservationStatus::Confirmed->value,
            ]),
            'Seeded reservations must never overlap for one vehicle.'
        );
        $this->assertSame(
            0,
            $this->overlappingCount('rentals', [
                RentalStatus::Active->value,
                RentalStatus::Overdue->value,
            ]),
            'Seeded hires must never put one vehicle on two rentals at once.'
        );
    }

    /**
     * @param  array<int, string>  $statuses
     */
    private function overlappingCount(string $table, array $statuses): int
    {
        return DB::table($table)
            ->whereIn('status', $statuses)
            ->whereExists(function ($query) use ($table, $statuses) {
                $query->select(DB::raw(1))
                    ->from("{$table} as other")
                    ->whereColumn('other.vehicle_id', "{$table}.vehicle_id")
                    ->whereColumn('other.id', '!=', "{$table}.id")
                    ->whereIn('other.status', $statuses)
                    ->whereColumn('other.start_date', '<=', "{$table}.end_date")
                    ->whereColumn('other.end_date', '>=', "{$table}.start_date");
            })
            ->count();
    }

    private function assertVehicleStatusesMatchTheirRecords(): void
    {
        foreach (Vehicle::query()->get() as $vehicle) {
            $hasOpenRental = $vehicle->rentals()
                ->whereIn('status', [RentalStatus::Active->value, RentalStatus::Overdue->value])
                ->exists();
            $hasLiveReservation = $vehicle->reservations()
                ->whereIn('status', [ReservationStatus::Pending->value, ReservationStatus::Confirmed->value])
                ->exists();
            $hasOngoingMaintenance = $vehicle->maintenances()
                ->whereIn('status', [MaintenanceStatus::Scheduled->value, MaintenanceStatus::InProgress->value])
                ->exists();

            $this->assertFalse(
                $vehicle->status === VehicleStatus::Rented && ! $hasOpenRental,
                "Vehicle {$vehicle->plate_number} is flagged Rented with no open rental."
            );
            $this->assertFalse(
                $vehicle->status === VehicleStatus::Maintenance && ! $hasOngoingMaintenance,
                "Vehicle {$vehicle->plate_number} is flagged Under Maintenance with no open job."
            );
            $this->assertFalse(
                $vehicle->status === VehicleStatus::Available && ($hasOpenRental || $hasOngoingMaintenance),
                "Vehicle {$vehicle->plate_number} is offered while it is actually committed elsewhere."
            );
        }
    }

    private function assertPaymentReferencesFollowTheMethod(): void
    {
        $mobileMoney = array_map(fn (PaymentMethod $method) => $method->value, PaymentMethod::mobileMoney());

        foreach (Payment::query()->get() as $payment) {
            if (in_array($payment->payment_method->value, $mobileMoney, true)) {
                $this->assertNotNull($payment->transaction_reference);
            } else {
                $this->assertNull($payment->transaction_reference);
            }
        }
    }

    private function assertPaidRowsCarryADate(): void
    {
        $this->assertSame(
            0,
            Payment::query()
                ->whereIn('status', [PaymentStatus::Paid->value, PaymentStatus::Partial->value])
                ->whereNull('paid_at')
                ->count()
        );
    }

    private function assertAnAvailableVehicleIsBookableInTheFuture(): void
    {
        $vehicle = Vehicle::query()->where('status', VehicleStatus::Available->value)->first();

        $this->assertNotNull($vehicle);
        $this->assertTrue(
            $vehicle->isFreeBetween(now()->addDays(30)->toDateString(), now()->addDays(31)->toDateString()),
            'A far-future window must be bookable on an available seeded vehicle.'
        );
    }
}

<?php

namespace Database\Seeders;

use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleCondition;
use App\Models\Customer;
use App\Models\Maintenance;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReturn;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Builds a coherent four-month (June-October 2026) operating history for the
 * fleet. Everything is hand-written against an explicit schedule so the demo
 * data is believable and internally consistent:
 *
 *  - no two bookings overlap for the same vehicle on the same dates;
 *  - rental money columns always satisfy subtotal = rate * days and
 *    total = subtotal + additional_charges - discount;
 *  - a vehicle's status column agrees with the open rental / live reservation /
 *    ongoing maintenance row that justifies it;
 *  - every mobile-money payment carries a transaction reference, every non
 *    mobile-money payment leaves it null;
 *  - no payment exceeds the total of the rental it belongs to.
 */
class RentalHistorySeeder extends Seeder
{
    private int $receiptNo = 0;

    /** @var array<string, Vehicle> */
    private array $vehicles = [];

    /** @var array<int, Customer> */
    private array $customers = [];

    private array $staff = [];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->resolveReferences();

            $this->seedCompletedHistory();
            $this->seedOpenRentals();
            $this->seedCancelledRental();
            $this->seedLiveReservations();
            $this->seedMaintenance();
            $this->seedNotifications();
        });
    }

    private function resolveReferences(): void
    {
        foreach (Vehicle::all() as $vehicle) {
            $this->vehicles[$vehicle->plate_number] = $vehicle;
        }

        foreach (Customer::orderBy('id')->get() as $i => $customer) {
            $this->customers[$i + 1] = $customer;
        }

        $emails = [
            'admin' => 'admin@landvalley.co.tz',
            'fleet' => 'fleet@landvalley.co.tz',
            'staff' => 'staff@landvalley.co.tz',
            'accounts' => 'accounts@landvalley.co.tz',
            'branchManager' => 'manager@landvalley.co.tz',
        ];
        foreach ($emails as $key => $email) {
            $this->staff[$key] = User::where('email', $email)->firstOrFail();
        }
    }

    private function vehicle(string $plate): Vehicle
    {
        return $this->vehicles[$plate];
    }

    private function customer(int $index): Customer
    {
        return $this->customers[$index];
    }

    private function days(string $start, string $end): int
    {
        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }

    private function totalFor(Vehicle $vehicle, string $start, string $end, float $add = 0, float $disc = 0): array
    {
        $days = $this->days($start, $end);
        $rate = (float) $vehicle->rate_per_day;
        $subtotal = round($rate * $days, 2);
        $total = round(max(0, $subtotal + $add - $disc), 2);

        return [$days, $rate, $subtotal, $total];
    }

    private function mobile(PaymentMethod $method): bool
    {
        return in_array($method, PaymentMethod::mobileMoney(), true);
    }

    private function record(string $paidOn, float $amount): string
    {
        $this->receiptNo++;

        return 'RC-'.substr(str_replace('-', '', $paidOn), 0, 8).'-'.str_pad((string) $this->receiptNo, 4, '0', STR_PAD_LEFT);
    }

    private function makePayment(Rental $rental, string $date, float $amount, PaymentMethod $method, PaymentStatus $status): Payment
    {
        $reference = $this->mobile($method)
            ? strtoupper(substr(md5($rental->id.'|'.$amount.'|'.$method->value), 0, 6)).substr((string) (100000 + ($rental->id * 7) + (int) $amount), 0, 6)
            : null;

        return Payment::create([
            'rental_id' => $rental->id,
            'receipt_number' => $this->record($date, $amount),
            'amount' => $amount,
            'payment_method' => $method,
            'status' => $status,
            'transaction_reference' => $reference,
            'recorded_by' => $this->staff['accounts']->id,
            'paid_at' => $status === PaymentStatus::Pending ? null : $date.' 10:30:00',
            'notes' => null,
        ]);
    }

    private function seedCompletedHistory(): void
    {
        // Optional keys: add (additional charge), disc (discount), res ('J'/'K' linked reservation).
        $rows = [
            ['T234 ABC', 2, '2026-06-03', '2026-06-10', 'res' => 'J'],
            ['T234 ABC', 5, '2026-07-14', '2026-07-18'],
            ['T 456 XY', 3, '2026-06-05', '2026-06-12'],
            ['T 456 XY', 7, '2026-08-01', '2026-08-05'],
            ['T112 PQR', 1, '2026-06-15', '2026-06-20'],
            ['T112 PQR', 4, '2026-07-08', '2026-07-12'],
            ['T112 PQR', 6, '2026-09-01', '2026-09-04'],
            ['T225 STU', 8, '2026-06-08', '2026-06-11', 'add' => 80000],
            ['T225 STU', 2, '2026-07-22', '2026-07-26'],
            ['T225 STU', 3, '2026-09-15', '2026-09-17'],
            ['T330 VWX', 5, '2026-06-10', '2026-06-14'],
            ['T330 VWX', 7, '2026-08-12', '2026-08-16'],
            ['T410 YZA', 1, '2026-06-25', '2026-06-28'],
            ['T410 YZA', 4, '2026-08-03', '2026-08-09', 'disc' => 20000],
            ['T410 YZA', 8, '2026-10-01', '2026-10-03'],
            ['T515 BCD', 6, '2026-07-01', '2026-07-05', 'add' => 15000],
            ['T620 EFG', 3, '2026-06-18', '2026-06-22', 'add' => 60000],
            ['T620 EFG', 5, '2026-09-20', '2026-09-24'],
            ['T735 HIJ', 7, '2026-06-02', '2026-06-06', 'res' => 'K'],
            ['T735 HIJ', 1, '2026-08-20', '2026-08-23'],
            ['T840 KLM', 2, '2026-07-05', '2026-07-15', 'disc' => 100000],
            ['T840 KLM', 6, '2026-09-10', '2026-09-14'],
            ['T955 NOP', 4, '2026-06-30', '2026-07-04'],
            ['T955 NOP', 8, '2026-08-15', '2026-08-18'],
            ['T060 QRS', 5, '2026-07-10', '2026-07-13'],
            ['T060 QRS', 3, '2026-09-05', '2026-09-08'],
            ['T175 TUV', 8, '2026-06-12', '2026-06-16'],
            ['T175 TUV', 2, '2026-08-25', '2026-08-30'],
            ['T290 WXY', 1, '2026-07-18', '2026-07-21'],
            ['T290 WXY', 7, '2026-10-05', '2026-10-08'],
            ['T305 YZA', 3, '2026-06-20', '2026-06-24'],
            ['T305 YZA', 4, '2026-09-25', '2026-09-29', 'disc' => 25000],
            ['T420 BCD', 6, '2026-07-25', '2026-07-28'],
            ['T420 BCD', 5, '2026-10-08', '2026-10-11'],
            ['T530 EFG', 2, '2026-06-08', '2026-06-12'],
            ['T640 HIJ', 7, '2026-06-28', '2026-07-02'],
            ['T640 HIJ', 1, '2026-08-08', '2026-08-10'],
        ];

        $splits = [
            'T234 ABC|2026-06-03', 'T112 PQR|2026-06-15', 'T410 YZA|2026-06-25',
            'T620 EFG|2026-06-18', 'T840 KLM|2026-07-05', 'T175 TUV|2026-08-25',
            'T305 YZA|2026-06-20', 'T640 HIJ|2026-08-08',
        ];
        $damage = [
            'T225 STU|2026-06-08' => 80000,
            'T620 EFG|2026-06-18' => 60000,
        ];
        $fair = ['T305 YZA|2026-06-20'];
        $excellent = ['T840 KLM|2026-07-05', 'T955 NOP|2026-06-30', 'T530 EFG|2026-06-08'];

        $linkedReservations = $this->seedLinkedReservations();
        $methods = [
            PaymentMethod::Mpesa->value, PaymentMethod::Cash->value, PaymentMethod::TigoPesa->value,
            PaymentMethod::Cash->value, PaymentMethod::AirtelMoney->value, PaymentMethod::BankTransfer->value,
            PaymentMethod::HaloPesa->value, PaymentMethod::Cash->value,
        ];

        foreach ($rows as $i => $row) {
            [$plate, $customerIndex, $start, $end] = $row;
            $key = $plate.'|'.$start;
            $vehicle = $this->vehicle($plate);
            $damageCharge = $damage[$key] ?? 0;
            $add = (float) ($row['add'] ?? 0) + $damageCharge;
            $disc = (float) ($row['disc'] ?? 0);

            [$days, $rate, $subtotal, $total] = $this->totalFor($vehicle, $start, $end, $add, $disc);

            $rental = Rental::create([
                'reservation_id' => isset($row['res']) ? $linkedReservations[$row['res']]->id : null,
                'customer_id' => $this->customer($customerIndex)->id,
                'vehicle_id' => $vehicle->id,
                'staff_id' => $this->staff['staff']->id,
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
                'rate_per_day' => $rate,
                'subtotal' => $subtotal,
                'additional_charges' => $add,
                'discount' => $disc,
                'total_amount' => $total,
                'status' => RentalStatus::Completed,
                'pickup_location' => 'Mwanza City Office',
                'dropoff_location' => 'Mwanza City Office',
            ]);

            $method = PaymentMethod::from($methods[$i % count($methods)]);
            $paidOn = Carbon::parse($end)->addDay()->format('Y-m-d');

            if (in_array($key, $splits, true)) {
                $deposit = round($total * 0.4 / 1000) * 1000;
                $this->makePayment($rental, Carbon::parse($start)->subDays(3)->format('Y-m-d'), $deposit, $method, PaymentStatus::Partial);
                $this->makePayment($rental, $paidOn, round($total - $deposit, 2), $method, PaymentStatus::Paid);
            } else {
                $this->makePayment($rental, $paidOn, $total, $method, PaymentStatus::Paid);
            }

            $condition = isset($damage[$key]) ? VehicleCondition::Damaged
                : (in_array($key, $fair, true) ? VehicleCondition::Fair
                    : (in_array($key, $excellent, true) ? VehicleCondition::Excellent : VehicleCondition::Good));

            VehicleReturn::create([
                'rental_id' => $rental->id,
                'vehicle_id' => $vehicle->id,
                'returned_at' => $end.' 16:00:00',
                'condition' => $condition,
                'fuel_level' => $condition === VehicleCondition::Fair ? 'half' : 'full',
                'mileage_at_return' => (int) ($vehicle->mileage + $days * 300),
                'damage_notes' => $condition === VehicleCondition::Damaged ? 'Rear bumper dent and cracked light reported on return.' : null,
                'additional_charges' => $damageCharge,
                'processed_by' => $this->staff['staff']->id,
            ]);
        }
    }

    /**
     * Two historical reservations that were converted into completed rentals,
     * so the reservation -> rental linkage and the completed status both show.
     */
    private function seedLinkedReservations(): array
    {
        $specs = [
            'J' => ['T234 ABC', 2, '2026-06-03', '2026-06-10'],
            'K' => ['T735 HIJ', 7, '2026-06-02', '2026-06-06'],
        ];

        $out = [];
        foreach ($specs as $tag => [$plate, $customerIndex, $start, $end]) {
            $vehicle = $this->vehicle($plate);
            $days = $this->days($start, $end);
            $out[$tag] = Reservation::create([
                'customer_id' => $this->customer($customerIndex)->id,
                'vehicle_id' => $vehicle->id,
                'start_date' => $start,
                'end_date' => $end,
                'estimated_total' => round((float) $vehicle->rate_per_day * $days, 2),
                'status' => ReservationStatus::Completed,
                'confirmed_by' => $this->staff['staff']->id,
                'confirmed_at' => Carbon::parse($start)->subDays(2)->format('Y-m-d H:i:s'),
            ]);
        }

        return $out;
    }

    private function seedOpenRentals(): void
    {
        $open = [
            ['T234 ABC', 1, '2026-09-28', '2026-10-28', RentalStatus::Active, 'Tigo Pesa', 0.4, PaymentStatus::Partial, 'Mwanza City Office', 'Mwanza City Office'],
            ['T530 EFG', 4, '2026-10-24', '2026-10-30', RentalStatus::Active, 'Airtel Money', 0.3, PaymentStatus::Partial, 'Mwanza Airport', 'Mwanza City Office'],
            ['T410 YZA', 4, '2026-10-18', '2026-10-23', RentalStatus::Overdue, 'Cash', 0.5, PaymentStatus::Partial, 'Mwanza City Office', 'Mwanza City Office'],
            ['T515 BCD', 2, '2026-10-10', '2026-10-22', RentalStatus::Overdue, 'M-Pesa', 0.6, PaymentStatus::Pending, 'Mwanza City Office', 'Mwanza City Office'],
        ];

        foreach ($open as [$plate, $customerIndex, $start, $end, $status, $methodLabel, $fraction, $payStatus, $pickup, $dropoff]) {
            $vehicle = $this->vehicle($plate);
            [$days, $rate, $subtotal, $total] = $this->totalFor($vehicle, $start, $end);

            $rental = Rental::create([
                'customer_id' => $this->customer($customerIndex)->id,
                'vehicle_id' => $vehicle->id,
                'staff_id' => $this->staff['staff']->id,
                'start_date' => $start,
                'end_date' => $end,
                'days' => $days,
                'rate_per_day' => $rate,
                'subtotal' => $subtotal,
                'additional_charges' => 0,
                'discount' => 0,
                'total_amount' => $total,
                'status' => $status,
                'pickup_location' => $pickup,
                'dropoff_location' => $dropoff,
            ]);

            // Open rentals only have a deposit (or a pending payment) so a
            // balance remains due.
            $deposit = round($total * $fraction / 1000) * 1000;
            $this->makePayment($rental, $start, $deposit, $this->methodFromLabel($methodLabel), $payStatus);
        }
    }

    private function seedCancelledRental(): void
    {
        $vehicle = $this->vehicle('T640 HIJ');
        $start = '2026-10-15';
        $end = '2026-10-17';
        [$days, $rate, $subtotal, $total] = $this->totalFor($vehicle, $start, $end);

        $rental = Rental::create([
            'customer_id' => $this->customer(4)->id,
            'vehicle_id' => $vehicle->id,
            'staff_id' => $this->staff['staff']->id,
            'start_date' => $start,
            'end_date' => $end,
            'days' => $days,
            'rate_per_day' => $rate,
            'subtotal' => $subtotal,
            'additional_charges' => 0,
            'discount' => 0,
            'total_amount' => $total,
            'status' => RentalStatus::Cancelled,
            'pickup_location' => 'Mwanza City Office',
            'dropoff_location' => 'Mwanza City Office',
            'notes' => 'Customer cancelled before pickup; deposit refunded.',
        ]);

        $this->makePayment($rental, $start, round($total * 0.5 / 1000) * 1000, PaymentMethod::Cash, PaymentStatus::Refunded);
    }

    private function seedLiveReservations(): void
    {
        $rows = [
            // status, plate, customer, start, end
            ['pending', 'T 456 XY', 1, '2026-11-10', '2026-11-13', null],
            ['pending', 'T112 PQR', 6, '2026-11-15', '2026-11-18', null],
            ['pending', 'T735 HIJ', 2, '2026-12-01', '2026-12-04', null],
            ['pending', 'T290 WXY', 5, '2026-11-20', '2026-11-22', null],
            ['confirmed', 'T955 NOP', 8, '2026-11-01', '2026-11-05', 'confirmed'],
            ['confirmed', 'T840 KLM', 3, '2026-11-08', '2026-11-12', 'confirmed'],
            ['cancelled', 'T225 STU', 7, '2026-10-12', '2026-10-14', 'cancelled'],
            ['cancelled', 'T305 YZA', 2, '2026-09-01', '2026-09-03', 'cancelled'],
            ['rejected', 'T060 QRS', 6, '2026-10-05', '2026-10-07', 'rejected'],
        ];

        foreach ($rows as [$kind, $plate, $customerIndex, $start, $end, $flag]) {
            $vehicle = $this->vehicle($plate);
            $days = $this->days($start, $end);

            Reservation::create([
                'customer_id' => $this->customer($customerIndex)->id,
                'vehicle_id' => $vehicle->id,
                'start_date' => $start,
                'end_date' => $end,
                'estimated_total' => round((float) $vehicle->rate_per_day * $days, 2),
                'status' => ReservationStatus::from($kind),
                'rejection_reason' => $flag === 'rejected' ? 'Vehicle was already committed to a corporate booking for those dates.' : null,
                'confirmed_by' => $flag === 'confirmed' ? $this->staff['branchManager']->id : null,
                'confirmed_at' => $flag === 'confirmed' ? Carbon::parse($start)->subDays(6)->format('Y-m-d H:i:s') : null,
            ]);
        }
    }

    private function seedMaintenance(): void
    {
        // Ongoing jobs justify the maintenance status on vehicles 8 and 13.
        Maintenance::create([
            'vehicle_id' => $this->vehicle('T620 EFG')->id,
            'title' => 'Front brake replacement',
            'description' => 'Worn pads and scoring on both front discs.',
            'service_provider' => 'TBC Garage Mwanza',
            'start_date' => '2026-10-20',
            'end_date' => null,
            'cost' => 180000,
            'status' => MaintenanceStatus::InProgress,
        ]);

        Maintenance::create([
            'vehicle_id' => $this->vehicle('T175 TUV')->id,
            'title' => 'Gearbox rebuild',
            'description' => 'Second-gear synchro failure reported by driver.',
            'service_provider' => 'Kariakuli Motors',
            'start_date' => '2026-10-22',
            'end_date' => null,
            'cost' => null,
            'status' => MaintenanceStatus::Scheduled,
        ]);

        // A few completed jobs so the completed filter has rows to show.
        $completed = [
            ['T225 STU', 'Scheduled service 60,000 km', 'Bongwe Auto Clinic', '2026-06-01', '2026-06-02', 120000],
            ['T420 BCD', 'Clutch replacement', 'Kariakuli Motors', '2026-09-01', '2026-09-03', 300000],
            ['T840 KLM', 'AC recharge', 'TBC Garage Mwanza', '2026-06-15', '2026-06-16', 90000],
        ];
        foreach ($completed as [$plate, $title, $provider, $start, $end, $cost]) {
            Maintenance::create([
                'vehicle_id' => $this->vehicle($plate)->id,
                'title' => $title,
                'service_provider' => $provider,
                'start_date' => $start,
                'end_date' => $end,
                'cost' => $cost,
                'status' => MaintenanceStatus::Completed,
            ]);
        }
    }

    private function methodFromLabel(string $label): PaymentMethod
    {
        return match ($label) {
            'M-Pesa' => PaymentMethod::Mpesa,
            'Tigo Pesa' => PaymentMethod::TigoPesa,
            'Airtel Money' => PaymentMethod::AirtelMoney,
            'Halo Pesa' => PaymentMethod::HaloPesa,
            'Bank Transfer' => PaymentMethod::BankTransfer,
            default => PaymentMethod::Cash,
        };
    }

    private function seedNotifications(): void
    {
        // Staff: overdue rentals drive alerts to the rental/admin/manager team.
        $overdue = Rental::query()->where('status', RentalStatus::Overdue)->with('vehicle', 'customer')->get();
        $overdueLink = route('admin.rentals.index', ['status' => RentalStatus::Overdue->value]);
        foreach ($overdue as $rental) {
            $msg = sprintf(
                '%s (%s) is overdue for return, due on %s.',
                $rental->customer->display_name,
                $rental->vehicle->plate_number,
                $rental->end_date->format('d M Y'),
            );
            foreach (['admin', 'staff', 'branchManager'] as $key) {
                Notification::create([
                    'user_id' => $this->staff[$key]->id,
                    'title' => 'Overdue rental',
                    'message' => $msg,
                    'link' => $overdueLink,
                    'type' => 'alert',
                    'read_at' => null,
                ]);
            }
        }

        // Staff: pending reservations need approval.
        $pendingLink = route('admin.reservations.index', ['status' => ReservationStatus::Pending->value]);
        foreach (Reservation::query()->where('status', ReservationStatus::Pending)->with('vehicle')->get() as $res) {
            Notification::create([
                'user_id' => $this->staff['staff']->id,
                'title' => 'New reservation pending approval',
                'message' => sprintf('Booking for %s from %s to %s awaits confirmation.', $res->vehicle->plate_number, $res->start_date->format('d M'), $res->end_date->format('d M Y')),
                'link' => $pendingLink,
                'type' => 'info',
                'read_at' => null,
            ]);
        }

        // Fleet manager: ongoing maintenance.
        foreach (Maintenance::query()->whereIn('status', [MaintenanceStatus::InProgress->value, MaintenanceStatus::Scheduled->value])->with('vehicle')->get() as $job) {
            Notification::create([
                'user_id' => $this->staff['fleet']->id,
                'title' => 'Vehicle in maintenance',
                'message' => sprintf('%s - %s', $job->vehicle->plate_number, $job->title),
                'link' => route('admin.maintenances.index', ['status' => $job->status->value]),
                'type' => 'warning',
                'read_at' => null,
            ]);
        }

        // Accountant: unpaid balance summary.
        $unpaid = Rental::query()->open()->get()->sum(fn (Rental $r) => $r->balanceDue());
        Notification::create([
            'user_id' => $this->staff['accounts']->id,
            'title' => 'Outstanding balances',
            'message' => sprintf('Open rentals carry TZS %s in unpaid balance.', number_format($unpaid, 2)),
            'link' => route('admin.payments.index'),
            'type' => 'alert',
            'read_at' => null,
        ]);

        // Customer logins.
        $amina = $this->customer(1)->user;
        $joseph = $this->customer(2)->user;
        $emmanuel = $this->customer(4)->user;

        Notification::create([
            'user_id' => $amina->id,
            'title' => 'Rental due soon',
            'message' => 'Your Hilux T234 ABC rental is due for return on 28 Oct 2026.',
            'link' => route('customer.rentals.index'),
            'type' => 'info',
            'read_at' => null,
        ]);
        Notification::create([
            'user_id' => $amina->id,
            'title' => 'Reservation awaiting confirmation',
            'message' => 'Your request for Isuzu D-Max (10-13 Nov 2026) is pending approval.',
            'link' => route('customer.reservations.index'),
            'type' => 'info',
            'read_at' => null,
        ]);
        Notification::create([
            'user_id' => $amina->id,
            'title' => 'Welcome to Land Valley Travels',
            'message' => 'Your account is ready. Browse our fleet and book online.',
            'link' => route('notifications.index'),
            'type' => 'success',
            'read_at' => now()->subDays(30),
        ]);
        Notification::create([
            'user_id' => $joseph->id,
            'title' => 'Rental overdue',
            'message' => 'Your Coaster T515 BCD rental was due back on 22 Oct 2026. Please return it as soon as possible.',
            'link' => route('customer.rentals.index'),
            'type' => 'alert',
            'read_at' => null,
        ]);
        Notification::create([
            'user_id' => $emmanuel->id,
            'title' => 'Rental starts today',
            'message' => 'Pick up your Hilux T530 EFG at Mwanza Airport from 8:00 AM today.',
            'link' => route('customer.rentals.index'),
            'type' => 'success',
            'read_at' => null,
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FineStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Enums\VehicleCondition;
use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Models\Maintenance;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\Reservation;
use App\Models\TrafficFine;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleReturn;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.index', [
            'reports' => [
                ['key' => 'fleet', 'label' => 'Fleet status', 'description' => 'Every vehicle grouped by status, with its daily rate and last booked service.'],
                ['key' => 'reservations', 'label' => 'Reservations', 'description' => 'Reservation listing for the period plus a count for each status.'],
                ['key' => 'rentals', 'label' => 'Rentals', 'description' => 'Hires started in the period with days, totals, money collected and outstanding.'],
                ['key' => 'payments', 'label' => 'Payments', 'description' => 'Cash collected in the period grouped by payment method, with the full listing.'],
                ['key' => 'customers', 'label' => 'Customers', 'description' => 'Accounts registered in the period with how many hires each one has.'],
                ['key' => 'returns', 'label' => 'Vehicle returns', 'description' => 'Returns processed in the period with condition counts and damage charges.'],
                ['key' => 'maintenance', 'label' => 'Maintenance', 'description' => 'Workshop jobs in the period with cost totals for each vehicle.'],
                ['key' => 'revenue', 'label' => 'Revenue', 'description' => 'Hire revenue billed, collected and outstanding in the period, grouped by month and payment method.'],
                ['key' => 'utilization', 'label' => 'Fleet utilization', 'description' => 'Days each vehicle spent on hire in the period and the revenue it earned.'],
                ['key' => 'tax', 'label' => 'Tax (VAT)', 'description' => 'VAT-inclusive hire revenue split into net and output tax for the period.'],
                ['key' => 'overdue', 'label' => 'Overdue rentals', 'description' => 'Hires past their drop-off date and still open, with days overdue and balance outstanding.'],
                ['key' => 'fines', 'label' => 'Traffic fines', 'description' => 'Fines recorded in the period with liability split between billed and absorbed.'],
            ],
        ]);
    }

    public function show(Request $request, string $report)
    {
        $range = $this->range($request);

        $data = match ($report) {
            'fleet' => $this->fleetReport(...$range),
            'reservations' => $this->reservationsReport(...$range),
            'rentals' => $this->rentalsReport(...$range),
            'payments' => $this->paymentsReport(...$range),
            'customers' => $this->customersReport(...$range),
            'returns' => $this->returnsReport(...$range),
            'maintenance' => $this->maintenanceReport(...$range),
            'revenue' => $this->revenueReport(...$range),
            'utilization' => $this->utilizationReport(...$range),
            'tax' => $this->taxReport(...$range),
            'overdue' => $this->overdueReport(...$range),
            'fines' => $this->finesReport(...$range),
            default => null,
        };

        if ($data === null) {
            abort(404);
        }

        if ($request->query('format') === 'csv') {
            return $this->streamCsv($data);
        }

        return view('admin.reports.show', [
            'report' => $data,
            'key' => $report,
            'from' => $range[0],
            'to' => $range[1],
        ]);
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function fleetReport(Carbon $from, Carbon $to): array
    {
        $services = Maintenance::query()
            ->whereBetween('start_date', [$from, $to])
            ->orderBy('start_date')
            ->get(['vehicle_id', 'start_date', 'cost', 'status'])
            ->groupBy('vehicle_id')
            ->map(fn ($group) => $group->last());

        $rows = [];
        $counts = [];

        foreach (VehicleStatus::cases() as $status) {
            $vehicles = Vehicle::query()
                ->where('status', $status)
                ->orderBy('plate_number')
                ->get();

            $counts[$status->label()] = $vehicles->count();

            foreach ($vehicles as $vehicle) {
                $service = $services->get($vehicle->id);

                $rows[] = [
                    'status' => $status->label(),
                    'plate' => $vehicle->plate_number,
                    'vehicle' => trim($vehicle->make.' '.$vehicle->model),
                    'type' => $vehicle->type->label(),
                    'rate' => $this->money($vehicle->rate_per_day),
                    'last_service' => $service?->start_date?->format('d M Y') ?? 'None in range',
                    'service_status' => $service === null ? '—' : ($service->status === MaintenanceStatus::Completed ? 'Completed' : 'Open'),
                ];
            }
        }

        return [
            'title' => 'Fleet status report',
            'columns' => [
                'status' => 'Status',
                'plate' => 'Plate',
                'vehicle' => 'Vehicle',
                'type' => 'Type',
                'rate' => 'Rate / day',
                'last_service' => 'Last service in range',
                'service_status' => 'Service state',
            ],
            'rows' => $rows,
            'summary' => array_merge(
                $counts,
                ['Vehicles on file' => array_sum($counts)],
            ),
        ];
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function reservationsReport(Carbon $from, Carbon $to): array
    {
        $reservations = Reservation::query()
            ->with(['customer.user', 'vehicle'])
            ->whereBetween('start_date', [$from, $to])
            ->orderBy('start_date')
            ->get();

        $counts = [];
        foreach (ReservationStatus::cases() as $status) {
            $counts[$status->label()] = $reservations->where('status', $status)->count();
        }

        return [
            'title' => 'Reservation report',
            'columns' => [
                'start' => 'Pick-up',
                'end' => 'Drop-off',
                'customer' => 'Customer',
                'vehicle' => 'Vehicle',
                'days' => 'Days',
                'estimate' => 'Estimate',
                'status' => 'Status',
            ],
            'rows' => $reservations->map(fn (Reservation $reservation) => [
                'start' => $reservation->start_date->format('d M Y'),
                'end' => $reservation->end_date->format('d M Y'),
                'customer' => $reservation->customer?->display_name ?? '—',
                'vehicle' => $reservation->vehicle?->display_name ?? '—',
                'days' => $reservation->days(),
                'estimate' => $this->money($reservation->estimated_total),
                'status' => $reservation->status->label(),
            ])->values()->all(),
            'summary' => array_merge(
                $counts,
                [
                    'Reservations in range' => $reservations->count(),
                    'Estimated value' => $this->money($reservations->sum('estimated_total')),
                ],
            ),
        ];
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function rentalsReport(Carbon $from, Carbon $to): array
    {
        $rentals = Rental::query()
            ->with(['customer.user', 'vehicle'])
            ->whereBetween('start_date', [$from, $to])
            ->orderBy('start_date')
            ->get();

        $totals = 0.0;
        $collected = 0.0;
        $overdue = 0;

        $rows = $rentals->map(function (Rental $rental) use (&$totals, &$collected, &$overdue) {
            $paid = $rental->paidAmount();
            $balance = $rental->balanceDue();
            $totals += (float) $rental->total_amount;
            $collected += $paid;

            if ($rental->status === RentalStatus::Active && $rental->end_date->lt(today())) {
                $overdue++;
            }

            return [
                'start' => $rental->start_date->format('d M Y'),
                'end' => $rental->end_date->format('d M Y'),
                'customer' => $rental->customer?->display_name ?? '—',
                'vehicle' => $rental->vehicle?->display_name ?? '—',
                'days' => $rental->days,
                'total' => $this->money($rental->total_amount),
                'collected' => $this->money($paid),
                'outstanding' => $this->money($balance),
                'status' => $rental->status->label(),
            ];
        })->values()->all();

        return [
            'title' => 'Rental income report',
            'columns' => [
                'start' => 'Start',
                'end' => 'End',
                'customer' => 'Customer',
                'vehicle' => 'Vehicle',
                'days' => 'Days',
                'total' => 'Total',
                'collected' => 'Collected',
                'outstanding' => 'Outstanding',
                'status' => 'Status',
            ],
            'rows' => $rows,
            'summary' => [
                'Hires in range' => count($rows),
                'Hire days' => (int) $rentals->sum('days'),
                'Billed' => $this->money($totals),
                'Collected' => $this->money($collected),
                'Outstanding' => $this->money(max(0, $totals - $collected)),
                'Still overdue' => $overdue,
            ],
        ];
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function paymentsReport(Carbon $from, Carbon $to): array
    {
        $payments = Payment::query()
            ->with(['rental.customer.user'])
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('paid_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->orderBy('paid_at')
            ->orderBy('created_at')
            ->get();

        $counted = $this->counted($payments);

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $total = (float) $counted->where('payment_method', $method)->sum('amount');

            if ($total > 0) {
                $byMethod[$method->label()] = $this->money($total);
            }
        }

        return [
            'title' => 'Payments report',
            'columns' => [
                'date' => 'Date',
                'receipt' => 'Receipt',
                'customer' => 'Customer',
                'amount' => 'Amount',
                'method' => 'Method',
                'reference' => 'Reference',
                'status' => 'Status',
                'taken_by' => 'Taken by',
            ],
            'rows' => $payments->map(fn (Payment $payment) => [
                'date' => ($payment->paid_at ?? $payment->created_at)->format('d M Y'),
                'receipt' => $payment->receipt_number,
                'customer' => $payment->rental?->customer?->display_name ?? '—',
                'amount' => $this->money($payment->amount),
                'method' => $payment->payment_method->label(),
                'reference' => $payment->transaction_reference ?? '—',
                'status' => $payment->status->label(),
                'taken_by' => $payment->recordedBy?->name ?? '—',
            ])->values()->all(),
            'summary' => array_merge(
                $byMethod,
                [
                    'Payments in range' => $payments->count(),
                    'Total collected' => $this->money($counted->sum('amount')),
                ],
            ),
        ];
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function customersReport(Carbon $from, Carbon $to): array
    {
        $accounts = User::query()
            ->where('role', UserRole::Customer)
            ->whereBetween('created_at', [$from, $to])
            ->with('customer')
            ->orderBy('created_at')
            ->get();

        $customerIds = $accounts->pluck('customer.id')->filter()->values();

        $hires = Rental::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, count(*) aggregate')
            ->groupBy('customer_id')
            ->pluck('aggregate', 'customer_id');

        $bookings = Reservation::query()
            ->whereIn('customer_id', $customerIds)
            ->selectRaw('customer_id, count(*) aggregate')
            ->groupBy('customer_id')
            ->pluck('aggregate', 'customer_id');

        $rows = $accounts->map(function (User $account) use ($hires, $bookings) {
            $customerId = $account->customer?->id;

            return [
                'registered' => $account->created_at->format('d M Y'),
                'name' => $account->name,
                'email' => $account->email,
                'phone' => $account->phone ?? '—',
                'city' => $account->customer?->city ?? '—',
                'licence' => filled($account->customer?->driving_license_no) ? 'On file' : 'Missing',
                'hires' => (int) ($hires[$customerId] ?? 0),
                'bookings' => (int) ($bookings[$customerId] ?? 0),
            ];
        })->values()->all();

        return [
            'title' => 'New customer registrations',
            'columns' => [
                'registered' => 'Registered',
                'name' => 'Name',
                'email' => 'Email',
                'phone' => 'Phone',
                'city' => 'City',
                'licence' => 'Licence',
                'hires' => 'Hires',
                'bookings' => 'Reservations',
            ],
            'rows' => $rows,
            'summary' => [
                'New accounts' => count($rows),
                'Active accounts' => $accounts->where('is_active', true)->count(),
                'Licences missing' => $accounts->filter(
                    fn (User $account) => blank($account->customer?->driving_license_no)
                )->count(),
                'Hires by new customers' => (int) $hires->sum(),
            ],
        ];
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function returnsReport(Carbon $from, Carbon $to): array
    {
        $returns = VehicleReturn::query()
            ->with(['rental.customer.user', 'vehicle', 'processedBy'])
            ->whereBetween('returned_at', [$from, $to])
            ->orderBy('returned_at')
            ->get();

        $conditionCounts = [];
        foreach (VehicleCondition::cases() as $condition) {
            $count = $returns->where('condition', $condition)->count();

            if ($count > 0) {
                $conditionCounts[$condition->label()] = $count;
            }
        }

        return [
            'title' => 'Vehicle return report',
            'columns' => [
                'returned' => 'Returned at',
                'vehicle' => 'Vehicle',
                'customer' => 'Customer',
                'condition' => 'Condition',
                'mileage' => 'Mileage',
                'damage' => 'Damage notes',
                'charges' => 'Damage charges',
                'processed_by' => 'Processed by',
            ],
            'rows' => $returns->map(fn (VehicleReturn $return) => [
                'returned' => $return->returned_at->format('d M Y H:i'),
                'vehicle' => $return->vehicle?->display_name ?? '—',
                'customer' => $return->rental?->customer?->display_name ?? '—',
                'condition' => $return->condition->label(),
                'mileage' => $return->mileage_at_return ?? '—',
                'damage' => $return->damage_notes ?? '—',
                'charges' => $this->money($return->additional_charges),
                'processed_by' => $return->processedBy?->name ?? '—',
            ])->values()->all(),
            'summary' => array_merge(
                $conditionCounts,
                [
                    'Returns in range' => $returns->count(),
                    'Damage charges' => $this->money($returns->sum('additional_charges')),
                ],
            ),
        ];
    }

    /**
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function maintenanceReport(Carbon $from, Carbon $to): array
    {
        $jobs = Maintenance::query()
            ->with('vehicle')
            ->whereBetween('start_date', [$from, $to])
            ->orderBy('start_date')
            ->get();

        $perVehicle = $jobs->filter(fn (Maintenance $job) => (float) $job->cost > 0)
            ->groupBy('vehicle_id')
            ->map(fn ($group) => (float) $group->sum('cost'))
            ->sortDesc();

        $summary = [
            'Jobs in range' => $jobs->count(),
            'Open jobs' => $jobs->filter(fn (Maintenance $job) => in_array(
                $job->status,
                [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress],
                true,
            ))->count(),
            'Total cost' => $this->money($jobs->sum('cost')),
        ];

        foreach ($perVehicle->take(10) as $vehicleId => $cost) {
            $plate = Vehicle::query()->whereKey($vehicleId)->value('plate_number') ?? ('#'.$vehicleId);
            $summary['Cost — '.$plate] = $this->money($cost);
        }

        return [
            'title' => 'Maintenance cost report',
            'columns' => [
                'start' => 'Start',
                'end' => 'End',
                'vehicle' => 'Vehicle',
                'title' => 'Work',
                'provider' => 'Service provider',
                'status' => 'Status',
                'cost' => 'Cost',
            ],
            'rows' => $jobs->map(fn (Maintenance $job) => [
                'start' => $job->start_date->format('d M Y'),
                'end' => $job->end_date?->format('d M Y') ?? 'Ongoing',
                'vehicle' => $job->vehicle?->display_name ?? '—',
                'title' => $job->title,
                'provider' => $job->service_provider ?? '—',
                'status' => $job->status->label(),
                'cost' => $this->money($job->cost),
            ])->values()->all(),
            'summary' => $summary,
        ];
    }

    /**
     * FR13: revenue billed, collected and outstanding per month in the period,
     * with the money already banked broken down by payment method.
     *
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function revenueReport(Carbon $from, Carbon $to): array
    {
        $rentals = Rental::query()
            ->whereBetween('start_date', [$from, $to])
            ->where('status', '!=', RentalStatus::Cancelled->value)
            ->get(['id', 'start_date', 'total_amount']);

        $payments = Payment::query()
            ->where(function ($query) use ($from, $to) {
                $query->whereBetween('paid_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('paid_at')->whereBetween('created_at', [$from, $to]);
                    });
            })
            ->get(['amount', 'payment_method', 'status', 'paid_at', 'created_at']);

        $counted = $this->counted($payments);

        // Bucket billed and collected money by calendar month across the range.
        $months = [];
        $cursor = $from->copy()->startOfMonth();
        while ($cursor->lte($to)) {
            $months[$cursor->format('Y-m')] = $cursor->format('M Y');
            $cursor->addMonthNoOverflow();
        }

        $billedByMonth = $rentals->groupBy(fn (Rental $rental) => $rental->start_date->format('Y-m'))->map(fn ($g) => (float) $g->sum('total_amount'));
        $collectedByMonth = $counted->groupBy(fn (Payment $p) => ($p->paid_at ?? $p->created_at)->format('Y-m'))->map(fn ($g) => (float) $g->sum('amount'));

        $rows = [];
        foreach ($months as $key => $label) {
            $billed = (float) ($billedByMonth[$key] ?? 0);
            $collected = (float) ($collectedByMonth[$key] ?? 0);
            $rows[] = [
                'month' => $label,
                'billed' => $this->money($billed),
                'collected' => $this->money($collected),
                'outstanding' => $this->money(max(0, $billed - $collected)),
            ];
        }

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $total = (float) $counted->where('payment_method', $method)->sum('amount');
            if ($total > 0) {
                $byMethod[$method->label()] = $this->money($total);
            }
        }

        $billedTotal = (float) $rentals->sum('total_amount');
        $collectedTotal = (float) $counted->sum('amount');

        return [
            'title' => 'Revenue report',
            'columns' => [
                'month' => 'Month',
                'billed' => 'Billed',
                'collected' => 'Collected',
                'outstanding' => 'Outstanding',
            ],
            'rows' => $rows,
            'summary' => array_merge($byMethod, [
                'Billed in range' => $this->money($billedTotal),
                'Collected in range' => $this->money($collectedTotal),
                'Outstanding in range' => $this->money(max(0, $billedTotal - $collectedTotal)),
            ]),
        ];
    }

    /**
     * FR13: how intensively each vehicle was hired out during the period - the
     * days on hire (clipped to the range), the resulting utilization rate and
     * the revenue the vehicle billed.
     *
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function utilizationReport(Carbon $from, Carbon $to): array
    {
        $rentals = Rental::query()
            ->where('status', '!=', RentalStatus::Cancelled->value)
            ->where('start_date', '<=', $to)
            ->where('end_date', '>=', $from)
            ->get(['vehicle_id', 'start_date', 'end_date', 'total_amount']);

        $availableDays = $from->copy()->startOfDay()->diffInDays($to->copy()->endOfDay()) + 1;

        $perVehicle = $rentals->groupBy('vehicle_id')->map(fn ($group) => [
            'hires' => $group->count(),
            'days' => $group->sum(fn (Rental $r) => $this->overlapDays($r->start_date, $r->end_date, $from, $to)),
            'revenue' => (float) $group->sum('total_amount'),
        ]);

        $rows = [];
        $totalDays = 0;
        $totalRevenue = 0.0;

        foreach (Vehicle::query()->orderBy('plate_number')->get() as $vehicle) {
            $stats = $perVehicle->get($vehicle->id);
            if ($stats === null) {
                continue;
            }

            $utilization = $availableDays > 0 ? round(($stats['days'] / $availableDays) * 100) : 0;
            $totalDays += $stats['days'];
            $totalRevenue += $stats['revenue'];

            $rows[] = [
                'vehicle' => trim($vehicle->make.' '.$vehicle->model),
                'plate' => $vehicle->plate_number,
                'type' => $vehicle->type->label(),
                'hires' => $stats['hires'],
                'days' => $stats['days'],
                'utilization' => $utilization.'%',
                'revenue' => $this->money($stats['revenue']),
            ];
        }

        $fleetSize = Vehicle::query()->count();
        $avg = $fleetSize > 0 && $availableDays > 0
            ? round((($totalDays / $availableDays) / $fleetSize) * 100)
            : 0;

        return [
            'title' => 'Fleet utilization report',
            'columns' => [
                'vehicle' => 'Vehicle',
                'plate' => 'Plate',
                'type' => 'Type',
                'hires' => 'Hires',
                'days' => 'Days on hire',
                'utilization' => 'Utilization',
                'revenue' => 'Revenue',
            ],
            'rows' => $rows,
            'summary' => [
                'Period days' => $availableDays,
                'Fleet size' => $fleetSize,
                'Vehicles hired' => $rows === [] ? 0 : count($rows),
                'Total hire days' => $totalDays,
                'Fleet utilization' => $avg.'%',
                'Revenue from hire' => $this->money($totalRevenue),
            ],
        ];
    }

    /**
     * FR13: split VAT-inclusive hire revenue into net and output tax for the
     * period, at the configured rate.
     *
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function taxReport(Carbon $from, Carbon $to): array
    {
        $rate = (float) config('crms.vat_rate', 0.18);
        $divisor = 1 + $rate;

        $rentals = Rental::query()
            ->with(['customer.user', 'vehicle'])
            ->whereBetween('start_date', [$from, $to])
            ->where('status', '!=', RentalStatus::Cancelled->value)
            ->orderBy('start_date')
            ->get();

        $grossTotal = 0.0;

        $rows = $rentals->map(function (Rental $rental) use ($divisor, $rate, &$grossTotal) {
            $gross = (float) $rental->total_amount;
            $grossTotal += $gross;
            $net = $divisor > 0 ? $gross / $divisor : $gross;
            $vat = $gross - $net;

            return [
                'date' => $rental->start_date->format('d M Y'),
                'customer' => $rental->customer?->display_name ?? '—',
                'vehicle' => $rental->vehicle?->display_name ?? '—',
                'gross' => $this->money($gross),
                'net' => $this->money($net),
                'vat' => $this->money($vat),
            ];
        })->values()->all();

        $netTotal = $divisor > 0 ? $grossTotal / $divisor : $grossTotal;

        return [
            'title' => 'Tax (VAT) report',
            'columns' => [
                'date' => 'Start',
                'customer' => 'Customer',
                'vehicle' => 'Vehicle',
                'gross' => 'Gross',
                'net' => 'Net',
                'vat' => 'VAT',
            ],
            'rows' => $rows,
            'summary' => [
                'VAT rate' => round($rate * 100).'%',
                'Taxable hires' => count($rows),
                'Gross revenue' => $this->money($grossTotal),
                'Net revenue' => $this->money($netTotal),
                'Output VAT' => $this->money($grossTotal - $netTotal),
            ],
        ];
    }

    /**
     * FR13: hires still open past their drop-off date, how overdue each is and
     * what balance is outstanding.
     *
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function overdueReport(Carbon $from, Carbon $to): array
    {
        $today = Carbon::today();

        $rentals = Rental::query()
            ->with(['customer.user', 'vehicle'])
            ->where(function ($query) use ($today) {
                $query->where('status', RentalStatus::Overdue->value)
                    ->orWhere(function ($inner) use ($today) {
                        $inner->where('status', RentalStatus::Active->value)
                            ->whereDate('end_date', '<', $today->toDateString());
                    });
            })
            ->orderBy('end_date')
            ->get();

        $outstanding = 0.0;
        $worst = 0;

        $rows = $rentals->map(function (Rental $rental) use ($today, &$outstanding, &$worst) {
            $daysOverdue = (int) round(abs($today->diffInDays($rental->end_date)));
            $balance = $rental->balanceDue();
            $outstanding += $balance;
            $worst = max($worst, $daysOverdue);

            return [
                'end' => $rental->end_date->format('d M Y'),
                'days_overdue' => $daysOverdue,
                'customer' => $rental->customer?->display_name ?? '—',
                'vehicle' => $rental->vehicle?->display_name ?? '—',
                'total' => $this->money($rental->total_amount),
                'collected' => $this->money($rental->paidAmount()),
                'outstanding' => $this->money($balance),
                'status' => $rental->status->label(),
            ];
        })->values()->all();

        return [
            'title' => 'Overdue rentals report',
            'columns' => [
                'end' => 'Drop-off',
                'days_overdue' => 'Days overdue',
                'customer' => 'Customer',
                'vehicle' => 'Vehicle',
                'total' => 'Total',
                'collected' => 'Collected',
                'outstanding' => 'Outstanding',
                'status' => 'Status',
            ],
            'rows' => $rows,
            'summary' => [
                'Overdue rentals' => count($rows),
                'Total outstanding' => $this->money($outstanding),
                'Longest overdue' => $worst.' days',
            ],
        ];
    }

    /**
     * UC25 support: the traffic fines recorded in the period and how liability
     * split between customers (billed) and the company (absorbed).
     *
     * @return array{title: string, columns: array<string, string>, rows: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    private function finesReport(Carbon $from, Carbon $to): array
    {
        $fines = TrafficFine::query()
            ->with(['rental.customer.user', 'vehicle', 'decider'])
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->get();

        $billed = 0.0;
        $absorbed = 0.0;
        $pending = 0;

        foreach ($fines as $fine) {
            if ($fine->status === FineStatus::Billed) {
                $billed += (float) $fine->amount;
            } elseif ($fine->status === FineStatus::Absorbed) {
                $absorbed += (float) $fine->amount;
            } else {
                $pending++;
            }
        }

        return [
            'title' => 'Traffic fines report',
            'columns' => [
                'recorded' => 'Recorded',
                'rental' => 'Rental',
                'vehicle' => 'Vehicle',
                'offence' => 'Offence',
                'reference' => 'Reference',
                'amount' => 'Amount',
                'liability' => 'Liability',
                'status' => 'Status',
                'decided_by' => 'Decided by',
            ],
            'rows' => $fines->map(fn (TrafficFine $fine) => [
                'recorded' => $fine->created_at->format('d M Y'),
                'rental' => '#'.$fine->rental_id,
                'vehicle' => $fine->vehicle?->display_name ?? $fine->rental?->vehicle?->display_name ?? '—',
                'offence' => $fine->description ?? '—',
                'reference' => $fine->reference ?? '—',
                'amount' => $this->money($fine->amount),
                'liability' => $fine->liability?->label() ?? 'Unassigned',
                'status' => $fine->status->label(),
                'decided_by' => $fine->decider?->name ?? '—',
            ])->values()->all(),
            'summary' => [
                'Fines in range' => $fines->count(),
                'Still pending' => $pending,
                'Billed to customers' => $this->money($billed),
                'Absorbed by company' => $this->money($absorbed),
            ],
        ];
    }

    /**
     * Whole days a rental period overlaps the report range (inclusive), never
     * negative when the hire sits outside the window.
     */
    private function overlapDays(Carbon $start, Carbon $end, Carbon $from, Carbon $to): int
    {
        $latestStart = $start->copy()->startOfDay()->max($from->copy()->startOfDay());
        $earliestEnd = $end->copy()->startOfDay()->min($to->copy()->startOfDay());

        if ($earliestEnd->lt($latestStart)) {
            return 0;
        }

        return $latestStart->diffInDays($earliestEnd) + 1;
    }

    private function streamCsv(array $report): StreamedResponse
    {
        $columns = $report['columns'];
        $rows = $report['rows'];
        $name = strtolower(str_replace(' ', '-', $report['title'])).'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($columns, $rows): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, array_values($columns));

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn (mixed $value): string => $this->safeCsvCell((string) $value), array_values($row)));
            }

            fclose($handle);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Spreadsheet applications run a cell that starts with a formula marker, so free-text
     * values such as customer names are forced to be literal before they reach the file.
     */
    private function safeCsvCell(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(Request $request): array
    {
        $from = $this->parseDate($request->input('from'), now()->startOfMonth())->startOfDay();
        $to = $this->parseDate($request->input('to'), now()->endOfMonth())->endOfDay();

        return $from->gt($to) ? [$to->copy(), $from->copy()] : [$from, $to];
    }

    private function parseDate(mixed $value, Carbon $fallback): Carbon
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || strtotime($value) === false) {
            return $fallback;
        }

        return Carbon::createFromFormat('Y-m-d', $value);
    }

    private function money(mixed $amount): string
    {
        return number_format((float) $amount, 0).' TZS';
    }

    private function counted(Collection $payments): Collection
    {
        return $payments->filter(fn (Payment $payment) => $payment->status !== PaymentStatus::Refunded);
    }
}

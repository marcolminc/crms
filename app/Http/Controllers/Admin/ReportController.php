<?php

namespace App\Http\Controllers\Admin;

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

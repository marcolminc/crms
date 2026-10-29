@extends('layouts.app')

@section('title', 'Overview')

@php
    $monthStart = now()->startOfMonth()->format('Y-m-d');
    $monthEnd = now()->endOfMonth()->format('Y-m-d');
    $today = now()->format('Y-m-d');
    $isAdmin = $isAdmin ?? false;
@endphp

@section('content')
    @if ($isAdmin)
        <x-page-header title="Administration overview" :description="'System and fleet oversight for '.config('app.name').'.'">
            <x-slot:actions>
                <a href="{{ route('admin.users.index') }}"
                   class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Manage users
                </a>
                <a href="{{ route('admin.vehicles.index') }}"
                   class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Manage vehicles
                </a>
            </x-slot:actions>
        </x-page-header>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <a href="{{ route('admin.users.index') }}"
               class="block rounded-xl transition hover:shadow-md">
                <x-stat label="User accounts" :value="$totalUsers" hint="Everyone who can sign in" />
            </a>
            <a href="{{ route('admin.users.index') }}"
               class="block rounded-xl transition hover:shadow-md">
                <x-stat label="Inactive accounts" :value="$inactiveUsers" hint="Locked out of the system" />
            </a>
            <a href="{{ route('admin.customers.index') }}"
               class="block rounded-xl transition hover:shadow-md">
                <x-stat label="Customers" :value="$totalCustomers" hint="On record to date" />
            </a>
            <a href="{{ route('admin.customers.index') }}"
               class="block rounded-xl transition hover:shadow-md">
                <x-stat label="New customers this month" :value="$newCustomersThisMonth" hint="Registered since the 1st" />
            </a>
        </div>

        <div class="mt-6 grid gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <x-card title="Accounts by role">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="pb-2 pr-4">Role</th>
                                <th class="pb-2 pr-4">Accounts</th>
                                <th class="pb-2">Manage</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach (App\Enums\UserRole::staff() as $role)
                                <tr>
                                    <td class="py-3 pr-4 font-medium text-slate-900">{{ $role->label() }}</td>
                                    <td class="py-3 pr-4">{{ $usersByRole[$role->value] ?? 0 }}</td>
                                    <td class="py-3">
                                        <a href="{{ route('admin.users.index', ['role' => $role->value]) }}"
                                           class="text-sm font-medium text-emerald-700 hover:underline">View accounts</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>

                <x-card title="Fleet by status">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="pb-2 pr-4">Status</th>
                                <th class="pb-2 pr-4">Vehicles</th>
                                <th class="pb-2">Open list</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach (App\Enums\VehicleStatus::cases() as $status)
                                <tr>
                                    <td class="py-3 pr-4"><x-badge :value="$status->value" /></td>
                                    <td class="py-3 pr-4 font-medium text-slate-900">{{ $vehicleCounts[$status->value] ?? 0 }}</td>
                                    <td class="py-3">
                                        <a href="{{ route('admin.vehicles.index', ['status' => $status->value]) }}"
                                           class="text-sm font-medium text-emerald-700 hover:underline">View vehicles</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            </div>

            <div class="space-y-6">
                <x-card title="Reports">
                    <ul class="space-y-2 text-sm">
                        @foreach (['fleet' => 'Fleet status', 'reservations' => 'Reservations', 'rentals' => 'Rentals', 'payments' => 'Payments', 'customers' => 'Customers', 'returns' => 'Returns', 'maintenance' => 'Maintenance'] as $key => $label)
                            <li>
                                <a href="{{ route('admin.reports.show', $key) }}" class="font-medium text-emerald-700 hover:underline">{{ $label }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('admin.reports.index') }}" class="mt-4 inline-block text-sm text-slate-500 hover:text-slate-800">All report descriptions</a>
                </x-card>

                <x-card title="Manage the system">
                    <ul class="space-y-2 text-sm">
                        <li><a href="{{ route('admin.users.index') }}" class="font-medium text-emerald-700 hover:underline">User accounts &amp; roles</a></li>
                        <li><a href="{{ route('admin.vehicles.index') }}" class="font-medium text-emerald-700 hover:underline">Vehicle fleet</a></li>
                        <li><a href="{{ route('admin.customers.index') }}" class="font-medium text-emerald-700 hover:underline">Customer records</a></li>
                    </ul>
                </x-card>

                <x-card title="My latest alerts">
                    @if ($alerts->isEmpty())
                        <p class="text-sm text-slate-600">No alerts yet.</p>
                    @else
                        <ul class="space-y-4">
                            @foreach ($alerts as $alert)
                                <li class="text-sm">
                                    <span @class([
                                        'block font-medium',
                                        'text-slate-900' => $alert->read_at === null,
                                        'text-slate-500' => $alert->read_at !== null,
                                    ])>{{ $alert->title }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $alert->created_at->format('d M Y') }}</span>
                                    @if ($alert->link)
                                        <a href="{{ $alert->link }}" class="mt-1 inline-block text-xs font-medium text-emerald-700 hover:underline">Open</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    <a href="{{ route('notifications.index') }}" class="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline">
                        View all alerts
                    </a>
                </x-card>
            </div>
        </div>
    @else
        <x-page-header title="Staff overview" :description="'What needs attention at '.config('app.name').' on '.now()->format('d M Y').'.'">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Start a rental
            </a>
            <a href="{{ route('admin.rentals.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Record a payment
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <a href="{{ route('admin.vehicles.index', ['status' => App\Enums\VehicleStatus::Available->value]) }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Vehicles available" :value="$vehicleCounts[App\Enums\VehicleStatus::Available->value] ?? 0" hint="Ready to hire today" />
        </a>
        <a href="{{ route('admin.reservations.index', ['status' => App\Enums\ReservationStatus::Pending->value]) }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Pending reservations" :value="$pendingReservations" hint="Waiting for a decision" />
        </a>
        <a href="{{ route('admin.rentals.index') }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Open rentals" :value="$openRentals->count()" hint="Active or overdue hires" />
        </a>
        <a href="{{ route('admin.rentals.index', ['overdue' => 1]) }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Overdue" :value="$overdueRentals->count()" hint="Past the end date, still out" />
        </a>
        <a href="{{ route('admin.rentals.index', ['from' => $today, 'to' => $today]) }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Starting today" :value="$startingToday->count()" hint="Handover scheduled" />
        </a>
        <a href="{{ route('admin.reservations.index', ['status' => App\Enums\ReservationStatus::Confirmed->value]) }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Pick-ups due" :value="$pickupsDue->count()" hint="Confirmed, not handed over" />
        </a>
        <a href="{{ route('admin.rentals.index', ['from' => $today, 'to' => $today]) }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Due back today" :value="$dueBackToday->count()" hint="Returns to process" />
        </a>
        <a href="{{ route('admin.payments.index', ['from' => $monthStart, 'to' => $monthEnd]) }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Collected this month" :value="number_format($monthRevenue, 0).' TZS'" hint="Receipts paid since the 1st" />
        </a>
        <a href="{{ route('admin.payments.index') }}"
           class="block rounded-xl transition hover:shadow-md">
            <x-stat label="Outstanding balance" :value="number_format($outstanding, 0).' TZS'" hint="Still owed on open rentals" />
        </a>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Fleet by status">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="pb-2 pr-4">Status</th>
                            <th class="pb-2 pr-4">Vehicles</th>
                            <th class="pb-2">Open list</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach (App\Enums\VehicleStatus::cases() as $status)
                            <tr>
                                <td class="py-3 pr-4"><x-badge :value="$status->value" /></td>
                                <td class="py-3 pr-4 font-medium text-slate-900">{{ $vehicleCounts[$status->value] ?? 0 }}</td>
                                <td class="py-3">
                                    <a href="{{ route('admin.vehicles.index', ['status' => $status->value]) }}"
                                       class="text-sm font-medium text-emerald-700 hover:underline">View vehicles</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>

            @if ($pickupsDue->isNotEmpty())
                <x-card title="Pick-ups due &mdash; convert to rental">
                    <p class="mb-3 text-sm text-slate-500">Confirmed bookings ready to be handed over. Convert one to start the hire and record the pick-up handover.</p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="pb-2 pr-4">Customer</th>
                                <th class="pb-2 pr-4">Vehicle</th>
                                <th class="pb-2 pr-4">Pick-up</th>
                                <th class="pb-2 pr-4">Until</th>
                                <th class="pb-2">Convert</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($pickupsDue as $due)
                                <tr>
                                    <td class="py-3 pr-4">{{ $due->customer?->display_name ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $due->vehicle?->display_name ?? '—' }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap text-slate-600">{{ $due->start_date->format('d M Y') }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap text-slate-600">{{ $due->end_date->format('d M Y') }}</td>
                                    <td class="py-3 whitespace-nowrap">
                                        <a href="{{ route('admin.reservations.show', $due) }}"
                                           class="text-sm font-medium text-slate-500 hover:underline">Open</a>
                                        <a href="{{ route('admin.rentals.create', ['reservation' => $due->id]) }}"
                                           class="ml-2 text-sm font-medium text-emerald-700 hover:underline">Convert to rental</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif

            <x-card title="Rentals starting today">
                @if ($startingToday->isEmpty())
                    <p class="text-sm text-slate-600">No vehicle leaves the yard today.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="pb-2 pr-4">Customer</th>
                                <th class="pb-2 pr-4">Vehicle</th>
                                <th class="pb-2 pr-4">Until</th>
                                <th class="pb-2 pr-4">Total</th>
                                <th class="pb-2">Hire</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($startingToday as $rental)
                                <tr>
                                    <td class="py-3 pr-4">{{ $rental->customer?->display_name ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $rental->vehicle?->display_name ?? '—' }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap text-slate-600">{{ $rental->end_date->format('d M Y') }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ number_format($rental->total_amount, 0) }} TZS</td>
                                    <td class="py-3 whitespace-nowrap">
                                        <a href="{{ route('admin.rentals.show', $rental) }}"
                                           class="text-sm font-medium text-emerald-700 hover:underline">Open</a>
                                        <a href="{{ route('admin.payments.create', $rental) }}"
                                           class="ml-2 text-sm font-medium text-emerald-700 hover:underline">Payment</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            <x-card title="Due back today">
                @if ($dueBackToday->isEmpty())
                    <p class="text-sm text-slate-600">Nothing is scheduled to come back today.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="pb-2 pr-4">Vehicle</th>
                                <th class="pb-2 pr-4">Customer</th>
                                <th class="pb-2 pr-4">Balance</th>
                                <th class="pb-2">Return</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($dueBackToday as $rental)
                                <tr>
                                    <td class="py-3 pr-4 font-medium text-slate-900">{{ $rental->vehicle?->plate_number ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $rental->customer?->display_name ?? '—' }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap">{{ number_format($rental->balanceDue(), 0) }} TZS</td>
                                    <td class="py-3 whitespace-nowrap">
                                        <a href="{{ route('admin.returns.create', $rental) }}"
                                           class="text-sm font-medium text-emerald-700 hover:underline">Process return</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            @if ($overdueRentals->isNotEmpty())
                <x-card title="Late rentals">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                <th class="pb-2 pr-4">Vehicle</th>
                                <th class="pb-2 pr-4">Customer</th>
                                <th class="pb-2 pr-4">Was due</th>
                                <th class="pb-2">Follow up</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($overdueRentals as $rental)
                                <tr>
                                    <td class="py-3 pr-4 font-medium text-slate-900">{{ $rental->vehicle?->plate_number ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-slate-600">{{ $rental->customer?->display_name ?? '—' }}</td>
                                    <td class="py-3 pr-4 whitespace-nowrap text-rose-700">{{ $rental->end_date->format('d M Y') }}</td>
                                    <td class="py-3 whitespace-nowrap">
                                        <a href="{{ route('admin.rentals.show', $rental) }}"
                                           class="text-sm font-medium text-emerald-700 hover:underline">Open rental</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Reports">
                <ul class="space-y-2 text-sm">
                    @foreach (['fleet' => 'Fleet status', 'reservations' => 'Reservations', 'rentals' => 'Rentals', 'payments' => 'Payments', 'customers' => 'Customers', 'returns' => 'Returns', 'maintenance' => 'Maintenance'] as $key => $label)
                        <li>
                            <a href="{{ route('admin.reports.show', $key) }}" class="font-medium text-emerald-700 hover:underline">{{ $label }}</a>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.reports.index') }}" class="mt-4 inline-block text-sm text-slate-500 hover:text-slate-800">All report descriptions</a>
            </x-card>

            <x-card title="My latest alerts">
                @if ($alerts->isEmpty())
                    <p class="text-sm text-slate-600">No alerts yet.</p>
                @else
                    <ul class="space-y-4">
                        @foreach ($alerts as $alert)
                            <li class="text-sm">
                                <span @class([
                                    'block font-medium',
                                    'text-slate-900' => $alert->read_at === null,
                                    'text-slate-500' => $alert->read_at !== null,
                                ])>{{ $alert->title }}</span>
                                <span class="mt-0.5 block text-xs text-slate-500">{{ $alert->created_at->format('d M Y') }}</span>
                                @if ($alert->link)
                                    <a href="{{ $alert->link }}" class="mt-1 inline-block text-xs font-medium text-emerald-700 hover:underline">Open</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                <a href="{{ route('notifications.index') }}" class="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline">
                    View all alerts
                </a>
            </x-card>

            <x-card title="Quick links">
                <ul class="space-y-2 text-sm">
                    <li><a href="{{ route('admin.reservations.index') }}" class="font-medium text-emerald-700 hover:underline">Reservations to review</a></li>
                    <li><a href="{{ route('admin.rentals.index') }}" class="font-medium text-emerald-700 hover:underline">All rentals</a></li>
                    <li><a href="{{ route('admin.payments.index') }}" class="font-medium text-emerald-700 hover:underline">All receipts</a></li>
                    <li><a href="{{ route('admin.maintenances.index') }}" class="font-medium text-emerald-700 hover:underline">Workshop jobs</a></li>
                    <li><a href="{{ route('admin.customers.index') }}" class="font-medium text-emerald-700 hover:underline">Customer records</a></li>
                </ul>
            </x-card>
        </div>
    </div>
    @endif
@endsection

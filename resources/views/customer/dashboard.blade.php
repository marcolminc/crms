@extends('layouts.app')

@section('title', 'My overview')

@section('content')
    @php
        $header = 'Welcome back, '.$user->name;
        $subHeader = $customer ? 'Your bookings, rentals and alerts at Land Valley\'s Travels.' : 'Your account is not linked to a customer record yet, so no bookings are shown.';
    @endphp

    <x-page-header :title="$header" :description="$subHeader">
        <x-slot:actions>
            <a href="{{ route('customer.reservations.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                New reservation
            </a>
            <a href="{{ route('vehicles.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Browse vehicles
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat label="Live reservations" :value="$reservations->count()" hint="Pending or confirmed" />
        <x-stat label="Open rentals" :value="$rentals->count()" hint="Active or overdue" />
        <x-stat label="Completed rentals" :value="$completedCount" hint="All time" />
        <x-stat label="Total spend" :value="number_format($totalSpend, 0).' TZS'" hint="Completed rentals" />
        <x-stat label="Profile complete" :value="$profile['percent'].'%'" :hint="$profile['percent'] === 100 ? 'Everything we need is on file' : 'Tap profile to finish'" />
    </div>

    @if ($profile['percent'] < 100)
        <x-card title="Finish your profile" class="mt-6">
            <p class="text-sm text-slate-600">
                Staff verify these details before releasing a vehicle. Still missing:
                <span class="font-medium text-slate-800">{{ implode(', ', $profile['missing']) }}</span>.
            </p>
            <a href="{{ route('profile.edit') }}"
               class="mt-4 inline-block rounded-lg border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                Update profile
            </a>
        </x-card>
    @endif

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Live reservations">
                @if ($reservations->isEmpty())
                    <p class="text-sm text-slate-600">You have no pending or confirmed reservations.</p>
                    <a href="{{ route('vehicles.index') }}" class="mt-3 inline-block text-sm font-medium text-emerald-700 hover:underline">
                        Find a vehicle
                    </a>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="pb-2 pr-4">Vehicle</th>
                                    <th class="pb-2 pr-4">Period</th>
                                    <th class="pb-2 pr-4">Estimate</th>
                                    <th class="pb-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($reservations as $reservation)
                                    <tr>
                                        <td class="py-3 pr-4">
                                            <a href="{{ route('customer.reservations.show', $reservation) }}"
                                               class="font-medium text-slate-900 hover:text-emerald-700">
                                                {{ $reservation->vehicle?->display_name ?? 'Vehicle unavailable' }}
                                            </a>
                                        </td>
                                        <td class="py-3 pr-4 whitespace-nowrap text-slate-600">
                                            {{ $reservation->start_date->format('d M Y') }} &ndash; {{ $reservation->end_date->format('d M Y') }}
                                        </td>
                                        <td class="py-3 pr-4 whitespace-nowrap text-slate-600">
                                            {{ number_format($reservation->estimated_total, 0) }} TZS
                                        </td>
                                        <td class="py-3">
                                            <x-badge :value="$reservation->status->value" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('customer.reservations.index') }}" class="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline">
                        All my reservations
                    </a>
                @endif
            </x-card>

            <x-card title="Open rentals">
                @if ($rentals->isEmpty())
                    <p class="text-sm text-slate-600">You are not currently renting a vehicle.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="pb-2 pr-4">Vehicle</th>
                                    <th class="pb-2 pr-4">Due back</th>
                                    <th class="pb-2 pr-4">Total</th>
                                    <th class="pb-2 pr-4">Balance</th>
                                    <th class="pb-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rentals as $rental)
                                    <tr>
                                        <td class="py-3 pr-4">
                                            <a href="{{ route('customer.rentals.index') }}" class="font-medium text-slate-900 hover:text-emerald-700">
                                                {{ $rental->vehicle?->display_name ?? 'Vehicle unavailable' }}
                                            </a>
                                        </td>
                                        <td class="py-3 pr-4 whitespace-nowrap text-slate-600">{{ $rental->end_date->format('d M Y') }}</td>
                                        <td class="py-3 pr-4 whitespace-nowrap text-slate-600">
                                            {{ number_format($rental->total_amount, 0) }} TZS
                                        </td>
                                        <td class="py-3 pr-4 whitespace-nowrap text-slate-600">
                                            {{ number_format($rental->balanceDue(), 0) }} TZS
                                        </td>
                                        <td class="py-3">
                                            <x-badge :value="$rental->status->value" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <a href="{{ route('customer.rentals.index') }}" class="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline">
                        All my rentals
                    </a>
                @endif
            </x-card>
        </div>

        <x-card title="Latest alerts">
            @if ($alerts->isEmpty())
                <p class="text-sm text-slate-600">No alerts yet.</p>
            @else
                <ul class="space-y-4">
                    @foreach ($alerts as $alert)
                        <li @class(['text-sm', 'text-slate-500' => $alert->read_at !== null, 'text-slate-900' => $alert->read_at === null])>
                            <span class="block font-medium">{{ $alert->title }}</span>
                            <span class="mt-0.5 block text-xs text-slate-500">{{ $alert->created_at->format('d M Y') }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            <a href="{{ route('notifications.index') }}" class="mt-4 inline-block text-sm font-medium text-emerald-700 hover:underline">
                View all alerts
            </a>
        </x-card>
    </div>
@endsection

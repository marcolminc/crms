@extends('layouts.app')

@section('title', 'Driver workspace')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Driver workspace" description="Your assignments, trip logs and duty status." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="My assignments">
                @if ($assignments->isEmpty())
                    <p class="text-sm text-slate-500">You have no hire assignments yet. When the desk puts you on a rental it will show up here.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($assignments as $rental)
                            <li class="flex items-center justify-between gap-3 py-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $rental->vehicle?->display_name ?? 'Vehicle' }}</p>
                                    <p class="text-xs text-slate-500">
                                        Rental #{{ $rental->id }} &middot; {{ $rental->customer?->display_name ?? 'Customer' }}
                                        &middot; {{ $rental->start_date->format('d M Y') }} &rarr; {{ $rental->end_date->format('d M Y') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <x-badge :value="$rental->status->value" />
                                    <a href="{{ route('driver.rentals.show', $rental) }}"
                                       class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">Log trips</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            <x-card title="Recent trips">
                @if ($trips->isEmpty())
                    <p class="text-sm text-slate-500">No trips logged yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="py-2 pr-4">Date</th>
                                    <th class="py-2 pr-4">Rental</th>
                                    <th class="py-2 pr-4">Distance</th>
                                    <th class="py-2">Incidents</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($trips as $trip)
                                    <tr>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ $trip->trip_date->format('d M Y') }}</td>
                                        <td class="py-2 pr-4">
                                            <a href="{{ route('driver.rentals.show', $trip->rental_id) }}" class="text-emerald-700 hover:underline">
                                                #{{ $trip->rental_id }}
                                            </a>
                                        </td>
                                        <td class="py-2 pr-4">{{ $trip->distanceCovered() !== null ? number_format($trip->distanceCovered()).' km' : '—' }}</td>
                                        <td class="py-2 text-slate-600">{{ $trip->incident_notes ?: '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>

        <div>
            <x-card title="Duty status">
                <p class="mb-3 text-sm text-slate-600">
                    You are currently
                    <span class="font-semibold {{ $driver->is_on_duty ? 'text-emerald-700' : 'text-slate-500' }}">
                        {{ $driver->is_on_duty ? 'on duty' : 'off duty' }}
                    </span>.
                    Only on-duty drivers are easy to spot when the desk assigns a hire.
                </p>
                <form method="POST" action="{{ route('driver.availability') }}">
                    @csrf
                    @method('PUT')
                    <button type="submit"
                            class="w-full rounded-lg {{ $driver->is_on_duty ? 'border border-slate-300 text-slate-700 hover:bg-slate-50' : 'bg-emerald-600 text-white hover:bg-emerald-700' }} px-4 py-2 text-sm font-semibold shadow-sm">
                        {{ $driver->is_on_duty ? 'Mark me off duty' : 'Mark me on duty' }}
                    </button>
                </form>
            </x-card>
        </div>
    </div>
@endsection

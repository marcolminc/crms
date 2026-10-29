@extends('layouts.app')

@section('title', 'Trip log — Rental #'.$rental->id)

@php
    $inputClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header
        :title="'Rental #'.$rental->id"
        :description="($rental->vehicle?->display_name ?? 'Vehicle').' · '.$rental->start_date->format('d M Y').' → '.$rental->end_date->format('d M Y')">
        <x-slot:actions>
            <a href="{{ route('driver.dashboard') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to workspace</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Daily trip log">
                <p class="mb-3 text-sm text-slate-500">Record each working day: when you set off and finished, the odometer at both ends, and any incident on the day.</p>
                <form method="POST" action="{{ route('driver.rentals.trips.store', $rental) }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="trip_date" class="mb-1 block text-sm font-medium text-slate-700">Date</label>
                            <input type="date" id="trip_date" name="trip_date" value="{{ old('trip_date', now()->toDateString()) }}" class="{{ $inputClass }}" required>
                            @error('trip_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="started_at" class="mb-1 block text-sm font-medium text-slate-700">Start time</label>
                            <input type="time" id="started_at" name="started_at" value="{{ old('started_at') }}" class="{{ $inputClass }}">
                            @error('started_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="ended_at" class="mb-1 block text-sm font-medium text-slate-700">End time</label>
                            <input type="time" id="ended_at" name="ended_at" value="{{ old('ended_at') }}" class="{{ $inputClass }}">
                            @error('ended_at')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="odometer_start" class="mb-1 block text-sm font-medium text-slate-700">Odometer at start (km)</label>
                            <input type="number" id="odometer_start" name="odometer_start" value="{{ old('odometer_start', $rental->vehicle?->mileage) }}" min="0" step="1" class="{{ $inputClass }}">
                            @error('odometer_start')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="odometer_end" class="mb-1 block text-sm font-medium text-slate-700">Odometer at end (km)</label>
                            <input type="number" id="odometer_end" name="odometer_end" value="{{ old('odometer_end') }}" min="0" step="1" class="{{ $inputClass }}">
                            @error('odometer_end')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <div>
                        <label for="incident_notes" class="mb-1 block text-sm font-medium text-slate-700">Incidents / notes (optional)</label>
                        <textarea id="incident_notes" name="incident_notes" rows="3" class="{{ $inputClass }}">{{ old('incident_notes') }}</textarea>
                        @error('incident_notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Log this trip
                    </button>
                </form>
            </x-card>

            <x-card title="Logged trips">
                @if ($trips->isEmpty())
                    <p class="text-sm text-slate-500">No trips logged for this hire yet.</p>
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($trips as $trip)
                            <li class="py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="font-medium text-slate-900">{{ $trip->trip_date->format('d M Y') }}</p>
                                    <p class="text-sm text-slate-600">{{ $trip->distanceCovered() !== null ? number_format($trip->distanceCovered()).' km' : '' }}</p>
                                </div>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ $trip->started_at ? substr($trip->started_at, 0, 5).' – ' : '' }}{{ $trip->ended_at ? substr($trip->ended_at, 0, 5) : '' }}
                                    @if ($trip->odometer_start !== null)
                                        &middot; odo {{ number_format($trip->odometer_start) }}
                                    @endif
                                    @if ($trip->odometer_end !== null)
                                        → {{ number_format($trip->odometer_end) }}
                                    @endif
                                </p>
                                @if ($trip->incident_notes)
                                    <p class="mt-1 text-sm text-slate-600">{{ $trip->incident_notes }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div>
            <x-card title="Hire">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Customer</dt><dd class="font-medium text-slate-800">{{ $rental->customer?->display_name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Vehicle</dt><dd class="font-medium text-slate-800">{{ $rental->vehicle?->display_name ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Pick-up</dt><dd class="font-medium text-slate-800">{{ $rental->start_date->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Drop-off</dt><dd class="font-medium text-slate-800">{{ $rental->end_date->format('d M Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Status</dt><dd><x-badge :value="$rental->status->value" /></dd></div>
                </dl>
            </x-card>
        </div>
    </div>
@endsection

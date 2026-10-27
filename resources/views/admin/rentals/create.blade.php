@extends('layouts.app')

@section('title', 'New Rental')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
    $today = now()->toDateString();
@endphp

@section('content')
    <x-page-header title="New Rental Transaction" description="Start a hire, optionally converting a confirmed reservation.">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to rentals</a>
        </x-slot:actions>
    </x-page-header>

    @if ($reservation)
        <x-alert type="info">
            Converting reservation #{{ $reservation->id }} for
            {{ $reservation->customer?->display_name }} —
            {{ $reservation->vehicle?->display_name }}
            ({{ $reservation->start_date->format('d M Y') }} to {{ $reservation->end_date->format('d M Y') }}).
        </x-alert>
    @endif

    <x-card class="mb-6" title="Pick a period to refresh available vehicles">
        <form method="GET" action="{{ route('admin.rentals.create') }}" class="grid gap-2 sm:grid-cols-3 lg:items-end">
            <div class="mb-4">
                <label for="f_reservation" class="mb-1 block text-sm font-medium text-slate-700">Reservation #</label>
                <input id="f_reservation" name="reservation" type="number" min="1" value="{{ request('reservation') }}"
                       placeholder="Convert a confirmed one"
                       class="{{ $selectClass }}">
            </div>
            <div class="mb-4">
                <label for="f_customer" class="mb-1 block text-sm font-medium text-slate-700">Customer</label>
                <select id="f_customer" name="customer" class="{{ $selectClass }}">
                    <option value="">Any customer</option>
                    @foreach ($customers as $option)
                        <option value="{{ $option->id }}" @selected((string) $selectedCustomer === (string) $option->id)>
                            {{ $option->display_name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-field label="From" name="from" type="date" :value="$from" />
            <x-field label="To" name="to" type="date" :value="$to" />
            <div class="sm:col-span-3">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Refresh vehicles</button>
            </div>
        </form>
    </x-card>

    <form method="POST" action="{{ route('admin.rentals.store') }}">
        @csrf
        @if ($reservation)
            <input type="hidden" name="reservation" value="{{ $reservation->id }}">
        @endif
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="mb-4">
                    <label for="customer" class="mb-1 block text-sm font-medium text-slate-700">Customer <span class="text-rose-600">*</span></label>
                    <select id="customer" name="customer" class="{{ $selectClass }}" required>
                        <option value="">Select a customer</option>
                        @foreach ($customers as $option)
                            <option value="{{ $option->id }}" @selected((string) old('customer', $selectedCustomer) === (string) $option->id)>
                                {{ $option->display_name }}
                            </option>
                        @endforeach
                    </select>
                    @error('customer')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="mb-4">
                    <label for="vehicle" class="mb-1 block text-sm font-medium text-slate-700">Vehicle <span class="text-rose-600">*</span></label>
                    <select id="vehicle" name="vehicle" class="{{ $selectClass }}" required>
                        <option value="">Select a vehicle</option>
                        @foreach ($vehicles as $option)
                            <option value="{{ $option->id }}" @selected((string) old('vehicle', $selectedVehicle) === (string) $option->id)>
                                {{ $option->plate_number }} — {{ $option->make }} {{ $option->model }}
                                ({{ number_format($option->rate_per_day, 0) }} TZS/day)
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <x-field label="Start date" name="start_date" type="date" :value="$from" required min="{{ $today }}" />
                <x-field label="End date" name="end_date" type="date" :value="$to" required min="{{ $today }}" />
                <x-field label="Additional charges (TZS)" name="additional_charges" type="number" :value="''" />

                <x-field label="Discount (TZS)" name="discount" type="number" :value="''" />
                <x-field label="Pick-up location" name="pickup_location" :value="''" />
                <x-field label="Drop-off location" name="dropoff_location" :value="''" />

                <div class="mb-4 sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes', $reservation?->notes ?? '') }}</textarea>
                    @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <p class="mb-4 text-xs text-slate-500">
                Vehicles shown are those with status Available or Reserved and free for the selected period.
                Leave the period blank to list all Available/Reserved vehicles.
            </p>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Create Rental
                </button>
                <a href="{{ route('admin.rentals.index') }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection

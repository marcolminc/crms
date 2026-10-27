@extends('layouts.guest')

@section('title', 'Browse vehicles')

@section('content')
    <x-page-header title="Our fleet" description="Browse available vehicles at Land Valley's Travels, Mwanza.">
        <x-slot:actions>
            @if (auth()->check() && auth()->user()->isCustomer())
                <a href="{{ route('customer.reservations.create') }}"
                   class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    Book a vehicle
                </a>
            @elseif (! auth()->check())
                <a href="{{ route('register') }}"
                   class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    Create an account
                </a>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-card title="Filter vehicles">
        <form method="GET" action="{{ route('vehicles.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Keyword</label>
                <input type="text" id="q" name="q" value="{{ $filters['q'] }}"
                       placeholder="Plate, make or model"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Vehicle type</label>
                <select id="type" name="type" class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option value="">Any type</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="seats" class="mb-1 block text-sm font-medium text-slate-700">Minimum seats</label>
                <input type="number" id="seats" name="seats" min="1" value="{{ $filters['seats'] }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="max_rate" class="mb-1 block text-sm font-medium text-slate-700">Max rate / day (TZS)</label>
                <input type="number" id="max_rate" name="max_rate" min="0" step="1000" value="{{ $filters['max_rate'] }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="from" class="mb-1 block text-sm font-medium text-slate-700">Pick-up date</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="to" class="mb-1 block text-sm font-medium text-slate-700">Drop-off date</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-3">
                <button type="submit"
                        class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                    Apply filters
                </button>
                <a href="{{ route('vehicles.index') }}"
                   class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Reset
                </a>
                @if ($filters['from'] !== '' && $filters['to'] !== '')
                    <span class="ml-auto self-center text-xs text-slate-500">Showing vehicles free between these dates.</span>
                @endif
            </div>
        </form>
    </x-card>

    <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($vehicles as $vehicle)
            @php
                $window = array_filter(['from' => $filters['from'], 'to' => $filters['to']]);
                $detailUrl = route('vehicles.show', $vehicle).($window ? '?'.http_build_query($window) : '');
            @endphp
            <a href="{{ $detailUrl }}"
               class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-emerald-300 hover:shadow">
                <div class="flex items-start justify-between gap-2">
                    <h3 class="text-base font-semibold text-slate-900">{{ $vehicle->make }} {{ $vehicle->model }}</h3>
                    <x-badge :value="$vehicle->status->value" />
                </div>
                <p class="mt-1 text-sm text-slate-500">Plate {{ $vehicle->plate_number }}</p>
                <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-sm text-slate-600">
                    <div>{{ $vehicle->type->label() }}</div>
                    <div>{{ $vehicle->seats }} seats</div>
                    <div>{{ ucfirst($vehicle->transmission) }}</div>
                    <div>{{ ucfirst($vehicle->fuel_type) }}</div>
                </dl>
                <p class="mt-4 text-lg font-semibold text-slate-900">
                    {{ number_format($vehicle->rate_per_day, 0) }} TZS
                    <span class="text-xs font-normal text-slate-500">/ day</span>
                </p>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-alert type="info">
                    No vehicles match your filters for the selected dates. Try widening the dates or removing some filters.
                </x-alert>
            </div>
        @endforelse
    </div>

    @if ($vehicles->hasPages())
        <div class="mt-8">
            {{ $vehicles->links() }}
        </div>
    @endif
@endsection

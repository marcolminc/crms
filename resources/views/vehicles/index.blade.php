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

    @php
        $activeFilters = collect($filters)->filter(static fn ($value) => $value !== '' && $value !== null)->count();
    @endphp
    <details data-filter-card class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm" @if ($activeFilters > 0) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <span class="flex items-center gap-2">
                <span class="text-sm font-semibold text-slate-700">Filter vehicles</span>
                @if ($activeFilters > 0)
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">{{ $activeFilters }} active</span>
                @endif
            </span>
            <svg data-filter-chevron class="h-4 w-4 shrink-0 text-slate-500" fill="none" viewBox="0 0 24 24"
                 stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </summary>

        <form method="GET" action="{{ route('vehicles.index') }}" data-fleet-filter
              class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div>
                <label for="q" class="mb-1 block text-sm font-medium text-slate-700">Keyword</label>
                <input type="text" id="q" name="q" value="{{ $filters['q'] }}" data-fleet-q
                       placeholder="Plate, make or model" autocomplete="off"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Vehicle type</label>
                <select id="type" name="type" data-fleet-apply class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option value="">Any type</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="seats" class="mb-1 block text-sm font-medium text-slate-700">Minimum seats</label>
                <input type="number" id="seats" name="seats" min="1" value="{{ $filters['seats'] }}" data-fleet-apply
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="max_rate" class="mb-1 block text-sm font-medium text-slate-700">Max rate / day (TZS)</label>
                <input type="number" id="max_rate" name="max_rate" min="0" step="1000" value="{{ $filters['max_rate'] }}" data-fleet-apply
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="from" class="mb-1 block text-sm font-medium text-slate-700">Pick-up date</label>
                <input type="date" id="from" name="from" value="{{ $filters['from'] }}" data-fleet-apply
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
            </div>

            <div>
                <label for="to" class="mb-1 block text-sm font-medium text-slate-700">Drop-off date</label>
                <input type="date" id="to" name="to" value="{{ $filters['to'] }}" data-fleet-apply
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
    </details>

    <div id="fleet-grid" class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @include('vehicles._partials.cards')
    </div>

    <div id="fleet-more" class="mt-8 text-center"
         data-infinite
         data-grid="#fleet-grid"
         data-next="{{ $nextPageUrl }}">
        <p class="text-xs text-slate-400">
            Showing <span data-infinite-shown>{{ $vehicles->count() }}</span>
            of <span data-infinite-total>{{ $vehicles->total() }}</span> vehicles
            <span data-infinite-end hidden &mdash; that is the whole fleet.</span>
        </p>
        <div class="mx-auto mt-2 h-1 w-44 overflow-hidden rounded-full bg-slate-200" data-infinite-bar {{ $vehicles->total() > $vehicles->count() ? '' : 'hidden' }}>
            <div class="h-full rounded-full bg-emerald-500 transition-all duration-500" data-infinite-progress
                 style="width: {{ $vehicles->total() > 0 ? round($vehicles->count() / $vehicles->total() * 100) : 100 }}%"></div>
        </div>
        <span class="mt-2 inline-block animate-pulse text-xs font-medium text-slate-400" data-infinite-spinner hidden>Loading more vehicles&hellip;</span>
    </div>

    @if ($vehicles->hasPages())
        <div class="mt-6" data-infinite-paginate>
            {{ $vehicles->links() }}
        </div>
    @endif
@endsection

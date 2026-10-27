@extends('layouts.app')

@section('title', 'Rentals')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Rental Transactions" description="Active and past hires.">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">New Rental</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
        <x-stat label="Total (filtered)" :value="number_format($filteredTotal, 0).' TZS'" />
        <x-stat label="Collected" :value="number_format($collected, 0).' TZS'" />
        <x-stat label="Outstanding" :value="number_format($outstanding, 0).' TZS'" />
    </div>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.rentals.index') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
            <div class="lg:col-span-2">
                <x-field label="Search" name="q" :value="request('q')" placeholder="Customer or plate" />
            </div>
            <div class="mb-4">
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status" class="{{ $selectClass }}">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <x-field label="From" name="from" type="date" :value="request('from')" />
            <x-field label="To" name="to" type="date" :value="request('to')" />
            <div class="mb-4">
                <label class="flex items-center gap-2 text-sm font-medium text-slate-700">
                    <input type="checkbox" name="overdue" value="1" @checked(request('overdue')) class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    Overdue only
                </label>
            </div>
            <div class="sm:col-span-2 lg:col-span-6">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('admin.rentals.index') }}" class="ml-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">#</th>
                        <th class="py-2 pr-4">Customer</th>
                        <th class="py-2 pr-4">Vehicle</th>
                        <th class="py-2 pr-4">Period</th>
                        <th class="py-2 pr-4">Total</th>
                        <th class="py-2 pr-4">Balance</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rentals as $rental)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">#{{ $rental->id }}</td>
                            <td class="py-3 pr-4">{{ $rental->customer?->display_name ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $rental->vehicle?->display_name ?? 'Removed' }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                {{ $rental->start_date->format('d M Y') }} &ndash; {{ $rental->end_date->format('d M Y') }}
                            </td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ number_format($rental->total_amount, 0) }} TZS</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ number_format($rental->balanceDue(), 0) }} TZS</td>
                            <td class="py-3 pr-4"><x-badge :value="$rental->status->value" /></td>
                            <td class="py-3 whitespace-nowrap">
                                <a href="{{ route('admin.rentals.show', $rental) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-500">No rentals match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $rentals->links() }}
        </div>
    </x-card>
@endsection

@extends('layouts.app')

@section('title', 'Reservations')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Reservations" description="Booking requests awaiting review." />

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.reservations.index') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
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
            <div class="mb-4">
                <label for="vehicle" class="mb-1 block text-sm font-medium text-slate-700">Vehicle</label>
                <select id="vehicle" name="vehicle" class="{{ $selectClass }}">
                    <option value="">All vehicles</option>
                    @foreach ($vehicles as $option)
                        <option value="{{ $option->id }}" @selected((string) request('vehicle') === (string) $option->id)>
                            {{ $option->plate_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <x-field label="From" name="from" type="date" :value="request('from')" />
            <x-field label="To" name="to" type="date" :value="request('to')" />
            <div class="sm:col-span-2 lg:col-span-6">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('admin.reservations.index') }}" class="ml-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
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
                        <th class="py-2 pr-4">Days</th>
                        <th class="py-2 pr-4">Estimated</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($reservations as $reservation)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">#{{ $reservation->id }}</td>
                            <td class="py-3 pr-4">{{ $reservation->customer?->display_name ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $reservation->vehicle?->display_name ?? 'Removed vehicle' }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                {{ $reservation->start_date->format('d M Y') }} &ndash; {{ $reservation->end_date->format('d M Y') }}
                            </td>
                            <td class="py-3 pr-4">{{ $reservation->days() }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ number_format($reservation->estimated_total, 0) }} TZS</td>
                            <td class="py-3 pr-4"><x-badge :value="$reservation->status->value" /></td>
                            <td class="py-3 whitespace-nowrap">
                                <a href="{{ route('admin.reservations.show', $reservation) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-500">No reservations match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $reservations->links() }}
        </div>
    </x-card>
@endsection

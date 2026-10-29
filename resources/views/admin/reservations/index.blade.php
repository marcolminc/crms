@extends('layouts.app')

@section('title', 'Reservations')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Reservations" description="Booking requests awaiting review." />

    <x-card class="live-search-card mb-6">
        <form method="GET" action="{{ route('admin.reservations.index') }}" class="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-6" data-live-search>
            <x-search-box class="lg:col-span-2" name="q" :value="request('q')" placeholder="Customer or plate" />
            <div class="mb-4">
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status" class="{{ $selectClass }}" data-live-apply>
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label for="vehicle" class="mb-1 block text-sm font-medium text-slate-700">Vehicle</label>
                <select id="vehicle" name="vehicle" class="{{ $selectClass }}" data-live-apply>
                    <option value="">All vehicles</option>
                    @foreach ($vehicles as $option)
                        <option value="{{ $option->id }}" @selected((string) request('vehicle') === (string) $option->id)>
                            {{ $option->plate_number }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label for="from" class="mb-1 block text-sm font-medium text-slate-700">From</label>
                <input type="date" id="from" name="from" value="{{ request('from') }}" data-live-apply
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div class="mb-4">
                <label for="to" class="mb-1 block text-sm font-medium text-slate-700">To</label>
                <input type="date" id="to" name="to" value="{{ request('to') }}" data-live-apply
                       class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
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
                                @if ($reservation->status === \App\Enums\ReservationStatus::Confirmed && $reservation->end_date->gte(now()->startOfDay()))
                                    <a href="{{ route('admin.rentals.create', ['reservation' => $reservation->id]) }}"
                                       class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Start rental</a>
                                @endif
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

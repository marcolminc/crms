@extends('layouts.app')

@section('title', 'My reservations')

@section('content')
    <x-page-header title="My reservations" description="Booking requests you have sent to our team.">
        <x-slot:actions>
            <a href="{{ route('vehicles.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Browse vehicles
            </a>
            <a href="{{ route('customer.reservations.create') }}"
               class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                New reservation
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="GET" action="{{ route('customer.reservations.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status"
                        class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected($currentStatus === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="rounded-md bg-slate-800 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">
                Filter
            </button>
            @if ($currentStatus !== '')
                <a href="{{ route('customer.reservations.index') }}" class="pb-2 text-sm text-slate-500 hover:text-slate-700">Clear</a>
            @endif
        </form>
    </x-card>

    <div class="mt-6 space-y-3">
        @forelse ($reservations as $reservation)
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <a href="{{ route('customer.reservations.show', $reservation) }}"
                           class="font-medium text-emerald-700 hover:underline">
                            {{ $reservation->vehicle?->display_name ?? 'Vehicle removed' }}
                        </a>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $reservation->start_date->format('d M Y') }} &rarr; {{ $reservation->end_date->format('d M Y') }}
                            &middot; {{ $reservation->days() }} day(s)
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <span class="font-semibold text-slate-900">{{ number_format($reservation->estimated_total, 0) }} TZS</span>
                        <x-badge :value="$reservation->status->value" />
                    </div>
                </div>
            </x-card>
        @empty
            <x-alert type="info">
                You have not made any reservations yet.
                <a href="{{ route('vehicles.index') }}" class="font-medium underline">Browse our fleet</a> to get started.
            </x-alert>
        @endforelse
    </div>

    @if ($reservations->hasPages())
        <div class="mt-8">
            {{ $reservations->links() }}
        </div>
    @endif
@endsection

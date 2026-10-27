@extends('layouts.app')

@section('title', 'Reservation #'.$reservation->id)

@section('content')
    @php
        $cancellable = in_array($reservation->status->value, ['pending', 'confirmed'], true)
            && $reservation->start_date->gte(now()->startOfDay());
    @endphp

    <x-page-header :title="'Reservation #'.$reservation->id" description="Details of your booking request.">
        <x-slot:actions>
            <a href="{{ route('customer.reservations.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Back to list
            </a>
            @if ($cancellable)
                <form method="POST" action="{{ route('customer.reservations.cancel', $reservation) }}"
                      onsubmit="return confirm('Cancel this reservation?');">
                    @csrf
                    @method('PUT')
                    <button type="submit"
                            class="rounded-md bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-500">
                        Cancel reservation
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Reservation summary">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-badge :value="$reservation->status->value" /></dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Estimated total</dt>
                        <dd class="font-medium text-slate-800">{{ number_format($reservation->estimated_total, 0) }} TZS</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Pick-up date</dt>
                        <dd class="font-medium text-slate-800">{{ $reservation->start_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Drop-off date</dt>
                        <dd class="font-medium text-slate-800">{{ $reservation->end_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Duration</dt>
                        <dd class="font-medium text-slate-800">{{ $reservation->days() }} day(s)</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Requested on</dt>
                        <dd class="font-medium text-slate-800">{{ $reservation->created_at->format('d M Y') }}</dd>
                    </div>
                </dl>

                @if ($reservation->notes)
                    <div class="mt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Your notes</p>
                        <p class="mt-1 text-sm text-slate-700">{{ $reservation->notes }}</p>
                    </div>
                @endif

                @if ($reservation->rejection_reason)
                    <div class="mt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-rose-600">Reason for rejection</p>
                        <p class="mt-1 text-sm text-slate-700">{{ $reservation->rejection_reason }}</p>
                    </div>
                @endif

                @if ($reservation->confirmed_at)
                    <p class="mt-4 text-sm text-emerald-700">
                        Confirmed by {{ $reservation->confirmedBy?->name ?? 'our team' }} on
                        {{ $reservation->confirmed_at->format('d M Y') }}.
                    </p>
                @endif

                @if ($reservation->rental)
                    <p class="mt-4 text-sm text-slate-600">
                        This reservation has been converted to rental
                        <a href="{{ route('customer.rentals.index') }}" class="text-emerald-700 underline">
                            #{{ $reservation->rental->id }}
                        </a>.
                    </p>
                @endif
            </x-card>
        </div>

        <div>
            <x-card title="Vehicle">
                @if ($reservation->vehicle)
                    <p class="text-base font-semibold text-slate-900">{{ $reservation->vehicle->display_name }}</p>
                    <p class="mt-1 text-sm text-slate-500">Plate {{ $reservation->vehicle->plate_number }}</p>
                    <dl class="mt-3 space-y-1 text-sm text-slate-600">
                        <div>{{ $reservation->vehicle->type->label() }}</div>
                        <div>{{ $reservation->vehicle->seats }} seats &middot; {{ ucfirst($reservation->vehicle->transmission) }}</div>
                        <div>{{ number_format($reservation->vehicle->rate_per_day, 0) }} TZS / day</div>
                    </dl>
                    <a href="{{ route('vehicles.show', $reservation->vehicle) }}"
                       class="mt-4 block rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                        View vehicle
                    </a>
                @else
                    <p class="text-sm text-slate-500">This vehicle is no longer in our fleet.</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection

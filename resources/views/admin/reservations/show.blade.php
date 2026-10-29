@extends('layouts.app')

@section('title', 'Reservation #'.$reservation->id)

@php
    $customer = $reservation->customer;
    $user = $customer?->user;
    $vehicle = $reservation->vehicle;
    $rental = $reservation->rental;
    $canConfirm = $reservation->status === \App\Enums\ReservationStatus::Pending;
    $canReject = $reservation->status === \App\Enums\ReservationStatus::Pending;
    // Pickup-day path: a confirmed, unexpired reservation converts on the
    // rental form; mirrors RentalController::create()'s acceptance checks.
    $canConvert = $reservation->status === \App\Enums\ReservationStatus::Confirmed
        && ! $rental
        && $reservation->end_date->gte(now()->startOfDay());
    $canCancel = in_array($reservation->status, [\App\Enums\ReservationStatus::Pending, \App\Enums\ReservationStatus::Confirmed], true) && ! $rental;
    $canDelete = in_array($reservation->status, [\App\Enums\ReservationStatus::Cancelled, \App\Enums\ReservationStatus::Rejected, \App\Enums\ReservationStatus::Completed], true) && ! $rental;
@endphp

@section('content')
    <x-page-header :title="'Reservation #'.$reservation->id" description="Review the booking request and take action.">
        <x-slot:actions>
            <a href="{{ route('admin.reservations.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to list</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Booking details">
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
                    @if ($reservation->needs_driver)
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Office driver</dt>
                            <dd class="font-medium text-emerald-700">Requested &mdash; assign a chauffeur</dd>
                        </div>
                    @endif
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Requested on</dt>
                        <dd class="font-medium text-slate-800">{{ $reservation->created_at->format('d M Y') }}</dd>
                    </div>
                    @if ($reservation->confirmed_at)
                        <div class="flex justify-between border-b border-slate-100 pb-2 sm:col-span-2">
                            <dt class="text-slate-500">Confirmed by</dt>
                            <dd class="font-medium text-slate-800">
                                {{ $reservation->confirmedBy?->name ?? 'our team' }} on {{ $reservation->confirmed_at->format('d M Y') }}
                            </dd>
                        </div>
                    @endif
                </dl>

                @if ($reservation->notes)
                    <div class="mt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $reservation->notes }}</p>
                    </div>
                @endif

                @if ($reservation->rejection_reason)
                    <div class="mt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-rose-600">Rejection reason</p>
                        <p class="mt-1 text-sm text-slate-700">{{ $reservation->rejection_reason }}</p>
                    </div>
                @endif

                @if ($rental)
                    <p class="mt-4 text-sm text-slate-600">
                        Converted to
                        <a href="{{ route('admin.rentals.show', $rental) }}" class="text-emerald-700 underline">rental #{{ $rental->id }}</a>.
                    </p>
                    @if ($rental->payments->isNotEmpty())
                        <div class="mt-3 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                        <th class="py-1 pr-4">Receipt</th>
                                        <th class="py-1 pr-4">Amount</th>
                                        <th class="py-1">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach ($rental->payments as $payment)
                                        <tr>
                                            <td class="py-1 pr-4">{{ $payment->receipt_number }}</td>
                                            <td class="py-1 pr-4 whitespace-nowrap">{{ number_format($payment->amount, 0) }} TZS</td>
                                            <td class="py-1"><x-badge :value="$payment->status->value" /></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Customer">
                <p class="text-base font-semibold text-slate-900">{{ $customer?->display_name ?? 'Unknown' }}</p>
                @if ($user)
                    <dl class="mt-2 space-y-1 text-sm text-slate-600">
                        <div>{{ $user->email }}</div>
                        <div>{{ $user->phone ?? 'No phone' }}</div>
                    </dl>
                @endif
                @if ($customer)
                    <dl class="mt-2 space-y-1 text-sm text-slate-600">
                        @if ($customer->national_id)<div>National ID: {{ $customer->national_id }}</div>@endif
                        @if ($customer->driving_license_no)<div>Driving licence: {{ $customer->driving_license_no }}</div>@endif
                        @if ($customer->city)<div>{{ $customer->city }}</div>@endif
                        @if ($customer->emergency_contact_name || $customer->emergency_contact_phone)
                            <div class="text-slate-500">
                                Emergency: {{ $customer->emergency_contact_name ?? '—' }}
                                {{ $customer->emergency_contact_phone ? ' ('.$customer->emergency_contact_phone.')' : '' }}
                            </div>
                        @endif
                    </dl>
                @endif
                @if ($customer)
                    <a href="{{ route('admin.customers.show', $customer) }}"
                       class="mt-4 block rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Open customer profile
                    </a>
                @endif
            </x-card>

            <x-card title="Vehicle">
                @if ($vehicle)
                    <p class="text-base font-semibold text-slate-900">{{ $vehicle->display_name }}</p>
                    <p class="mt-1 text-sm text-slate-500">Plate {{ $vehicle->plate_number }}</p>
                    <dl class="mt-3 space-y-1 text-sm text-slate-600">
                        <div>{{ $vehicle->type->label() }} &middot; {{ $vehicle->seats }} seats</div>
                        <div>{{ ucfirst($vehicle->transmission) }} &middot; {{ ucfirst($vehicle->fuel_type) }}</div>
                        <div>{{ number_format($vehicle->rate_per_day, 0) }} TZS / day</div>
                    </dl>
                    <x-badge :value="$vehicle->status->value" class="mt-3" />
                @else
                    <p class="text-sm text-slate-500">This vehicle is no longer in the fleet.</p>
                @endif
            </x-card>

            <x-card title="Actions">
                @if ($canConvert)
                    <a href="{{ route('admin.rentals.create', ['reservation' => $reservation->id]) }}"
                       class="mb-3 block w-full rounded-lg bg-emerald-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Convert to rental
                    </a>
                @endif

                @if ($canConfirm)
                    <form method="POST" action="{{ route('admin.reservations.confirm', $reservation) }}" class="mb-3">
                        @csrf
                        @method('PUT')
                        <button type="submit"
                                class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                            Confirm reservation
                        </button>
                    </form>
                @endif

                @if ($canConfirm)
                    <form method="POST" action="{{ route('admin.reservations.reject', $reservation) }}" class="mb-3">
                        @csrf
                        @method('PUT')
                        <textarea name="reason" rows="2" required placeholder="Rejection reason"
                                  class="mb-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                        <button type="submit"
                                class="w-full rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                            Reject reservation
                        </button>
                    </form>
                @endif

                @if ($canCancel)
                    <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" class="mb-3">
                        @csrf
                        @method('PUT')
                        <textarea name="reason" rows="2" placeholder="Cancellation reason (optional)"
                                  class="mb-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                        <button type="submit"
                                class="w-full rounded-lg border border-amber-300 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-50">
                            Cancel reservation
                        </button>
                    </form>
                @endif

                @if ($canDelete)
                    <form method="POST" action="{{ route('admin.reservations.destroy', $reservation) }}"
                          onsubmit="return confirm('Delete reservation #{{ $reservation->id }} permanently?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50">
                            Delete record
                        </button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection

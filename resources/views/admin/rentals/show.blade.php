@extends('layouts.app')

@section('title', 'Rental #'.$rental->id)

@php
    $vehicle = $rental->vehicle;
    $return = $rental->vehicleReturn;
    $isOpen = in_array($rental->status, [\App\Enums\RentalStatus::Active, \App\Enums\RentalStatus::Overdue], true);
    $balance = $rental->balanceDue();
@endphp

@section('content')
    <x-page-header :title="'Rental #'.$rental->id" :description="$rental->customer?->display_name.' — '.($vehicle?->display_name ?? 'Removed vehicle')">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to rentals</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Transaction">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-badge :value="$rental->status->value" /></dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Handled by</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->staff?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Pick-up date</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->start_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Drop-off date</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->end_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Duration</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->days }} day(s)</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Rate / day</dt>
                        <dd class="font-medium text-slate-800">{{ number_format($rental->rate_per_day, 0) }} TZS</dd>
                    </div>
                    @if ($rental->reservation)
                        <div class="flex justify-between border-b border-slate-100 pb-2 sm:col-span-2">
                            <dt class="text-slate-500">Source reservation</dt>
                            <dd>
                                <a class="text-emerald-700 hover:underline" href="{{ route('admin.reservations.show', $rental->reservation) }}">
                                    #{{ $rental->reservation->id }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>

                <div class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
                    <p class="text-slate-500">Subtotal</p>
                    <p class="text-right font-medium text-slate-800">{{ number_format($rental->subtotal, 0) }} TZS</p>
                </div>
                <div class="grid gap-2 text-sm sm:grid-cols-3">
                    <p class="text-slate-500">Additional charges</p>
                    <p class="text-right text-slate-800">+{{ number_format($rental->additional_charges, 0) }} TZS</p>
                </div>
                <div class="grid gap-2 text-sm sm:grid-cols-3">
                    <p class="text-slate-500">Discount</p>
                    <p class="text-right text-slate-800">-{{ number_format($rental->discount, 0) }} TZS</p>
                </div>
                <div class="mt-2 grid gap-2 border-t border-slate-200 pt-2 text-sm sm:grid-cols-3">
                    <p class="font-medium text-slate-700">Total</p>
                    <p class="text-right font-semibold text-slate-900">{{ number_format($rental->total_amount, 0) }} TZS</p>
                </div>
                <div class="grid gap-2 text-sm sm:grid-cols-3">
                    <p class="font-medium text-slate-700">Balance due</p>
                    <p class="text-right font-semibold {{ $balance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ number_format($balance, 0) }} TZS</p>
                </div>

                @if ($rental->pickup_location || $rental->dropoff_location)
                    <p class="mt-3 text-sm text-slate-600">
                        Pickup: {{ $rental->pickup_location ?? '—' }} &middot; Drop-off: {{ $rental->dropoff_location ?? '—' }}
                    </p>
                @endif

                @if ($rental->odometer_at_pickup !== null || $rental->fuel_at_pickup || $rental->hasPhotos('pickup_photos'))
                    <div class="mt-4 rounded-lg border border-slate-200 bg-slate-50/50 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Pick-up handover</p>
                        <div class="mb-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                            <div class="flex justify-between border-b border-slate-200 pb-1">
                                <dt class="text-slate-500">Odometer</dt>
                                <dd class="font-medium text-slate-800">{{ $rental->odometer_at_pickup !== null ? number_format($rental->odometer_at_pickup).' km' : '—' }}</dd>
                            </div>
                            <div class="flex justify-between border-b border-slate-200 pb-1">
                                <dt class="text-slate-500">Fuel</dt>
                                <dd class="font-medium text-slate-800">{{ $rental->fuel_at_pickup ?? '—' }}</dd>
                            </div>
                        </div>
                        <x-photo-grid :rows="$rental->gallery('pickup_photos')" :subject="$rental" alt="pick-up photo" />
                    </div>
                @endif

                @if ($rental->notes)
                    <div class="mt-3">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $rental->notes }}</p>
                    </div>
                @endif
            </x-card>

            <x-card title="Payments">
                @if ($rental->payments->isEmpty())
                    <p class="text-sm text-slate-500">No payments recorded yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="py-2 pr-4">Receipt</th>
                                    <th class="py-2 pr-4">Paid on</th>
                                    <th class="py-2 pr-4">Method</th>
                                    <th class="py-2 pr-4">Amount</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rental->payments as $payment)
                                    <tr>
                                        <td class="py-2 pr-4 font-medium text-slate-900">{{ $payment->receipt_number }}</td>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ ($payment->paid_at ?? $payment->created_at)->format('d M Y') }}</td>
                                        <td class="py-2 pr-4">{{ $payment->payment_method?->label() }}</td>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ number_format($payment->amount, 0) }} TZS</td>
                                        <td class="py-2"><x-badge :value="$payment->status->value" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                @if ($isOpen || $balance > 0)
                    <a href="{{ route('admin.payments.create', $rental) }}"
                       class="mt-4 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Record payment
                    </a>
                @endif
            </x-card>

            @if ($return)
                <x-card title="Vehicle return">
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Returned on</dt>
                            <dd class="font-medium text-slate-800">{{ $return->returned_at->format('d M Y') }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Condition</dt>
                            <dd><x-badge :value="$return->condition->value" /></dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Fuel level</dt>
                            <dd class="font-medium text-slate-800">{{ $return->fuel_level ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Mileage at return</dt>
                            <dd class="font-medium text-slate-800">{{ $return->mileage_at_return !== null ? number_format($return->mileage_at_return).' km' : '—' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2 sm:col-span-2">
                            <dt class="text-slate-500">Damage / extra charges</dt>
                            <dd class="font-medium text-slate-800">{{ number_format($return->additional_charges, 0) }} TZS</dd>
                        </div>
                    </dl>
                    @if ($return->damage_notes)
                        <p class="mt-3 whitespace-pre-line text-sm text-slate-700">{{ $return->damage_notes }}</p>
                    @endif
                    @if ($return->hasPhotos('photos'))
                        <div class="mt-4">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Return photos</p>
                            <x-photo-grid :rows="$return->gallery('photos')" :subject="$return" alt="return photo" />
                        </div>
                    @endif
                    <p class="mt-3 text-xs text-slate-500">Processed by {{ $return->processedBy?->name ?? '—' }}</p>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            <x-card title="Vehicle">
                @if ($vehicle)
                    <p class="text-base font-semibold text-slate-900">{{ $vehicle->display_name }}</p>
                    <p class="mt-1 text-sm text-slate-500">Plate {{ $vehicle->plate_number }}</p>
                    <p class="mt-2 text-sm text-slate-600">{{ $vehicle->type->label() }} &middot; {{ $vehicle->seats }} seats</p>
                    <x-badge :value="$vehicle->status->value" class="mt-3" />
                @else
                    <p class="text-sm text-slate-500">Vehicle no longer in the fleet.</p>
                @endif
            </x-card>

            <x-card title="Customer">
                <p class="text-base font-semibold text-slate-900">{{ $rental->customer?->display_name ?? 'Unknown' }}</p>
                @if ($rental->customer?->user)
                    <p class="mt-1 text-sm text-slate-500">{{ $rental->customer->user->email }}</p>
                    <p class="text-sm text-slate-500">{{ $rental->customer->user->phone ?? 'No phone' }}</p>
                @endif
                @if ($rental->customer)
                    <a href="{{ route('admin.customers.show', $rental->customer) }}"
                       class="mt-4 block rounded-md border border-slate-300 px-3 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Open customer profile
                    </a>
                @endif
            </x-card>

            <x-card title="Actions">
                @if ($rental->status !== \App\Enums\RentalStatus::Cancelled)
                    <a href="{{ route('admin.rentals.edit', $rental) }}"
                       class="mb-3 block rounded-lg border border-slate-300 px-4 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">Edit rental</a>
                @endif

                @if ($isOpen && ! $return)
                    <a href="{{ route('admin.returns.create', $rental) }}"
                       class="mb-3 block rounded-lg bg-sky-600 px-4 py-2 text-center text-sm font-semibold text-white shadow-sm hover:bg-sky-700">Record return</a>
                @endif

                @if ($isOpen)
                    <form method="POST" action="{{ route('admin.rentals.cancel', $rental) }}" class="mb-3">
                        @csrf
                        @method('PUT')
                        <textarea name="reason" rows="2" required placeholder="Cancellation reason"
                                  class="mb-2 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500"></textarea>
                        <button type="submit"
                                class="w-full rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                            Cancel rental
                        </button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
@endsection

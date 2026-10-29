@extends('layouts.app')

@section('title', 'Rental #'.$rental->id)

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
    $return = $rental->vehicleReturn;
@endphp

@section('content')
    <x-page-header
        :title="'Rental #'.$rental->id"
        :description="($rental->vehicle?->display_name ?? 'Vehicle').' · '.$rental->start_date->format('d M Y').' → '.$rental->end_date->format('d M Y')">
        <x-slot:actions>
            <a href="{{ route('customer.rentals.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Back to rentals
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Rental agreement">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-badge :value="$rental->status->value" /></dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Vehicle</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->vehicle?->display_name ?? 'Removed' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Pick-up</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->start_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Drop-off</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->end_date->format('d M Y') }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Duration</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->days }} day(s)</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Daily rate</dt>
                        <dd class="font-medium text-slate-800">{{ number_format($rental->rate_per_day, 0) }} TZS</dd>
                    </div>
                </dl>

                <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Total</dt>
                        <dd class="font-semibold text-slate-900">{{ number_format($rental->total_amount, 0) }} TZS</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Paid</dt>
                        <dd class="font-semibold text-slate-900">{{ number_format($rental->paidAmount(), 0) }} TZS</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide text-slate-500">Balance due</dt>
                        <dd class="font-semibold {{ $rental->isSettled() ? 'text-emerald-700' : 'text-rose-700' }}">
                            {{ number_format($rental->balanceDue(), 0) }} TZS
                        </dd>
                    </div>
                </dl>

                @if ($onlinePayments && ! $rental->isSettled() && $rental->status !== \App\Enums\RentalStatus::Cancelled)
                    <form method="POST" action="{{ route('customer.rentals.pay', $rental) }}" class="mt-4">
                        @csrf
                        <label for="payment_method" class="mb-1 block text-sm font-medium text-slate-700">Pay the balance online</label>
                        <div class="flex flex-wrap items-center gap-3">
                            <select id="payment_method" name="payment_method" class="{{ $selectClass }} sm:w-56" required>
                                @foreach ($onlineMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                Pay now &mdash; {{ number_format($rental->balanceDue(), 0) }} TZS
                            </button>
                        </div>
                    </form>
                @endif
            </x-card>

            {{-- UC08: the pick-up record captured by our team when you collected the vehicle --}}
            <x-card title="Vehicle pick-up">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Pick-up location</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->pickup_location ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Drop-off location</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->dropoff_location ?: '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Odometer at pick-up</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->odometer_at_pickup !== null ? number_format($rental->odometer_at_pickup).' km' : '—' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Fuel at pick-up</dt>
                        <dd class="font-medium text-slate-800">{{ $rental->fuel_at_pickup ?: '—' }}</dd>
                    </div>
                </dl>
                @if (empty($rental->pickup_location) && empty($rental->odometer_at_pickup) && empty($rental->fuel_at_pickup))
                    <p class="mt-3 text-sm text-slate-500">The pick-up details are recorded by our staff when you collect the vehicle at the desk.</p>
                @endif
            </x-card>

            {{-- UC09: the return inspection our team completed when the vehicle came back --}}
            <x-card title="Vehicle return &amp; inspection">
                @if ($return)
                    <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Returned on</dt>
                            <dd class="font-medium text-slate-800">{{ $return->returned_at->format('d M Y H:i') }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Condition</dt>
                            <dd class="font-medium text-slate-800">{{ $return->condition->label() }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Mileage at return</dt>
                            <dd class="font-medium text-slate-800">{{ $return->mileage_at_return !== null ? number_format($return->mileage_at_return).' km' : '—' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Fuel level</dt>
                            <dd class="font-medium text-slate-800">{{ $return->fuel_level ?: '—' }}</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Damage charges</dt>
                            <dd class="font-medium text-slate-800">{{ number_format($return->additional_charges, 0) }} TZS</dd>
                        </div>
                        <div class="flex justify-between border-b border-slate-100 pb-2">
                            <dt class="text-slate-500">Processed by</dt>
                            <dd class="font-medium text-slate-800">{{ $return->processedBy?->name ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if ($return->damage_notes)
                        <p class="mt-3 text-sm text-slate-600"><span class="font-medium">Notes:</span> {{ $return->damage_notes }}</p>
                    @endif
                @else
                    <p class="text-sm text-slate-500">This vehicle has not been returned yet. The inspection record will appear here once our team checks it back in.</p>
                @endif
            </x-card>

            {{-- UC25: any traffic fines raised against this hire and their liability --}}
            @if ($rental->fines->isNotEmpty())
                <x-card title="Traffic fines">
                    <ul class="divide-y divide-slate-100">
                        @foreach ($rental->fines as $fine)
                            <li class="flex items-start justify-between gap-3 py-3">
                                <div>
                                    <p class="font-medium text-slate-900">{{ $fine->description ?? 'Traffic fine' }}</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $fine->reference ?: 'no reference' }} &middot; {{ $fine->issued_at?->format('d M Y') ?? 'date n/a' }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-slate-900">{{ number_format((float) $fine->amount, 0) }} TZS</p>
                                    <p class="text-xs text-slate-500">
                                        {{ $fine->status->label() }}@if ($fine->liability) &middot; {{ $fine->liability->label() }}@endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            {{-- UC10: extend the hire, subject to the vehicle being free --}}
            @if ($extendable)
                <x-card title="Extend this rental">
                    <form method="POST" action="{{ route('customer.rentals.extend', $rental) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="end_date" class="mb-1 block text-sm font-medium text-slate-700">New drop-off date</label>
                            <input type="date" id="end_date" name="end_date" value="{{ old('end_date') }}"
                                   min="{{ $rental->end_date->copy()->addDay()->toDateString() }}"
                                   class="{{ $selectClass }}" required>
                            @error('end_date')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <p class="text-xs text-slate-500">Extra days are billed at {{ number_format($rental->rate_per_day, 0) }} TZS per day. We confirm the vehicle is free, then you pay the new balance.</p>
                        <button type="submit"
                                class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                            Request extension
                        </button>
                    </form>
                </x-card>
            @endif

            {{-- UC21: rate the rental once it has been returned --}}
            <x-card title="Rate your rental">
                @if ($rental->rating !== null)
                    <p class="text-sm text-slate-600">You rated this hire
                        <span class="font-semibold text-amber-500">{{ str_repeat('★', $rental->rating) }}{{ str_repeat('☆', 5 - $rental->rating) }}</span>
                        on {{ $rental->rated_at?->format('d M Y') }}.
                    </p>
                    @if ($rental->rating_comment)
                        <p class="mt-2 text-sm text-slate-700">&ldquo;{{ $rental->rating_comment }}&rdquo;</p>
                    @endif
                @elseif ($rental->isRateable())
                    <form method="POST" action="{{ route('customer.rentals.rate', $rental) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="rating" class="mb-1 block text-sm font-medium text-slate-700">Rating</label>
                            <select id="rating" name="rating" class="{{ $selectClass }}" required>
                                @foreach ([5 => '5 — Excellent', 4 => '4 — Good', 3 => '3 — Fair', 2 => '2 — Poor', 1 => '1 — Very poor'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('rating') == $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('rating')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                        <div>
                            <label for="rating_comment" class="mb-1 block text-sm font-medium text-slate-700">Comment (optional)</label>
                            <textarea id="rating_comment" name="rating_comment" rows="3" class="{{ $selectClass }}">{{ old('rating_comment') }}</textarea>
                        </div>
                        <button type="submit"
                                class="w-full rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                            Submit rating
                        </button>
                    </form>
                @else
                    <p class="text-sm text-slate-500">You can rate this rental once the vehicle has been returned.</p>
                @endif
            </x-card>
        </div>
    </div>
@endsection

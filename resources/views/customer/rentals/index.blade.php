@extends('layouts.app')

@section('title', 'My rentals')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="My rentals" description="Active and past rentals on your account, with balances.">
        <x-slot:actions>
            <a href="{{ route('vehicles.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Browse vehicles
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="space-y-3">
        @forelse ($rentals as $rental)
            <x-card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-slate-900">{{ $rental->vehicle?->display_name ?? 'Vehicle removed' }}</p>
                        <p class="mt-1 text-sm text-slate-500">
                            Rental #{{ $rental->id }} &middot;
                            {{ $rental->start_date->format('d M Y') }} &rarr; {{ $rental->end_date->format('d M Y') }}
                            &middot; {{ $rental->days }} day(s)
                        </p>
                    </div>
                    <x-badge :value="$rental->status->value" />
                </div>

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

                @if ($rental->isSettled())
                    <p class="mt-3 text-xs text-emerald-700">This rental is fully settled.</p>
                @endif

                @if ($onlinePayments && ! $rental->isSettled() && $rental->status !== \App\Enums\RentalStatus::Cancelled)
                    <form method="POST" action="{{ route('customer.rentals.pay', $rental) }}" class="mt-4">
                        @csrf
                        <label for="payment_method_{{ $rental->id }}" class="mb-1 block text-sm font-medium text-slate-700">
                            Pay the balance online
                        </label>
                        <div class="flex flex-wrap items-center gap-3">
                            <select id="payment_method_{{ $rental->id }}" name="payment_method"
                                    class="{{ $selectClass }} sm:w-56" required>
                                @foreach ($onlineMethods as $value => $label)
                                    <option value="{{ $value }}" @selected(old('payment_method') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                                Pay now &mdash; {{ number_format($rental->balanceDue(), 0) }} TZS
                            </button>
                        </div>
                        @error('payment_method')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </form>
                @endif
            </x-card>
        @empty
            <x-alert type="info">
                You have no rentals yet. Once a reservation is confirmed, our team will set up the rental for you.
                <a href="{{ route('vehicles.index') }}" class="font-medium underline">Browse our fleet</a> to get started.
            </x-alert>
        @endforelse
    </div>

    @if ($rentals->hasPages())
        <div class="mt-8">
            {{ $rentals->links() }}
        </div>
    @endif
@endsection

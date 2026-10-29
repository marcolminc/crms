@extends('layouts.app')

@section('title', 'Record Payment')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Record a payment" :description="'Receipt for '.$rental->customer?->display_name.' against rental '.$rental->id.'.'">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.show', $rental) }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Back to rental
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card title="Hire summary">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Vehicle</dt>
                    <dd class="text-right font-medium text-slate-900">{{ $rental->vehicle?->display_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Period</dt>
                    <dd class="text-right text-slate-800">
                        {{ $rental->start_date->format('d M Y') }} &ndash; {{ $rental->end_date->format('d M Y') }}
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Total amount</dt>
                    <dd class="text-right text-slate-800">{{ number_format($rental->total_amount, 0) }} TZS</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Paid so far</dt>
                    <dd class="text-right text-slate-800">{{ number_format($rental->paidAmount(), 0) }} TZS</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-slate-100 pt-3">
                    <dt class="font-medium text-slate-700">Balance due</dt>
                    <dd class="text-right text-base font-semibold text-emerald-700">{{ number_format($balance, 0) }} TZS</dd>
                </div>
            </dl>
        </x-card>

        <form method="POST" action="{{ route('admin.payments.store', $rental) }}" class="lg:col-span-2">
            @csrf
            <x-card title="Payment details">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Amount (TZS)" name="amount" type="number" step="0.01" min="1"
                             :value="number_format($balance, 2, '.', '')" required />

                    <div class="mb-4">
                        <label for="payment_method" class="mb-1 block text-sm font-medium text-slate-700">
                            Method <span class="text-rose-600">*</span>
                        </label>
                        <select id="payment_method" name="payment_method" class="{{ $selectClass }}" required>
                            @foreach ($methods as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_method', 'cash') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_method')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-field label="Transaction reference" name="transaction_reference"
                             maxlength="60" placeholder="M-Pesa / Tigo Pesa confirmation code" />

                    <x-field label="Paid on" name="paid_at" type="date" :value="now()->format('Y-m-d')" />

                    <div class="mb-4 sm:col-span-2">
                        <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                        <textarea id="notes" name="notes" rows="3" maxlength="2000"
                                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes') }}</textarea>
                        @error('notes')
                            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <p class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Mobile money receipts need the transaction reference. Leave the paid date empty to file a
                    promise to pay &mdash; the receipt is then saved as Pending.
                </p>

                <div class="mt-4 flex items-center gap-3">
                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Save payment
                    </button>
                    <a href="{{ route('admin.rentals.show', $rental) }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
                </div>
            </x-card>
        </form>
    </div>

    <script>
        (function () {
            const mobileMoney = @json($mobileMoney);
            const method = document.getElementById('payment_method');
            const reference = document.getElementById('transaction_reference');

            const sync = () => {
                reference.required = mobileMoney.includes(method.value);
            };

            method.addEventListener('change', sync);
            sync();
        })();
    </script>
@endsection

@extends('layouts.app')

@section('title', 'Refund Rental #'.$rental->id)

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Settle refund" :description="'Rental #'.$rental->id.' — '.($rental->customer?->display_name ?? 'Customer')">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.invoice', $rental) }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to invoice</a>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        @if ($surplus <= 0)
            <p class="text-sm text-slate-600">This rental is not over-paid, so there is nothing to refund.</p>
        @else
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <span class="font-semibold">Refund due: {{ number_format($surplus, 0) }} TZS.</span>
                Money collected exceeds the final total of {{ number_format($rental->total_amount, 0) }} TZS by this amount.
            </div>

            @if ($refundable->isEmpty())
                <p class="mb-4 text-sm text-slate-600">
                    None of the collected payments can be paid back through AzamPay (mobile money only). Settle this refund at the office.
                </p>
            @endif

            <form method="POST" action="{{ route('admin.rentals.refund.store', $rental) }}">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="mb-4">
                        <label for="amount" class="mb-1 block text-sm font-medium text-slate-700">Refund amount (TZS) <span class="text-rose-600">*</span></label>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" max="{{ $surplus }}"
                               value="{{ old('amount', $surplus) }}" class="{{ $selectClass }}" required>
                        @error('amount')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div class="mb-4 sm:col-span-2">
                        <label for="reason" class="mb-1 block text-sm font-medium text-slate-700">Reason <span class="text-rose-600">*</span></label>
                        <textarea id="reason" name="reason" rows="3" class="{{ $selectClass }}" required>{{ old('reason', 'Settlement of over-payment after return') }}</textarea>
                        @error('reason')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                @if ($refundable->isNotEmpty())
                    <div class="mb-6">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Paid back from</p>
                        <ul class="space-y-1 text-sm text-slate-700">
                            @foreach ($refundable as $payment)
                                <li>{{ $payment->receipt_number }} &middot; {{ $payment->payment_method?->label() }} &middot; {{ number_format($payment->refundableAmount(), 0) }} TZS refundable</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="flex items-center gap-3">
                    <button type="submit" @disabled($refundable->isEmpty())
                            class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                        Send refund through AzamPay
                    </button>
                    <a href="{{ route('admin.rentals.invoice', $rental) }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
                </div>
            </form>
        @endif
    </x-card>
@endsection

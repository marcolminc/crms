@extends('layouts.print')

@section('title', 'Receipt '.$payment->receipt_number)

@php
    $rental = $payment->rental;
@endphp

@section('content')
    <header class="flex items-start justify-between gap-6 border-b border-slate-300 pb-4">
        <div>
            <h1 class="text-lg font-semibold tracking-tight text-slate-900">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-slate-600">Car rental services &middot; Station Road, Mwanza, Tanzania</p>
        </div>
        <div class="text-right">
            <p class="text-xs uppercase tracking-wide text-slate-500">Official receipt</p>
            <p class="text-base font-semibold text-slate-900">{{ $payment->receipt_number }}</p>
            <p class="text-sm text-slate-600">{{ ($payment->paid_at ?? $payment->created_at)->format('d M Y') }}</p>
        </div>
    </header>

    <main class="mt-6">
        <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Payment details</h2>
        <table class="mt-3 w-full text-sm">
            <tbody class="divide-y divide-slate-100">
                <tr>
                    <th class="w-44 py-2 text-left font-medium text-slate-500">Received from</th>
                    <td class="py-2 text-slate-900">{{ $rental?->customer?->display_name ?? 'Walk-in customer' }}</td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Vehicle</th>
                    <td class="py-2 text-slate-900">
                        {{ $rental?->vehicle?->make ?? '—' }} {{ $rental?->vehicle?->model }}
                        <span class="text-slate-500">({{ $rental?->vehicle?->plate_number ?? 'no plate on file' }})</span>
                    </td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Rental period</th>
                    <td class="py-2 text-slate-900">
                        @if ($rental)
                            {{ $rental->start_date->format('d M Y') }} &ndash; {{ $rental->end_date->format('d M Y') }}
                            <span class="text-slate-500">({{ $rental->days }} {{ $rental->days === 1 ? 'day' : 'days' }})</span>
                        @else
                            &mdash;
                        @endif
                    </td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Hire total</th>
                    <td class="py-2 text-slate-900">
                        @if ($rental)
                            {{ number_format($rental->total_amount, 0) }} TZS
                        @else
                            &mdash;
                        @endif
                    </td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Amount paid</th>
                    <td class="py-2 text-lg font-semibold text-slate-900">{{ number_format($payment->amount, 0) }} TZS</td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Method</th>
                    <td class="py-2 text-slate-900">{{ $payment->payment_method->label() }}</td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Reference</th>
                    <td class="py-2 text-slate-900">{{ $payment->transaction_reference ?: '—' }}</td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Payment status</th>
                    <td class="py-2 text-slate-900">{{ $payment->status->label() }}</td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Balance remaining</th>
                    <td class="py-2 text-slate-900">{{ number_format($remaining, 0) }} TZS</td>
                </tr>
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Received by</th>
                    <td class="py-2 text-slate-900">{{ $payment->recordedBy?->name ?? 'System' }}</td>
                </tr>
            </tbody>
        </table>

        @if ($payment->notes)
            <p class="mt-4 rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-600">{{ $payment->notes }}</p>
        @endif

        <div class="mt-10 flex items-end justify-between gap-8 text-sm">
            <div>
                <p class="text-slate-500">Customer signature</p>
                <div class="mt-8 w-48 border-t border-slate-400"></div>
            </div>
            <div class="text-right">
                <p class="text-slate-500">Authorised by {{ config('app.name') }}</p>
                <div class="mt-8 w-48 border-t border-slate-400"></div>
            </div>
        </div>

        <p class="mt-8 text-center text-xs text-slate-500">
            Keep this receipt. It is proof of payment for rental {{ $rental?->id ?? '' }} and is not transferable.
        </p>
    </main>
@endsection

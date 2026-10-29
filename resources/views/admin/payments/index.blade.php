@extends('layouts.app')

@section('title', 'Payments')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Payments" description="Every receipt recorded by staff, newest first.">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.index') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Record a payment
            </a>
            <a href="{{ route('admin.reports.show', 'payments') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Payments report
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Collected (filtered)" :value="number_format($collected, 0).' TZS'" hint="Excludes refunded receipts" />
        <x-stat label="Outstanding" :value="number_format($outstanding, 0).' TZS'" hint="Balance on open rentals" />
        <x-stat label="Open rentals" :value="$openRentals" hint="Active or overdue hires" />
        <x-stat label="Receipts shown" :value="$payments->total()" hint="Matching the filters" />
    </div>

    <x-card class="live-search-card mb-6">
        <form method="GET" action="{{ route('admin.payments.index') }}" class="grid gap-x-4 sm:grid-cols-2 lg:grid-cols-5" data-live-search>
            <x-search-box class="lg:col-span-2" name="q" :value="request('q')" placeholder="Receipt number or customer name" />
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
                <label for="method" class="mb-1 block text-sm font-medium text-slate-700">Method</label>
                <select id="method" name="method" class="{{ $selectClass }}" data-live-apply>
                    <option value="">All methods</option>
                    @foreach ($methods as $value => $label)
                        <option value="{{ $value }}" @selected(request('method') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-2 gap-x-4">
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
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Receipt</th>
                        <th class="py-2 pr-4">Customer</th>
                        <th class="py-2 pr-4">Vehicle</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Method</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Taken by</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($payments as $payment)
                        <tr>
                            <td class="py-3 pr-4 whitespace-nowrap text-slate-600">
                                {{ ($payment->paid_at ?? $payment->created_at)->format('d M Y') }}
                            </td>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $payment->receipt_number }}</td>
                            <td class="py-3 pr-4">
                                @if ($payment->rental?->customer)
                                    <a href="{{ route('admin.customers.show', $payment->rental->customer) }}"
                                       class="text-emerald-700 hover:underline">{{ $payment->rental->customer->display_name }}</a>
                                @else
                                    <span class="text-slate-500">&mdash;</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-slate-600">{{ $payment->rental?->vehicle?->plate_number ?? '—' }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ number_format($payment->amount, 0) }} TZS</td>
                            <td class="py-3 pr-4">
                                <span class="text-slate-700">{{ $payment->payment_method->label() }}</span>
                                @if ($payment->transaction_reference)
                                    <span class="block text-xs text-slate-500">{{ $payment->transaction_reference }}</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4"><x-badge :value="$payment->status->value" /></td>
                            <td class="py-3 pr-4 text-slate-600">{{ $payment->recordedBy?->name ?? 'System' }}</td>
                            <td class="py-3 whitespace-nowrap">
                                @if ($payment->rental)
                                    <a href="{{ route('admin.rentals.show', $payment->rental) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Rental</a>
                                @endif
                                <a href="{{ route('admin.payments.receipt', $payment) }}"
                                   class="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-100">Receipt</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-slate-500">No payments match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $payments->links() }}
        </div>
    </x-card>
@endsection

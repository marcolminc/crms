@php
    $vehicle = $rental->vehicle;
    $canRefund = $canRefund ?? false;
@endphp

<header class="flex items-start justify-between gap-6 border-b border-slate-300 pb-4">
    <div>
        <h1 class="text-lg font-semibold tracking-tight text-slate-900">{{ config('app.name') }}</h1>
        <p class="mt-1 text-sm text-slate-600">Car rental services &middot; Station Road, Mwanza, Tanzania</p>
    </div>
    <div class="text-right">
        <p class="text-xs uppercase tracking-wide text-slate-500">Settlement invoice</p>
        <p class="text-base font-semibold text-slate-900">INV-{{ $rental->id }}</p>
        <p class="text-sm text-slate-600">{{ now()->format('d M Y') }}</p>
    </div>
</header>

<main class="mt-6">
    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Billed to</h2>
    <table class="mt-3 w-full text-sm">
        <tbody class="divide-y divide-slate-100">
            <tr>
                <th class="w-44 py-2 text-left font-medium text-slate-500">Customer</th>
                <td class="py-2 text-slate-900">{{ $rental->customer?->display_name ?? 'Walk-in customer' }}</td>
            </tr>
            <tr>
                <th class="py-2 text-left font-medium text-slate-500">Vehicle</th>
                <td class="py-2 text-slate-900">
                    {{ $vehicle?->make ?? '—' }} {{ $vehicle?->model }}
                    <span class="text-slate-500">({{ $vehicle?->plate_number ?? 'no plate on file' }})</span>
                </td>
            </tr>
            <tr>
                <th class="py-2 text-left font-medium text-slate-500">Rental period</th>
                <td class="py-2 text-slate-900">
                    {{ $rental->start_date->format('d M Y') }} &ndash; {{ $rental->end_date->format('d M Y') }}
                    <span class="text-slate-500">({{ $rental->days }} {{ $rental->days === 1 ? 'day' : 'days' }})</span>
                </td>
            </tr>
        </tbody>
    </table>

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Charges</h2>
    <table class="mt-3 w-full text-sm">
        <tbody class="divide-y divide-slate-100">
            <tr>
                <th class="py-2 text-left font-medium text-slate-500">Hire &mdash; {{ number_format($rental->rate_per_day, 0) }} TZS &times; {{ $rental->days }} day(s)</th>
                <td class="py-2 text-right text-slate-900">{{ number_format($rental->subtotal, 0) }} TZS</td>
            </tr>
            @if ((float) $rental->driver_fee > 0)
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Office driver</th>
                    <td class="py-2 text-right text-slate-900">{{ number_format($rental->driver_fee, 0) }} TZS</td>
                </tr>
            @endif
            @if ((float) $rental->additional_charges > 0)
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Damage &amp; other charges</th>
                    <td class="py-2 text-right text-slate-900">{{ number_format($rental->additional_charges, 0) }} TZS</td>
                </tr>
            @endif
            @if ((float) $rental->late_fee > 0)
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Late return</th>
                    <td class="py-2 text-right text-slate-900">{{ number_format($rental->late_fee, 0) }} TZS</td>
                </tr>
            @endif
            @if ((float) $rental->discount > 0)
                <tr>
                    <th class="py-2 text-left font-medium text-slate-500">Discount</th>
                    <td class="py-2 text-right text-slate-900">-{{ number_format($rental->discount, 0) }} TZS</td>
                </tr>
            @endif
            <tr class="border-t-2 border-slate-300">
                <th class="py-2 text-left font-semibold text-slate-800">Total</th>
                <td class="py-2 text-right text-base font-semibold text-slate-900">{{ number_format($rental->total_amount, 0) }} TZS</td>
            </tr>
            <tr>
                <th class="py-2 text-left font-medium text-slate-500">Payments collected</th>
                <td class="py-2 text-right text-slate-900">{{ number_format($paid, 0) }} TZS</td>
            </tr>
            @if ($surplus > 0)
                <tr>
                    <th class="py-2 text-left font-semibold text-emerald-700">Refund due to customer</th>
                    <td class="py-2 text-right font-semibold text-emerald-700">{{ number_format($surplus, 0) }} TZS</td>
                </tr>
            @else
                <tr>
                    <th class="py-2 text-left font-semibold text-slate-800">Balance due</th>
                    <td class="py-2 text-right font-semibold {{ $balance > 0 ? 'text-rose-700' : 'text-emerald-700' }}">{{ number_format($balance, 0) }} TZS</td>
                </tr>
            @endif
        </tbody>
    </table>

    @if ($rental->payments->isNotEmpty())
        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Payment history</h2>
        <table class="mt-3 w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <th class="py-2 pr-4">Receipt</th>
                    <th class="py-2 pr-4">Date</th>
                    <th class="py-2 pr-4">Method</th>
                    <th class="py-2 text-right">Amount</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rental->payments as $payment)
                    <tr>
                        <td class="py-2 pr-4 text-slate-900">{{ $payment->receipt_number }}</td>
                        <td class="py-2 pr-4 whitespace-nowrap">{{ ($payment->paid_at ?? $payment->created_at)->format('d M Y') }}</td>
                        <td class="py-2 pr-4">
                            {{ $payment->payment_method?->label() }}
                            @if ($payment->type !== \App\Enums\PaymentType::Payment)
                                <span class="text-slate-500">({{ $payment->type->label() }})</span>
                            @endif
                        </td>
                        <td class="py-2 text-right whitespace-nowrap">{{ number_format($payment->amount, 0) }} TZS</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @isset($note)
        <p class="mt-6 rounded-md border border-slate-200 px-3 py-2 text-sm text-slate-600">{{ $note }}</p>
    @endisset

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
        This invoice is a statement of account for rental {{ $rental->id }} and is not a proof of payment on its own.
    </p>
</main>

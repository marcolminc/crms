@extends('layouts.print')

@section('title', 'Invoice INV-'.$rental->id)

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('customer.rentals.show', $rental) }}"
           class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">&larr; Back to rental</a>
        <button type="button" onclick="window.print()"
                class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print</button>
    </div>

    @include('rentals.partials.invoice-details', [
        'rental' => $rental,
        'paid' => $paid,
        'balance' => $balance,
        'surplus' => $surplus,
    ])

    @if ($surplus > 0)
        <p class="mt-4 text-center text-sm text-emerald-700">
            You are owed a refund of {{ number_format($surplus, 0) }} TZS. Our team will settle it to your mobile money wallet.
        </p>
    @endif
@endsection

@extends('layouts.print')

@section('title', 'Invoice INV-'.$rental->id)

@php
    $canSettle = auth()->user()?->hasRole(
        \App\Enums\UserRole::Finance,
        \App\Enums\UserRole::BranchManager,
        \App\Enums\UserRole::Admin
    ) ?? false;
@endphp

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.rentals.show', $rental) }}"
           class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">&larr; Back to rental</a>
        <div class="flex items-center gap-2">
            <button type="button" onclick="window.print()"
                    class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Print</button>
            @if ($surplus > 0 && $canSettle)
                <a href="{{ route('admin.rentals.refund', $rental) }}"
                   class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Issue refund</a>
            @elseif ($balance > 0)
                <a href="{{ route('admin.payments.create', $rental) }}"
                   class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Record payment</a>
            @endif
        </div>
    </div>

    @include('rentals.partials.invoice-details', [
        'rental' => $rental,
        'paid' => $paid,
        'balance' => $balance,
        'surplus' => $surplus,
    ])
@endsection

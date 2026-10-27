@extends('layouts.app')

@section('title', 'Reports')

@section('content')
    <x-page-header title="Reports" description="Pick a report. Each one covers the current month unless you set a date range." />

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $item)
            <a href="{{ route('admin.reports.show', $item['key']) }}"
               class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm hover:border-emerald-300 hover:bg-emerald-50/40">
                <h3 class="text-sm font-semibold text-slate-900 group-hover:text-emerald-800">{{ $item['label'] }}</h3>
                <p class="mt-2 text-sm text-slate-600">{{ $item['description'] }}</p>
                <p class="mt-4 text-xs font-medium uppercase tracking-wide text-emerald-700">Open report</p>
            </a>
        @endforeach
    </div>

    <x-card title="Money on the street" class="mt-8">
        <p class="text-sm text-slate-600">
            Staff screens answer most day-to-day questions already:
            <a href="{{ route('admin.payments.index') }}" class="font-medium text-emerald-700 hover:underline">all receipts</a>,
            <a href="{{ route('admin.rentals.index') }}" class="font-medium text-emerald-700 hover:underline">open hires</a> and
            <a href="{{ route('admin.reservations.index') }}" class="font-medium text-emerald-700 hover:underline">reservation requests</a>.
        </p>
    </x-card>
@endsection

@extends('layouts.app')

@section('title', 'Edit Rental #'.$rental->id)

@php
    $today = now()->toDateString();
@endphp

@section('content')
    <x-page-header title="Edit Rental" :description="'Rental #'.$rental->id.' — '.($rental->vehicle?->display_name ?? 'Removed vehicle')">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.show', $rental) }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to rental</a>
        </x-slot:actions>
    </x-page-header>

    @if ($rental->status === \App\Enums\RentalStatus::Cancelled)
        <x-alert type="error">A cancelled rental cannot be edited.</x-alert>
    @endif

    <form method="POST" action="{{ route('admin.rentals.update', $rental) }}">
        @csrf
        @method('PUT')
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Start date</label>
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        {{ $rental->start_date->format('d M Y') }}
                    </p>
                </div>
                <x-field label="Drop-off date" name="end_date" type="date"
                         :value="$rental->end_date->format('Y-m-d')" required min="{{ $rental->start_date->format('Y-m-d') }}" />
                <div class="mb-4">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Rate / day (locked)</label>
                    <p class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                        {{ number_format($rental->rate_per_day, 0) }} TZS
                    </p>
                </div>

                <x-field label="Additional charges (TZS)" name="additional_charges" type="number" :value="$rental->additional_charges" min="0" step="0.01" max="9999999999" />
                <x-field label="Discount (TZS)" name="discount" type="number" :value="$rental->discount" min="0" step="0.01" max="9999999999" />
                <x-field label="Pick-up location" name="pickup_location" :value="$rental->pickup_location" maxlength="255" />
                <x-field label="Drop-off location" name="dropoff_location" :value="$rental->dropoff_location" maxlength="255" />

                <div class="mb-4 sm:col-span-2 lg:col-span-3">
                    <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                    <textarea id="notes" name="notes" rows="3" maxlength="5000"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes', $rental->notes) }}</textarea>
                    @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <p class="mb-4 text-xs text-slate-500">
                Changing the drop-off date recalculates the day count and total. The discount cannot push the total below
                {{ number_format($rental->paidAmount(), 0) }} TZS already paid.
            </p>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Save changes
                </button>
                <a href="{{ route('admin.rentals.show', $rental) }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection

@extends('layouts.app')

@section('title', 'Return Vehicle')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
    $nowLocal = now()->format('Y-m-d\TH:i');
    $vehicle = $rental->vehicle;
@endphp

@section('content')
    <x-page-header title="Record Vehicle Return" :description="'Rental #'.$rental->id.' — '.($vehicle?->display_name ?? 'Removed vehicle')">
        <x-slot:actions>
            <a href="{{ route('admin.rentals.show', $rental) }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to rental</a>
        </x-slot:actions>
    </x-page-header>

    <form method="POST" action="{{ route('admin.returns.store', $rental) }}" enctype="multipart/form-data">
        @csrf
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-field label="Returned at" name="returned_at" type="datetime-local" :value="$nowLocal" required :max="$nowLocal" />

                <div class="mb-4">
                    <label for="condition" class="mb-1 block text-sm font-medium text-slate-700">Condition <span class="text-rose-600">*</span></label>
                    <select id="condition" name="condition" class="{{ $selectClass }}" required>
                        @foreach ($conditions as $value => $label)
                            <option value="{{ $value }}" @selected(old('condition') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('condition')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <x-field label="Fuel level" name="fuel_level" :value="old('fuel_level')" placeholder="Full, 3/4, Half..." />

                <x-field label="Mileage at return (km)" name="mileage_at_return" type="number"
                         :value="$vehicle?->mileage" required />
                <x-field label="Additional / damage charges (TZS)" name="additional_charges" type="number" :value="old('additional_charges', 0)" />

                <div class="mb-4 sm:col-span-2 lg:col-span-3">
                    <label for="damage_notes" class="mb-1 block text-sm font-medium text-slate-700">Damage notes</label>
                    <textarea id="damage_notes" name="damage_notes" rows="3"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('damage_notes') }}</textarea>
                    @error('damage_notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <p class="mb-4 text-xs text-slate-500">
                Extra charges are added to the rental total. A Damaged condition sends the vehicle to maintenance; otherwise it
                returns to the fleet. Current rental balance before return: {{ number_format($rental->balanceDue(), 0) }} TZS.
            </p>

            <div class="mb-6 rounded-xl border border-slate-200 bg-slate-50/40 p-4" data-photo-manager>
                <h3 class="mb-1 text-sm font-semibold text-slate-800">Return condition photos</h3>
                <p class="mb-4 text-xs text-slate-500">Photograph the vehicle at drop-off from each angle as evidence of its condition.</p>
                <x-photo-slots :map="[]" :subject="null" prefix="photos" />
            </div>

            <div class="flex items-center gap-3">
                <button type="submit"
                        class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                    Save return
                </button>
                <a href="{{ route('admin.rentals.show', $rental) }}" class="text-sm text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </x-card>
    </form>
@endsection

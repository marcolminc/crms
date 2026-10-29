@extends('layouts.app')

@section('title', 'New reservation')

@section('content')
    <x-page-header title="Request a reservation"
                   description="Choose a vehicle and the dates you need it. Our fleet team will confirm shortly." />

    <form method="POST" action="{{ route('customer.reservations.store') }}" class="grid gap-6 lg:grid-cols-3">
        @csrf

        <div class="lg:col-span-2">
            <x-card title="Booking details">
                <div class="mb-4">
                    <label for="vehicle" class="mb-1 block text-sm font-medium text-slate-700">
                        Vehicle <span class="text-rose-600">*</span>
                    </label>
                    <select id="vehicle" name="vehicle" required
                            class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm">
                        <option value="">Select a vehicle</option>
                        @foreach ($vehicles as $vehicle)
                            <option value="{{ $vehicle->id }}"
                                    @selected((string) old('vehicle', $selectedVehicle) === (string) $vehicle->id)>
                                {{ $vehicle->display_name }} &mdash; {{ number_format($vehicle->rate_per_day, 0) }} TZS/day
                                ({{ $vehicle->type->label() }}, {{ $vehicle->seats }} seats)
                            </option>
                        @endforeach
                    </select>
                    @error('vehicle')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    @if ($vehicles->isEmpty())
                        <p class="mt-1 text-xs text-amber-700">
                            No vehicles are free for the selected period. Pick different dates and try again.
                        </p>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Pick-up date" name="start_date" type="date" :value="$from" required min="{{ now()->toDateString() }}" />
                    <x-field label="Drop-off date" name="end_date" type="date" :value="$to" required min="{{ now()->toDateString() }}" />
                </div>

                {{-- FR15: optional office driver (chauffeur) chosen at booking --}}
                <div class="mb-4 rounded-lg border border-slate-200 bg-slate-50/60 p-3">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="needs_driver" value="1" @checked(old('needs_driver'))
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                        <span class="text-sm text-slate-700">
                            I would like an office driver (chauffeur) for this hire.
                            <span class="block text-xs text-slate-500">
                                Adds {{ number_format($driverRate, 0) }} TZS per day to your estimate. We assign an available driver before pick-up.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="mb-4">
                    <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notes (optional)</label>
                    <textarea id="notes" name="notes" rows="4" maxlength="2000"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">{{ old('notes') }}</textarea>
                    @error('notes')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            </x-card>
        </div>

        <div>
            <x-card title="Before you book">
                <ul class="list-disc space-y-1 pl-5 text-sm text-slate-600">
                    <li>The estimated total is the daily rate multiplied by the number of days.</li>
                    <li>Adding an office driver increases the estimate by the chauffeur's daily rate.</li>
                    <li>The drop-off date must be on or after the pick-up date.</li>
                    <li>The pick-up date cannot be in the past.</li>
                    <li>A single booking can run for up to 90 days.</li>
                    <li>Every request needs confirmation from our team before it is final.</li>
                </ul>
                <button type="submit"
                        class="mt-5 w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">
                    Submit reservation
                </button>
                <a href="{{ route('customer.reservations.index') }}"
                   class="mt-2 block text-center text-sm text-slate-500 hover:text-slate-700">Cancel</a>
            </x-card>
        </div>
    </form>
@endsection

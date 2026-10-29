@extends('layouts.guest')

@section('title', $vehicle->display_name)

@section('content')
    <a href="{{ route('vehicles.index') }}" class="text-sm text-slate-500 hover:text-slate-800">&larr; Back to the fleet</a>

    <div class="mt-3">
        <x-page-header :title="$vehicle->display_name" :description="'Plate '.$vehicle->plate_number">
            <x-slot:actions>
                <x-badge :value="$vehicle->status->value" />
            </x-slot:actions>
        </x-page-header>
    </div>

    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <x-vehicle-carousel :vehicle="$vehicle" ratio="aspect-[16/9]" :eager="true" />
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Specifications">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Make</dt><dd class="font-medium text-slate-800">{{ $vehicle->make }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Model</dt><dd class="font-medium text-slate-800">{{ $vehicle->model }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Type</dt><dd class="font-medium text-slate-800">{{ $vehicle->type->label() }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Seats</dt><dd class="font-medium text-slate-800">{{ $vehicle->seats }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Transmission</dt><dd class="font-medium text-slate-800">{{ ucfirst($vehicle->transmission) }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Fuel</dt><dd class="font-medium text-slate-800">{{ ucfirst($vehicle->fuel_type) }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Year</dt><dd class="font-medium text-slate-800">{{ $vehicle->year ?: '&mdash;' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Colour</dt><dd class="font-medium text-slate-800">{{ $vehicle->colour ?: '&mdash;' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Mileage</dt>
                        <dd class="font-medium text-slate-800">{{ $vehicle->mileage !== null ? number_format($vehicle->mileage, 0).' km' : '&mdash;' }}</dd>
                    </div>
                    <div class="flex justify-between border-b border-slate-100 pb-2">
                        <dt class="text-slate-500">Status</dt>
                        <dd><x-badge :value="$vehicle->status->value" /></dd>
                    </div>
                </dl>

                @if ($vehicle->description)
                    <p class="mt-4 text-sm leading-relaxed text-slate-600">{{ $vehicle->description }}</p>
                @endif
            </x-card>

            <x-card title="Features">
                @if (! empty($vehicle->features))
                    <ul class="grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
                        @foreach ($vehicle->features as $feature)
                            <li class="flex items-center gap-2">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-500">No extra features listed for this vehicle.</p>
                @endif
            </x-card>
        </div>

        <div>
            <x-card title="Book this vehicle">
                <p class="text-2xl font-semibold text-slate-900">
                    {{ number_format($vehicle->rate_per_day, 0) }} TZS
                    <span class="text-sm font-normal text-slate-500">/ day</span>
                </p>

                @guest
                    <p class="mt-3 text-sm text-slate-600">Sign in to check availability and request a booking.</p>
                    <a href="{{ route('login') }}"
                       class="mt-4 block rounded-md bg-emerald-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-emerald-500">
                        Sign in to book
                    </a>
                @else
                    @if ($canBook)
                        @if ($hasWindow && ! $isFree)
                            <x-alert type="error" class="mt-4">This vehicle is already booked for the selected period. Please choose different dates.</x-alert>
                        @elseif ($hasWindow && $isFree)
                            <x-alert type="success" class="mt-4">This vehicle is free for the selected period.</x-alert>
                        @endif

                        <form method="GET" action="{{ route('customer.reservations.create') }}" class="mt-4 space-y-3">
                            <input type="hidden" name="vehicle" value="{{ $vehicle->id }}">
                            <div>
                                <label for="from" class="mb-1 block text-sm font-medium text-slate-700">Pick-up date</label>
                                <input type="date" id="from" name="from" value="{{ $from }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            </div>
                            <div>
                                <label for="to" class="mb-1 block text-sm font-medium text-slate-700">Drop-off date</label>
                                <input type="date" id="to" name="to" value="{{ $to }}"
                                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm shadow-sm">
                            </div>
                            <button type="submit"
                                    @disabled($hasWindow && ! $isFree)
                                    class="w-full rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-slate-300">
                                Continue to booking
                            </button>
                        </form>
                    @else
                        <p class="mt-3 text-sm text-slate-600">
                            You are signed in with a staff account. Manage vehicles and reservations from the
                            <a href="{{ route('admin.dashboard') }}" class="text-emerald-700 underline">admin dashboard</a>.
                        </p>
                    @endif
                @endguest
            </x-card>
        </div>
    </div>
@endsection

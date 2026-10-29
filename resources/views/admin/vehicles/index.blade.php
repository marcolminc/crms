@extends('layouts.app')

@section('title', 'Fleet')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Vehicle Fleet" description="Every vehicle registered for Land Valley's Travels.">
        <x-slot:actions>
            <a href="{{ route('admin.vehicles.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Add Vehicle
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
        @foreach (App\Enums\VehicleStatus::cases() as $status)
            <x-stat :label="$status->label()" :value="$counts[$status->value] ?? 0" />
        @endforeach
    </div>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.vehicles.index') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-6 lg:items-end">
            <div class="lg:col-span-2">
                <x-field label="Search" name="q" :value="request('q')" placeholder="Plate, make or model" />
            </div>
            <div class="mb-4">
                <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Type</label>
                <select id="type" name="type" class="{{ $selectClass }}">
                    <option value="">All types</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status" class="{{ $selectClass }}">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <x-field label="Min rate" name="min_rate" type="number" :value="request('min_rate')" />
            </div>
            <div>
                <x-field label="Max rate" name="max_rate" type="number" :value="request('max_rate')" />
            </div>
            <div class="sm:col-span-2 lg:col-span-6">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('admin.vehicles.index') }}" class="ml-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Photo</th>
                        <th class="py-2 pr-4">Plate</th>
                        <th class="py-2 pr-4">Vehicle</th>
                        <th class="py-2 pr-4">Type</th>
                        <th class="py-2 pr-4">Seats</th>
                        <th class="py-2 pr-4">Rate / day</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Maintenance</th>
                        <th class="py-2 pr-4">Change status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($vehicles as $vehicle)
                        <tr>
                            <td class="py-3 pr-4">
                                @if ($vehicle->hasPhotos())
                                    <img src="{{ $vehicle->photoUrl($vehicle->gallery()[0]) }}"
                                         alt="" class="h-12 w-20 rounded-md object-cover">
                                @else
                                    <div class="flex h-12 w-20 items-center justify-center rounded-md bg-slate-100 text-[10px] font-medium text-slate-400">None</div>
                                @endif
                            </td>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $vehicle->plate_number }}</td>
                            <td class="py-3 pr-4">
                                {{ $vehicle->make }} {{ $vehicle->model }}
                                <span class="text-slate-500">{{ $vehicle->year }}</span>
                            </td>
                            <td class="py-3 pr-4">{{ $vehicle->type->label() }}</td>
                            <td class="py-3 pr-4">{{ $vehicle->seats }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ number_format($vehicle->rate_per_day, 0) }} TZS</td>
                            <td class="py-3 pr-4"><x-badge :value="$vehicle->status->value" /></td>
                            <td class="py-3 pr-4">
                                <a href="{{ route('admin.maintenances.index', ['vehicle' => $vehicle->id]) }}"
                                   class="text-emerald-700 hover:underline">Records</a>
                            </td>
                            <td class="py-3 pr-4">
                                <form method="POST" action="{{ route('admin.vehicles.status', $vehicle) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <select name="status" class="rounded-lg border border-slate-300 bg-white px-2 py-1 text-xs shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        @foreach (App\Enums\VehicleStatus::manuallyAssignable() as $option)
                                            <option value="{{ $option->value }}" @selected($vehicle->status === $option)>{{ $option->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="rounded-lg border border-slate-300 px-2 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Set</button>
                                </form>
                            </td>
                            <td class="py-3 whitespace-nowrap">
                                <a href="{{ route('admin.vehicles.edit', $vehicle) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('admin.vehicles.destroy', $vehicle) }}" class="ml-1 inline"
                                      onsubmit="return confirm('Remove {{ $vehicle->plate_number }} from the fleet?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-6 text-center text-slate-500">No vehicles match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $vehicles->links() }}
        </div>
    </x-card>
@endsection

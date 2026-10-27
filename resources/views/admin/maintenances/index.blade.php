@extends('layouts.app')

@section('title', 'Maintenance')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Vehicle Maintenance" description="Scheduled and completed workshop work.">
        <x-slot:actions>
            <a href="{{ route('admin.maintenances.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Add Maintenance Record
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.maintenances.index') }}" class="grid gap-2 sm:grid-cols-3 lg:items-end">
            <div class="mb-4">
                <label for="vehicle" class="mb-1 block text-sm font-medium text-slate-700">Vehicle</label>
                <select id="vehicle" name="vehicle" class="{{ $selectClass }}">
                    <option value="">All vehicles</option>
                    @foreach ($vehicles as $option)
                        <option value="{{ $option->id }}" @selected((string) request('vehicle') === (string) $option->id)>
                            {{ $option->plate_number }} — {{ $option->make }} {{ $option->model }}
                        </option>
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
            <div class="mb-4">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('admin.maintenances.index') }}" class="ml-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Vehicle</th>
                        <th class="py-2 pr-4">Work</th>
                        <th class="py-2 pr-4">Provider</th>
                        <th class="py-2 pr-4">Start</th>
                        <th class="py-2 pr-4">End</th>
                        <th class="py-2 pr-4">Cost</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($maintenances as $maintenance)
                        <tr>
                            <td class="py-3 pr-4">
                                @if ($maintenance->vehicle)
                                    <a href="{{ route('admin.maintenances.index', ['vehicle' => $maintenance->vehicle_id]) }}"
                                       class="font-medium text-emerald-700 hover:underline">{{ $maintenance->vehicle->plate_number }}</a>
                                    <div class="text-xs text-slate-500">{{ $maintenance->vehicle->make }} {{ $maintenance->vehicle->model }}</div>
                                @else
                                    <span class="text-slate-400">Removed vehicle</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">{{ $maintenance->title }}</td>
                            <td class="py-3 pr-4">{{ $maintenance->service_provider ?? '—' }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ $maintenance->start_date->format('d M Y') }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ $maintenance->end_date?->format('d M Y') ?? '—' }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">{{ $maintenance->cost !== null ? number_format($maintenance->cost, 0).' TZS' : '—' }}</td>
                            <td class="py-3 pr-4"><x-badge :value="$maintenance->status->value" /></td>
                            <td class="py-3 whitespace-nowrap">
                                <a href="{{ route('admin.maintenances.edit', $maintenance) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('admin.maintenances.destroy', $maintenance) }}" class="ml-1 inline"
                                      onsubmit="return confirm('Delete this maintenance record for {{ $maintenance->vehicle?->plate_number ?? 'the vehicle' }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-slate-500">No maintenance records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $maintenances->links() }}
        </div>
    </x-card>
@endsection

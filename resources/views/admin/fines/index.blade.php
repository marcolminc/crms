@extends('layouts.app')

@section('title', 'Traffic fines')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header
        title="Traffic Fines"
        description="Fines raised at return inspection. Decide liability, then bill the customer or absorb." />

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat label="Pending" :value="$pendingCount" />
        <x-stat label="Billed to customers" :value="number_format($outstandingBilled, 0).' TZS'" />
        <x-stat label="Absorbed by company" :value="number_format($absorbedTotal, 0).' TZS'" />
    </div>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.fines.index') }}" class="grid gap-2 sm:grid-cols-3 lg:items-end">
            <div class="mb-4">
                <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
                <select id="status" name="status" class="{{ $selectClass }}">
                    <option value="">All statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4 sm:col-span-2">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('admin.fines.index') }}" class="ml-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Rental</th>
                        <th class="py-2 pr-4">Offence</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Liability</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Decision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($fines as $fine)
                        <tr class="align-top">
                            <td class="py-3 pr-4">
                                <a href="{{ route('admin.fines.create', $fine->rental_id) }}" class="font-medium text-emerald-700 hover:underline">
                                    #{{ $fine->rental_id }}
                                </a>
                                <span class="block text-xs text-slate-500">
                                    {{ $fine->vehicle?->display_name ?? $fine->rental?->vehicle?->display_name ?? 'Vehicle removed' }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">
                                {{ $fine->description ?? '—' }}
                                <span class="block text-xs text-slate-500">
                                    {{ $fine->reference }}
                                    @if ($fine->issued_at)
                                        &middot; {{ $fine->issued_at->format('d M Y') }}
                                    @endif
                                </span>
                            </td>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ number_format((float) $fine->amount, 0) }} TZS</td>
                            <td class="py-3 pr-4">{{ $fine->liability?->label() ?? 'Unassigned' }}</td>
                            <td class="py-3 pr-4"><x-badge :value="$fine->status->value" /></td>
                            <td class="py-3 whitespace-nowrap">
                                @if ($fine->isPending())
                                    <div class="flex items-center gap-1">
                                        <form method="POST" action="{{ route('admin.fines.bill', $fine) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Bill customer</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.fines.absorb', $fine) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Absorb</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">
                                        {{ $fine->decider?->name ?? 'System' }} &middot; {{ $fine->decided_at?->format('d M Y H:i') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">No traffic fines match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $fines->links() }}
        </div>
    </x-card>
@endsection

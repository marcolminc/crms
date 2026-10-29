@extends('layouts.app')

@section('title', 'Approvals')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header
        title="Charge Approvals"
        description="Material discounts and damage charges awaiting manager sign-off." />

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.approvals.index') }}" class="grid gap-2 sm:grid-cols-3 lg:items-end">
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
                <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Type</label>
                <select id="type" name="type" class="{{ $selectClass }}">
                    <option value="">All types</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('admin.approvals.index') }}" class="ml-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
            </div>
        </form>
    </x-card>

    <p class="mb-3 text-sm text-slate-600">
        <span class="font-semibold text-slate-900">{{ $pendingCount }}</span>
        {{ $pendingCount === 1 ? 'request is' : 'requests are' }} pending review.
    </p>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Rental</th>
                        <th class="py-2 pr-4">Type</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Requested by</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2">Decision</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($approvals as $approval)
                        <tr class="align-top">
                            <td class="py-3 pr-4">
                                <a href="{{ route('admin.rentals.show', $approval->rental_id) }}" class="font-medium text-emerald-700 hover:underline">
                                    #{{ $approval->rental_id }}
                                </a>
                                <span class="block text-xs text-slate-500">
                                    {{ $approval->rental?->vehicle?->display_name ?? 'Vehicle removed' }}
                                    &middot; {{ $approval->rental?->customer?->display_name ?? 'Customer removed' }}
                                </span>
                            </td>
                            <td class="py-3 pr-4">{{ $approval->type->label() }}</td>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ number_format((float) $approval->amount, 0) }} TZS</td>
                            <td class="py-3 pr-4">
                                {{ $approval->requester?->name ?? '—' }}
                                <span class="block text-xs text-slate-500">{{ $approval->created_at->format('d M Y H:i') }}</span>
                                @if ($approval->reason)
                                    <span class="mt-1 block max-w-xs text-xs text-slate-500">{{ $approval->reason }}</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                <x-badge :value="$approval->status->value" />
                                @if ($approval->reviewer)
                                    <span class="mt-1 block text-xs text-slate-500">by {{ $approval->reviewer->name }}</span>
                                @endif
                            </td>
                            <td class="py-3">
                                @if ($approval->isPending())
                                    <div class="flex flex-col gap-2">
                                        <form method="POST" action="{{ route('admin.approvals.approve', $approval) }}">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.approvals.reject', $approval) }}" class="flex items-center gap-1"
                                              onsubmit="return confirm('Reject and reverse this charge on the rental?')">
                                            @csrf
                                            @method('PUT')
                                            <input type="text" name="note" required placeholder="Rejection reason"
                                                   class="w-40 rounded-lg border border-slate-300 px-2 py-1 text-xs focus:border-rose-400 focus:ring-rose-400">
                                            <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Reject</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">
                                        Reviewed {{ $approval->reviewed_at?->format('d M Y H:i') }}
                                        @if ($approval->review_note)
                                            <span class="block max-w-xs text-slate-500">&ldquo;{{ $approval->review_note }}&rdquo;</span>
                                        @endif
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">No charge approvals match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $approvals->links() }}
        </div>
    </x-card>
@endsection

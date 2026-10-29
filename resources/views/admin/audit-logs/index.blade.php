@extends('layouts.app')

@section('title', 'Audit log')

@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

@section('content')
    <x-page-header title="Audit Log" description="Append-only trail of sensitive actions taken in the system.">
        <x-slot:actions>
            <a href="{{ route('admin.audit-log.index', array_merge(request()->query(), ['from' => now()->subdays(30)->toDateString()])) }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                Last 30 days
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.audit-log.index') }}" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 lg:items-end">
            <div class="mb-4 lg:col-span-2">
                <x-field label="Search" name="q" :value="request('q')" placeholder="Description or IP address" />
            </div>
            <div class="mb-4">
                <label for="action" class="mb-1 block text-sm font-medium text-slate-700">Action</label>
                <select id="action" name="action" class="{{ $selectClass }}">
                    <option value="">All actions</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <label for="user" class="mb-1 block text-sm font-medium text-slate-700">Actor</label>
                <select id="user" name="user" class="{{ $selectClass }}">
                    <option value="">Any actor</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-4">
                <x-field label="From" type="date" name="from" :value="request('from')" />
            </div>
            <div class="mb-4">
                <x-field label="To" type="date" name="to" :value="request('to')" />
            </div>
            <div class="mb-4 sm:col-span-2">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Apply filters</button>
                <a href="{{ route('admin.audit-log.index') }}" class="ml-2 text-sm text-slate-500 hover:text-slate-800">Reset</a>
            </div>
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">When</th>
                        <th class="py-2 pr-4">Actor</th>
                        <th class="py-2 pr-4">Action</th>
                        <th class="py-2 pr-4">Detail</th>
                        <th class="py-2">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr class="align-top">
                            <td class="whitespace-nowrap py-3 pr-4 text-slate-500">{{ $log->created_at->format('d M Y H:i') }}</td>
                            <td class="py-3 pr-4">
                                <span class="font-medium text-slate-900">{{ $log->user?->name ?? 'System / guest' }}</span>
                                @if ($log->user)
                                    <span class="block text-xs text-slate-500">{{ $log->user->email }}</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">
                                <x-badge :value="$log->action" />
                            </td>
                            <td class="py-3 pr-4 text-slate-700">
                                {{ $log->description }}
                                @if (! empty($log->properties))
                                    <pre class="mt-1 whitespace-pre-wrap break-words rounded bg-slate-50 p-2 text-xs text-slate-500">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                @endif
                            </td>
                            <td class="py-3 whitespace-nowrap text-xs text-slate-500">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-500">No audit entries match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $logs->links() }}
        </div>
    </x-card>
@endsection

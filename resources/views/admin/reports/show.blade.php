@extends('layouts.app')

@section('title', $report['title'])

@section('content')
    <x-page-header :title="$report['title']" :description="'Rows from '.$from->format('d M Y').' to '.$to->format('d M Y').'.'">
        <x-slot:actions>
            <a href="{{ route('admin.reports.index') }}"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                All reports
            </a>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Download CSV
            </a>
        </x-slot:actions>
    </x-page-header>

    <x-card class="mb-6">
        <form method="GET" action="{{ route('admin.reports.show', $key) }}" class="grid gap-2 sm:grid-cols-3 lg:items-end">
            <x-field label="From" name="from" type="date" :value="$from->format('Y-m-d')" />
            <x-field label="To" name="to" type="date" :value="$to->format('Y-m-d')" />
            <div class="mb-4">
                <button type="submit" class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Run report</button>
                <a href="{{ route('admin.reports.show', [$key, 'from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->endOfMonth()->format('Y-m-d')]) }}"
                   class="ml-2 text-sm text-slate-500 hover:text-slate-800">This month</a>
            </div>
        </form>
    </x-card>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        @foreach ($report['summary'] as $label => $value)
            <x-stat :label="$label" :value="$value" />
        @endforeach
    </div>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        @foreach ($report['columns'] as $label)
                            <th class="py-2 pr-4">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($report['rows'] as $row)
                        <tr>
                            @foreach (array_keys($report['columns']) as $column)
                                <td class="py-3 pr-4 align-top text-slate-700">{{ $row[$column] }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report['columns']) }}" class="py-6 text-center text-slate-500">
                                Nothing was recorded in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            {{ count($report['rows']) }} {{ count($report['rows']) === 1 ? 'row' : 'rows' }} &middot;
            <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="font-medium text-emerald-700 hover:underline">Download CSV</a>
        </p>
    </x-card>
@endsection

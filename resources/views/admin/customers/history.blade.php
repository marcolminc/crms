@extends('layouts.app')

@section('title', $customer->display_name.' — History')

@section('content')
    <x-page-header :title="$customer->display_name.' — Account history'" description="Chronological ledger of bookings, rentals and payments.">
        <x-slot:actions>
            <a href="{{ route('admin.customers.show', $customer) }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back to profile</a>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        @if ($entries->isEmpty())
            <p class="text-sm text-slate-500">No history recorded for this customer.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Type</th>
                            <th class="py-2 pr-4">Record</th>
                            <th class="py-2 pr-4">Amount</th>
                            <th class="py-2 pr-4">Paid</th>
                            <th class="py-2 pr-4">Balance</th>
                            <th class="py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($entries as $entry)
                            <tr>
                                <td class="py-3 pr-4 whitespace-nowrap">{{ $entry['date']->format('d M Y') }}</td>
                                <td class="py-3 pr-4 capitalize text-slate-500">{{ $entry['kind'] }}</td>
                                <td class="py-3 pr-4">{{ $entry['title'] }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap">
                                    {{ number_format($entry['amount'], 0) }} TZS
                                    <span class="text-xs text-slate-400">{{ $entry['label'] }}</span>
                                </td>
                                <td class="py-3 pr-4 whitespace-nowrap">{{ isset($entry['paid']) ? number_format($entry['paid'], 0).' TZS' : '—' }}</td>
                                <td class="py-3 pr-4 whitespace-nowrap">{{ isset($entry['balance']) ? number_format($entry['balance'], 0).' TZS' : '—' }}</td>
                                <td class="py-3"><x-badge :value="$entry['status']" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
@endsection

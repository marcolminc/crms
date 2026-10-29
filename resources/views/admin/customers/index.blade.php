@extends('layouts.app')

@section('title', 'Customers')

@section('content')
    <x-page-header title="Customers" description="Every customer registered with Land Valley's Travels.">
        <x-slot:actions>
            <a href="{{ route('admin.customers.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add Customer</a>
        </x-slot:actions>
    </x-page-header>

    <x-card class="live-search-card mb-6">
        <form method="GET" action="{{ route('admin.customers.index') }}" data-live-search>
            <x-search-box name="q" :value="request('q')" placeholder="Name, email, phone or national ID" />
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Customer</th>
                        <th class="py-2 pr-4">Contact</th>
                        <th class="py-2 pr-4">National ID</th>
                        <th class="py-2 pr-4">City</th>
                        <th class="py-2 pr-4">Reservations</th>
                        <th class="py-2 pr-4">Rentals</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($customers as $customer)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">{{ $customer->display_name }}</td>
                            <td class="py-3 pr-4">
                                @if ($customer->user)
                                    <div>{{ $customer->user->email }}</div>
                                    <div class="text-xs text-slate-500">{{ $customer->user->phone ?? 'No phone' }}</div>
                                @else
                                    <span class="text-slate-400">No login account</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">{{ $customer->national_id ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $customer->city ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $customer->reservations_count }}</td>
                            <td class="py-3 pr-4">{{ $customer->rentals_count }}</td>
                            <td class="py-3 whitespace-nowrap">
                                <a href="{{ route('admin.customers.show', $customer) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">View</a>
                                <a href="{{ route('admin.customers.edit', $customer) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>
                                <form method="POST" action="{{ route('admin.customers.destroy', $customer) }}" class="ml-1 inline"
                                      onsubmit="return confirm('Delete {{ $customer->display_name }}? This is blocked if they have bookings or payments.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-6 text-center text-slate-500">No customers match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    </x-card>
@endsection

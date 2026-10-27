@extends('layouts.app')

@section('title', $customer->display_name)

@section('content')
    <x-page-header :title="$customer->display_name" description="Customer profile and recent bookings.">
        <x-slot:actions>
            <a href="{{ route('admin.customers.history', $customer) }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Full history</a>
            <a href="{{ route('admin.customers.edit', $customer) }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Edit</a>
            <a href="{{ route('admin.customers.index') }}"
               class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Back</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-3">
        <div>
            <x-card title="Profile">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Account</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $customer->user?->name ?? 'No login account' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Email</dt>
                        <dd class="text-right text-slate-800">{{ $customer->user?->email ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Phone</dt>
                        <dd class="text-right text-slate-800">{{ $customer->user?->phone ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">National ID</dt>
                        <dd class="text-right text-slate-800">{{ $customer->national_id ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Passport</dt>
                        <dd class="text-right text-slate-800">{{ $customer->passport_no ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Driving licence</dt>
                        <dd class="text-right text-slate-800">{{ $customer->driving_license_no ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Date of birth</dt>
                        <dd class="text-right text-slate-800">{{ $customer->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">City</dt>
                        <dd class="text-right text-slate-800">{{ $customer->city ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Address</dt>
                        <dd class="text-right text-slate-800">{{ $customer->address ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-500">Emergency</dt>
                        <dd class="text-right text-slate-800">
                            {{ $customer->emergency_contact_name ?? '—' }}
                            {{ $customer->emergency_contact_phone ? ' ('.$customer->emergency_contact_phone.')' : '' }}
                        </dd>
                    </div>
                </dl>
                @if ($customer->notes)
                    <div class="mt-4">
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Notes</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-700">{{ $customer->notes }}</p>
                    </div>
                @endif
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-card title="Recent reservations">
                @if ($reservations->isEmpty())
                    <p class="text-sm text-slate-500">No reservations yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="py-2 pr-4">#</th>
                                    <th class="py-2 pr-4">Vehicle</th>
                                    <th class="py-2 pr-4">Period</th>
                                    <th class="py-2 pr-4">Estimated</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($reservations as $reservation)
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <a href="{{ route('admin.reservations.show', $reservation) }}" class="text-emerald-700 hover:underline">#{{ $reservation->id }}</a>
                                        </td>
                                        <td class="py-2 pr-4">{{ $reservation->vehicle?->display_name ?? '—' }}</td>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ $reservation->start_date->format('d M Y') }} &ndash; {{ $reservation->end_date->format('d M Y') }}</td>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ number_format($reservation->estimated_total, 0) }} TZS</td>
                                        <td class="py-2"><x-badge :value="$reservation->status->value" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-card title="Recent rentals">
                @if ($rentals->isEmpty())
                    <p class="text-sm text-slate-500">No rentals yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                                    <th class="py-2 pr-4">#</th>
                                    <th class="py-2 pr-4">Vehicle</th>
                                    <th class="py-2 pr-4">Period</th>
                                    <th class="py-2 pr-4">Total</th>
                                    <th class="py-2 pr-4">Balance</th>
                                    <th class="py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($rentals as $rental)
                                    <tr>
                                        <td class="py-2 pr-4">
                                            <a href="{{ route('admin.rentals.show', $rental) }}" class="text-emerald-700 hover:underline">#{{ $rental->id }}</a>
                                        </td>
                                        <td class="py-2 pr-4">{{ $rental->vehicle?->display_name ?? '—' }}</td>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ $rental->start_date->format('d M Y') }} &ndash; {{ $rental->end_date->format('d M Y') }}</td>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ number_format($rental->total_amount, 0) }} TZS</td>
                                        <td class="py-2 pr-4 whitespace-nowrap">{{ number_format($rental->balanceDue(), 0) }} TZS</td>
                                        <td class="py-2"><x-badge :value="$rental->status->value" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>
        </div>
    </div>
@endsection

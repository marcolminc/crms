@extends('layouts.app')

@section('title', 'Traffic fines')

@section('content')
    <x-page-header
        :title="'Traffic fines · Rental #'.$rental->id"
        :description="$rental->vehicle?->display_name.' · '.($rental->customer?->display_name ?? 'Customer')" />

    <div class="grid gap-6 lg:grid-cols-2">
        <x-card>
            <h2 class="mb-4 text-base font-semibold text-slate-900">Record a fine</h2>
            <form method="POST" action="{{ route('admin.fines.store', $rental) }}" class="space-y-4">
                @csrf
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field label="Reference" name="reference" :value="old('reference')" placeholder="Authority / ticket no." />
                    <x-field label="Amount (TZS)" name="amount" type="number" step="0.01" min="0" :value="old('amount')" required />
                </div>
                <x-field label="Offence" name="description" :value="old('description')" placeholder="e.g. Speeding, red light" />
                <div>
                    <label for="issued_at" class="mb-1 block text-sm font-medium text-slate-700">Issued on</label>
                    <input type="date" id="issued_at" name="issued_at" value="{{ old('issued_at') }}"
                           class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
                </div>
                <div>
                    <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
                    <textarea id="notes" name="notes" rows="3"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes') }}</textarea>
                </div>
                <button type="submit" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">Add fine</button>
            </form>
        </x-card>

        <x-card>
            <h2 class="mb-4 text-base font-semibold text-slate-900">Fines on this rental</h2>
            @if ($existingFines->isEmpty())
                <p class="text-sm text-slate-500">No fines recorded yet.</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($existingFines as $fine)
                        <li class="flex items-start justify-between gap-3 py-3">
                            <div>
                                <p class="font-medium text-slate-900">{{ $fine->description ?? 'Traffic fine' }}</p>
                                <p class="text-xs text-slate-500">
                                    {{ $fine->reference }} &middot; {{ $fine->issued_at?->format('d M Y') ?? 'date n/a' }}
                                    &middot; by {{ $fine->recorder?->name ?? '—' }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="font-medium text-slate-900">{{ number_format((float) $fine->amount, 0) }} TZS</p>
                                <x-badge :value="$fine->status->value" />
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif

            <p class="mt-4 text-sm text-slate-500">
                Manage decisions (bill or absorb) from the
                <a href="{{ route('admin.fines.index') }}" class="font-medium text-emerald-700 hover:underline">Traffic fines queue</a>.
            </p>
        </x-card>
    </div>
@endsection

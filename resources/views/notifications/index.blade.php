@extends('layouts.app')

@section('title', 'Alerts')

@section('content')
    <x-page-header title="Alerts" description="Reservation, rental and payment notifications sent to your account." />

    @if ($notifications->isEmpty())
        <x-card>
            <p class="text-sm text-slate-600">You have no alerts yet.</p>
            <a href="{{ route('vehicles.index') }}"
               class="mt-4 inline-block rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                Browse vehicles
            </a>
        </x-card>
    @else
        <div class="space-y-3">
            @foreach ($notifications as $alert)
                <div @class([
                    'rounded-xl border bg-white p-4 shadow-sm',
                    'border-slate-200' => $alert->read_at !== null,
                    'border-emerald-300 bg-emerald-50/50' => $alert->read_at === null,
                ])>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-sm font-semibold text-slate-900">{{ $alert->title }}</h3>
                                @if ($alert->read_at === null)
                                    <span class="rounded-full bg-emerald-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">New</span>
                                @endif
                                @if ($alert->type)
                                    <x-badge :value="$alert->type" />
                                @endif
                            </div>

                            <p class="mt-1 text-sm text-slate-600">{{ $alert->message }}</p>
                            <p class="mt-2 text-xs text-slate-400">{{ $alert->created_at->format('d M Y') }}</p>

                            @if ($alert->link && $alert->read_at === null)
                                {{-- Readme: opening the details marks the alert read in one step. --}}
                                <a href="{{ route('notifications.open', $alert) }}" class="mt-2 inline-block text-sm font-medium text-emerald-700 hover:underline">
                                    Open details
                                </a>
                            @endif
                        </div>

                        @if ($alert->read_at === null && ! $alert->link)
                            <form method="POST" action="{{ route('notifications.read', $alert) }}">
                                @csrf
                                @method('PUT')
                                <button type="submit"
                                        class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">
                                    Acknowledge
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            {{ $notifications->links() }}
        </div>
    @endif
@endsection

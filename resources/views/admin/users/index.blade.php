@extends('layouts.app')

@section('title', 'Users')

@section('content')
    <x-page-header title="System Users" description="Staff and customer accounts.">
        <x-slot:actions>
            <a href="{{ route('admin.users.create') }}"
               class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                Add User
            </a>
        </x-slot:actions>
    </x-page-header>

    {{-- Readme: dynamic keyword search replaced the old search + role filter
         form; app.js submits as the visitor types, × clears. --}}
    <x-card class="live-search-card mb-6">
        <form method="GET" action="{{ route('admin.users.index') }}" data-live-search>
            <x-search-box name="q" :value="request('q')" placeholder="Name, email or phone" />
        </form>
    </x-card>

    <x-card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-slate-500">
                        <th class="py-2 pr-4">Name</th>
                        <th class="py-2 pr-4">Email</th>
                        <th class="py-2 pr-4">Phone</th>
                        <th class="py-2 pr-4">Role</th>
                        <th class="py-2 pr-4">Account</th>
                        <th class="py-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($users as $user)
                        <tr>
                            <td class="py-3 pr-4 font-medium text-slate-900">
                                {{ $user->name }}
                                @if ($user->is(auth()->user()))
                                    <span class="ml-1 text-xs font-normal text-emerald-700">(you)</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4">{{ $user->email }}</td>
                            <td class="py-3 pr-4">{{ $user->phone ?? '—' }}</td>
                            <td class="py-3 pr-4">{{ $user->role?->label() ?? '—' }}</td>
                            <td class="py-3 pr-4">
                                <x-badge :value="$user->is_active ? 'active' : 'inactive'" />
                            </td>
                            <td class="py-3 whitespace-nowrap">
                                <a href="{{ route('admin.users.edit', $user) }}"
                                   class="rounded-lg border border-slate-300 px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50">Edit</a>

                                @unless ($user->is(auth()->user()))
                                    @if ($user->is_active)
                                        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" class="ml-1 inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="rounded-lg border border-amber-200 px-3 py-1 text-xs font-medium text-amber-700 hover:bg-amber-50">Deactivate</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.activate', $user) }}" class="ml-1 inline">
                                            @csrf
                                            @method('PUT')
                                            <button type="submit" class="rounded-lg border border-emerald-200 px-3 py-1 text-xs font-medium text-emerald-700 hover:bg-emerald-50">Activate</button>
                                        </form>
                                    @endif

                                    @if ($user->customer?->reservations()->exists() || $user->customer?->rentals()->exists())
                                        {{-- Mirrors the destroy() guard: booking history must survive for reporting. --}}
                                        <span class="ml-1 inline-block cursor-help text-xs text-slate-400" title="This account belongs to a customer with booking history, so it can only be deactivated — records stay available for reporting.">Delete blocked</span>
                                    @else
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" class="ml-1 inline"
                                              onsubmit="return confirm('Delete {{ $user->name }} permanently? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-lg border border-rose-200 px-3 py-1 text-xs font-medium text-rose-700 hover:bg-rose-50">Delete</button>
                                        </form>
                                    @endif
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">No users match the current filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </x-card>
@endsection

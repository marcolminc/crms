<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
<div class="min-h-full">
    <nav class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6">
            <div class="flex items-center gap-6">
                <a href="{{ auth()->user()?->isStaff() ? route('admin.dashboard') : route('customer.dashboard') }}"
                   class="text-base font-semibold tracking-tight text-emerald-700">
                    {{ config('app.name') }}
                </a>
                @php
                    $links = auth()->user()?->isStaff()
                        ? [
                            ['label' => 'Overview', 'route' => 'admin.dashboard'],
                            ['label' => 'Vehicles', 'route' => 'admin.vehicles.index'],
                            ['label' => 'Reservations', 'route' => 'admin.reservations.index'],
                            ['label' => 'Rentals', 'route' => 'admin.rentals.index'],
                            ['label' => 'Payments', 'route' => 'admin.payments.index'],
                            ['label' => 'Customers', 'route' => 'admin.customers.index'],
                            ['label' => 'Reports', 'route' => 'admin.reports.index'],
                        ]
                        : [
                            ['label' => 'Overview', 'route' => 'customer.dashboard'],
                            ['label' => 'Browse vehicles', 'route' => 'vehicles.index'],
                            ['label' => 'My reservations', 'route' => 'customer.reservations.index'],
                            ['label' => 'My rentals', 'route' => 'customer.rentals.index'],
                        ];
                @endphp
                <div class="hidden items-center gap-1 md:flex">
                    @foreach ($links as $link)
                        <a href="{{ route($link['route']) }}"
                           @class([
                               'rounded-md px-3 py-2 text-sm font-medium',
                               'bg-emerald-50 text-emerald-700' => request()->routeIs($link['route'].'*'),
                               'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs($link['route'].'*'),
                           ])>
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                    @if (auth()->user()?->isStaff() && auth()->user()->hasRole(\App\Enums\UserRole::Admin))
                        <a href="{{ route('admin.users.index') }}"
                           @class([
                               'rounded-md px-3 py-2 text-sm font-medium',
                               'bg-emerald-50 text-emerald-700' => request()->routeIs('admin.users.*'),
                               'text-slate-600 hover:bg-slate-50 hover:text-slate-900' => ! request()->routeIs('admin.users.*'),
                           ])>Users</a>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-4">
                @php $unread = auth()->user()?->unreadNotifications()->count() ?? 0; @endphp
                <a href="{{ route('notifications.index') }}" class="relative text-sm text-slate-600 hover:text-slate-900">
                    Alerts
                    @if ($unread > 0)
                        <span class="absolute -right-3 -top-2 rounded-full bg-rose-600 px-1.5 text-[10px] font-semibold text-white">{{ $unread }}</span>
                    @endif
                </a>
                <span class="hidden text-sm text-slate-500 sm:inline">{{ auth()->user()?->name }}</span>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif
        @if ($errors->any())
            <x-alert type="error">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        @yield('content')
    </main>
</div>
</body>
</html>

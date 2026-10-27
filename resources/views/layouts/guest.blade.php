<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">
<div class="min-h-full">
    <header class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center">
                <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }} logo" class="h-14 w-auto sm:h-16">
            </a>
            <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                <span class="truncate text-lg font-bold tracking-tight text-slate-900">Land Valley&rsquo;s Travels</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('vehicles.index') }}" class="rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">Browse vehicles</a>
                @auth
                    <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : (auth()->user()->isDriver() ? route('notifications.index') : route('customer.dashboard')) }}"
                       class="rounded-lg bg-emerald-700 px-3.5 py-2 font-medium text-white hover:bg-emerald-600">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-emerald-700 px-3.5 py-2 font-medium text-white hover:bg-emerald-600">Sign up</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        @if (session('status'))
            <x-alert type="success" :auto-dismiss="true">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="error" :auto-dismiss="true">{{ session('error') }}</x-alert>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">
<div class="min-h-full">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-4 sm:px-6">
            <a href="{{ route('home') }}" class="text-lg font-semibold tracking-tight text-emerald-700">
                {{ config('app.name') }}
            </a>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('vehicles.index') }}" class="text-slate-600 hover:text-slate-900">Browse vehicles</a>
                @auth
                    <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : route('customer.dashboard') }}"
                       class="rounded-md bg-emerald-600 px-3 py-1.5 font-medium text-white hover:bg-emerald-500">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-slate-600 hover:text-slate-900">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-md bg-emerald-600 px-3 py-1.5 font-medium text-white hover:bg-emerald-500">Sign up</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 py-10 sm:px-6">
        @if (session('status'))
            <x-alert type="success">{{ session('status') }}</x-alert>
        @endif
        @if (session('error'))
            <x-alert type="error">{{ session('error') }}</x-alert>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>

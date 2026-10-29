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
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-2 sm:px-6">
            <a href="{{ route('home') }}" class="flex items-center">
                <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }} logo" class="h-11 w-auto sm:h-12">
            </a>
            <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                <span class="truncate text-lg font-bold tracking-tight text-slate-900">Land Valley&rsquo;s Travels</span>
            </div>
            <div class="flex items-center gap-3 text-sm">
                <div class="relative" data-menu-root>
                    <button type="button" data-menu-toggle aria-haspopup="true"
                            class="flex items-center gap-1 rounded-lg px-3 py-2 font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">
                        Company
                        <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                        </svg>
                    </button>
                    <div data-menu-panel hidden
                         class="absolute right-0 top-full z-50 mt-2 w-52 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                        <a href="{{ route('pages.about') }}"
                           @class(['block rounded-lg px-3 py-2 font-medium', 'bg-emerald-50 text-emerald-700' => request()->routeIs('pages.about'), 'text-slate-700 hover:bg-slate-50' => ! request()->routeIs('pages.about')])>About us</a>
                        <a href="{{ route('pages.contact') }}"
                           @class(['block rounded-lg px-3 py-2 font-medium', 'bg-emerald-50 text-emerald-700' => request()->routeIs('pages.contact'), 'text-slate-700 hover:bg-slate-50' => ! request()->routeIs('pages.contact')])>Contact us</a>
                        <a href="{{ route('pages.terms') }}"
                           @class(['block rounded-lg px-3 py-2 font-medium', 'bg-emerald-50 text-emerald-700' => request()->routeIs('pages.terms'), 'text-slate-700 hover:bg-slate-50' => ! request()->routeIs('pages.terms')])>Terms &amp; conditions</a>
                    </div>
                </div>
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
<script>
    // Dependency-free dropdown controller for the Company menu, mirroring the
    // Menu panel behaviour on the signed-in layout.
    document.querySelectorAll('[data-menu-root]').forEach((root) => {
        const toggle = root.querySelector('[data-menu-toggle]');
        const panel = root.querySelector('[data-menu-panel]');
        toggle.addEventListener('click', (event) => {
            event.stopPropagation();
            document.querySelectorAll('[data-menu-panel]').forEach((other) => {
                if (other !== panel) other.hidden = true;
            });
            panel.hidden = ! panel.hidden;
        });
        panel.addEventListener('click', (event) => event.stopPropagation());
    });
    document.addEventListener('click', () => {
        document.querySelectorAll('[data-menu-panel]').forEach((panel) => panel.hidden = true);
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-menu-panel]').forEach((panel) => panel.hidden = true);
        }
    });
</script>
</body>
</html>

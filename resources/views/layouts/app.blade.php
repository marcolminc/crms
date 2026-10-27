<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('logo.png') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
@php
    $user = auth()->user();

    /*
    | Navigation is grouped per actor (design doc §2) and rendered inside a
    | single Menu button; the breadcrumb strip mirrors the active group/page.
    */
    if ($user?->isStaff()) {
        $home = ['label' => 'Overview', 'route' => 'admin.dashboard'];
        $groups = [
            'Bookings & rentals' => [
                ['label' => 'Reservations', 'route' => 'admin.reservations.index'],
                ['label' => 'Rentals', 'route' => 'admin.rentals.index'],
            ],
            'Fleet' => [
                ['label' => 'Vehicles', 'route' => 'admin.vehicles.index'],
                ['label' => 'Maintenance', 'route' => 'admin.maintenances.index'],
            ],
            'Money' => [
                ['label' => 'Payments', 'route' => 'admin.payments.index'],
                ['label' => 'Reports', 'route' => 'admin.reports.index'],
            ],
            'People' => array_values(array_filter([
                ['label' => 'Customers', 'route' => 'admin.customers.index'],
                $user->hasRole(\App\Enums\UserRole::Admin)
                    ? ['label' => 'Users & roles', 'route' => 'admin.users.index']
                    : null,
            ])),
        ];
    } elseif ($user?->isDriver()) {
        // The trip-log portal (UC24) is not built yet; drivers keep account tools only.
        $home = ['label' => 'Alerts', 'route' => 'notifications.index'];
        $groups = [];
    } else {
        $home = ['label' => 'Overview', 'route' => 'customer.dashboard'];
        $groups = [
            'Book' => [
                ['label' => 'Browse vehicles', 'route' => 'vehicles.index', 'pattern' => 'vehicles.*'],
                ['label' => 'New reservation', 'route' => 'customer.reservations.create'],
            ],
            'My activity' => [
                ['label' => 'My reservations', 'route' => 'customer.reservations.index'],
                ['label' => 'My rentals', 'route' => 'customer.rentals.index'],
            ],
        ];
    }

    $isActive = function (array $link): bool {
        return request()->routeIs($link['pattern'] ?? $link['route'].'*');
    };

    // Breadcrumb trail: area home › active group › active page.
    $crumbs = [];
    if ($user !== null) {
        $crumbs[] = ['label' => $home['label'], 'route' => $home['route']];
        foreach ($groups as $groupLabel => $links) {
            foreach ($links as $link) {
                if ($isActive($link)) {
                    $crumbs[] = ['label' => $groupLabel];
                    $crumbs[] = ['label' => $link['label']];
                }
            }
        }
    }
    $unread = $user?->unreadNotifications()->count() ?? 0;
@endphp
<div class="min-h-full">
    <nav class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 shadow-sm backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-2.5 sm:px-6">
            <div class="flex items-center gap-3">
                @if ($user !== null && ! empty($groups))
                    <div class="relative" data-menu-root>
                        <button type="button" data-menu-toggle aria-haspopup="true"
                                class="flex items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/50 hover:text-slate-900">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                            Menu
                            <svg class="h-3.5 w-3.5 opacity-70" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>
                        <div data-menu-panel hidden
                             class="absolute left-0 top-full z-50 mt-2 max-h-[70vh] w-64 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                            <a href="{{ route($home['route']) }}"
                               @class([
                                   'mb-1 block rounded-lg px-3 py-2 text-sm font-medium',
                                   'bg-emerald-50 text-emerald-700' => request()->routeIs($home['route'].'*'),
                                   'text-slate-700 hover:bg-slate-50' => ! request()->routeIs($home['route'].'*'),
                               ])>{{ $home['label'] }}</a>
                            @foreach ($groups as $groupLabel => $links)
                                <p class="px-3 pb-1 pt-3 text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $groupLabel }}</p>
                                @foreach ($links as $link)
                                    <a href="{{ route($link['route']) }}"
                                       @class([
                                           'block rounded-lg px-3 py-2 text-sm font-medium',
                                           'bg-emerald-50 text-emerald-700' => $isActive($link),
                                           'text-slate-700 hover:bg-slate-50' => ! $isActive($link),
                                       ])>{{ $link['label'] }}</a>
                                @endforeach
                            @endforeach
                            <div class="my-2 border-t border-slate-100"></div>
                            <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Profile</a>
                        </div>
                    </div>
                @endif
                <a href="{{ $user?->isStaff() ? route('admin.dashboard') : ($user?->isDriver() ? route('notifications.index') : route('customer.dashboard')) }}"
                   class="flex items-center gap-2.5">
                    <img src="{{ asset('logo.png') }}" alt="{{ config('app.name') }} logo"
                         class="h-14 w-auto sm:h-16">
                </a>
            </div>

            {{-- Company name, centred between the brand block and the right-hand controls --}}
            <div class="hidden min-w-0 flex-1 items-center justify-center md:flex">
                <span class="truncate text-lg font-bold tracking-tight text-slate-900">Land Valley&rsquo;s Travels</span>
            </div>

            <div class="flex items-center gap-2.5 sm:gap-3">
                @auth
                    <a href="{{ route('notifications.index') }}" class="relative inline-flex rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Notifications{{ $unread > 0 ? ' ('.$unread.' unread)' : '' }}">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                        </svg>
                        @if ($unread > 0)
                            <span class="absolute right-0 top-0 z-10 flex h-4 min-w-4 -translate-y-1/3 translate-x-1/3 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-semibold leading-none text-white ring-2 ring-white">
                                {{ $unread > 99 ? '99+' : $unread }}
                            </span>
                        @endif
                    </a>

                    {{-- Visual separator between alerts and the account chip --}}
                    <span class="h-6 w-px bg-slate-200" aria-hidden="true"></span>

                    <div class="relative" data-menu-root>
                        <button type="button" data-menu-toggle aria-haspopup="true"
                                class="flex items-center gap-2 rounded-full border border-slate-200 bg-slate-50 py-1 pl-1 pr-2 text-sm font-medium text-slate-700 hover:border-emerald-300 hover:bg-emerald-50/50">
                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-emerald-700 text-xs font-semibold text-white">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </span>
                            <span class="hidden max-w-32 truncate sm:inline">{{ $user->name }}</span>
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/>
                            </svg>
                        </button>
                        <div data-menu-panel hidden
                             class="absolute right-0 top-full z-50 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-xl">
                            <div class="px-3 py-2">
                                <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                                <p class="mt-0.5 inline-block rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700">
                                    {{ $user->role?->label() }}
                                </p>
                            </div>
                            <div class="my-1 border-t border-slate-100"></div>
                            <a href="{{ route('profile.edit') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">My profile</a>
                            <a href="{{ route('notifications.index') }}" class="block rounded-lg px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                                Alerts @if ($unread > 0)<span class="ml-1 text-xs font-semibold text-rose-600">({{ $unread }})</span>@endif
                            </a>
                            <div class="my-1 border-t border-slate-100"></div>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm font-medium text-rose-700 hover:bg-rose-50">
                                    Log out
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-emerald-700 px-3.5 py-2 text-sm font-medium text-white hover:bg-emerald-600">Sign up</a>
                @endauth
            </div>
        </div>

        @if ($user !== null && $crumbs !== [])
            <div class="border-t border-slate-100 bg-slate-50/70">
                <div class="mx-auto flex max-w-7xl items-center gap-1.5 overflow-x-auto px-4 py-1.5 text-xs sm:px-6">
                    <a href="{{ route($crumbs[0]['route']) }}" class="shrink-0 font-medium text-slate-500 hover:text-emerald-700">{{ $crumbs[0]['label'] }}</a>
                    @foreach (array_slice($crumbs, 1) as $crumb)
                        <svg class="h-3 w-3 shrink-0 text-slate-300" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                        </svg>
                        @if (isset($crumb['route']))
                            <a href="{{ route($crumb['route']) }}" class="shrink-0 font-medium text-slate-500 hover:text-emerald-700">{{ $crumb['label'] }}</a>
                        @else
                            <span class="shrink-0 font-semibold text-slate-800">{{ $crumb['label'] }}</span>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </nav>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
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
    // Tiny dependency-free dropdown controller for the Menu and account panels.
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

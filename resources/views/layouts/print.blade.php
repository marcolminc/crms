<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', 'Document') &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-white text-slate-900">
<div class="mx-auto max-w-3xl p-8 print:p-0">
    @yield('content')
</div>
<div class="mt-8 text-center print:hidden">
    <button onclick="window.print()" class="rounded-md bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-500">Print</button>
    <a href="{{ url()->previous() }}" class="ml-2 text-sm text-slate-600 hover:text-slate-900">Back</a>
</div>
</body>
</html>

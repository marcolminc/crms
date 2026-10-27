@extends('layouts.guest')

@section('title', 'Log in')

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Log in to your account</h1>
        <p class="mt-1 text-sm text-slate-500">
            Customers manage reservations and rentals here; staff reach the operations dashboard with the same login.
        </p>

        @if ($errors->any())
            <x-alert type="error" class="mt-6">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <x-card class="mt-6">
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <x-field label="Email address" name="email" type="email" required />
                <x-field label="Password" name="password" type="password" required />

                <div class="mb-6 flex items-center gap-2">
                    <input type="checkbox" id="remember" name="remember" value="1"
                           @checked(old('remember'))
                           class="h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    <label for="remember" class="text-sm text-slate-700">Keep me logged in</label>
                    <a href="{{ route('password.request') }}" class="ml-auto text-sm font-medium text-emerald-700 hover:underline">Forgot password?</a>
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    Log in
                </button>
            </form>
        </x-card>

        <p class="mt-6 text-center text-sm text-slate-600">
            No account yet?
            <a href="{{ route('register') }}" class="font-medium text-emerald-700 hover:underline">Create one</a>
            or
            <a href="{{ route('vehicles.index') }}" class="font-medium text-emerald-700 hover:underline">browse vehicles</a>
            as a guest.
        </p>
    </div>
@endsection

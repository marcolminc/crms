@extends('layouts.guest')

@section('title', 'Create an account')

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Create your account</h1>
        <p class="mt-1 text-sm text-slate-500">
            Register once to reserve and rent vehicles from Land Valley's Travels, Mwanza.
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
            <form method="POST" action="{{ route('register') }}">
                @csrf

                <x-field label="Full name" name="name" required pattern="[A-Za-z]{2,}.*" hint="Letters only — a name made up entirely of numbers is not accepted." />
                <x-field label="Email address" name="email" type="email" required />
                <x-field label="Phone number" name="phone" type="tel" required />
                <x-password required />

                <button type="submit"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    Create account
                </button>
            </form>
        </x-card>

        <p class="mt-6 text-center text-sm text-slate-600">
            Already registered?
            <a href="{{ route('login') }}" class="font-medium text-emerald-700 hover:underline">Log in</a>
            or
            <a href="{{ route('vehicles.index') }}" class="font-medium text-emerald-700 hover:underline">browse our vehicles</a>
            first.
        </p>
    </div>
@endsection

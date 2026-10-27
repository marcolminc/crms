@extends('layouts.guest')

@section('title', 'Choose a new password')

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Choose a new password</h1>
        <p class="mt-1 text-sm text-slate-500">
            Use at least 8 characters with letters and numbers.
        </p>

        <x-card class="mt-6">
            <form method="POST" action="{{ route('password.update') }}">
                @csrf

                <input type="hidden" name="token" value="{{ $token }}">

                <x-field label="Email address" name="email" type="email" :value="$email ?? old('email')" required />
                <x-field label="New password" name="password" type="password" required />
                <x-field label="Confirm new password" name="password_confirmation" type="password" required />

                <button type="submit"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    Update password
                </button>
            </form>
        </x-card>
    </div>
@endsection

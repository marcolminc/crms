@extends('layouts.guest')

@section('title', 'Forgot password')

@section('content')
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-semibold tracking-tight text-slate-900">Reset your password</h1>
        <p class="mt-1 text-sm text-slate-500">
            Tell us the email you use for Land Valley&rsquo;s Travels and we will send you a link to choose a new password.
        </p>

        @if (session('status'))
            <x-alert type="success" :auto-dismiss="true" class="mt-6">{{ session('status') }}</x-alert>
        @endif

        <x-card class="mt-6">
            <form method="POST" action="{{ route('password.email') }}">
                @csrf

                <x-field label="Email address" name="email" type="email" required />

                <button type="submit"
                        class="w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                    Email me a reset link
                </button>
            </form>
        </x-card>

        <p class="mt-6 text-center text-sm text-slate-600">
            Remembered it?
            <a href="{{ route('login') }}" class="font-medium text-emerald-700 hover:underline">Back to log in</a>
        </p>
    </div>
@endsection

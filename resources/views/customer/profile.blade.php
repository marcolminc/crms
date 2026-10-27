@extends('layouts.app')

@section('title', 'My profile')

@section('content')
    <x-page-header title="My profile" description="Your login details and the paperwork staff check at pick-up." />

    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')

        <div class="grid gap-6 lg:grid-cols-2">
            <x-card title="Account">
                <fieldset class="space-y-0">
                    <legend class="sr-only">Account details</legend>
                    <x-field label="Full name" name="name" required :value="$user->name" />
                    <x-field label="Email address" name="email" type="email" required :value="$user->email" />
                    <x-field label="Phone number" name="phone" type="tel" :value="$user->phone" />
                </fieldset>
            </x-card>

            @if ($user->isCustomer())
            <x-card title="Rental details">
                <fieldset class="space-y-0">
                    <legend class="sr-only">Rental details</legend>
                    <x-field label="National ID / voter ID" name="national_id" :value="$customer?->national_id" />
                    <x-field label="Passport number" name="passport_no" :value="$customer?->passport_no" />
                    <x-field label="Driving licence number" name="driving_license_no" :value="$customer?->driving_license_no" />
                    <x-field label="Date of birth" name="date_of_birth" type="date" :value="$customer?->date_of_birth?->format('Y-m-d')" />
                    <x-field label="City" name="city" :value="$customer?->city" />
                    <x-field label="Address" name="address" :value="$customer?->address" />
                    <x-field label="Emergency contact name" name="emergency_contact_name" :value="$customer?->emergency_contact_name" />
                    <x-field label="Emergency contact phone" name="emergency_contact_phone" type="tel" :value="$customer?->emergency_contact_phone" />
                </fieldset>
            </x-card>
            @endif
        </div>

        <div class="mt-6">
            <button type="submit"
                    class="rounded-lg bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">
                Save changes
            </button>
        </div>
    </form>

    <x-card title="Change password" class="mt-8 max-w-lg">
        <form method="POST" action="{{ route('profile.password.update') }}">
            @csrf
            @method('PUT')

            <x-field label="Current password" name="current_password" type="password" required />
            <x-field label="New password" name="password" type="password" required />
            <x-field label="Confirm new password" name="password_confirmation" type="password" required />

            <button type="submit"
                    class="rounded-lg border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                Update password
            </button>
        </form>
    </x-card>
@endsection

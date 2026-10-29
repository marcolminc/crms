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
                    <x-field label="Full name" name="name" required :value="$user->name" pattern="[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'. -]*" minlength="2" placeholder="e.g. Ali Hamisi" />
                    <x-field label="Email address" name="email" type="email" required :value="$user->email" />
                    <x-field label="Phone number" name="phone" type="tel" :value="$user->phone" pattern="0[0-9]{9}" inputmode="numeric" maxlength="10" placeholder="e.g. 0123456789" />
                </fieldset>
            </x-card>

            @if ($user->isCustomer())
            <x-card title="Rental details">
                <fieldset class="space-y-0">
                    <legend class="sr-only">Rental details</legend>
                    <x-field label="National ID / voter ID" name="national_id" :value="$customer?->national_id" pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*" maxlength="32" placeholder="e.g. 12345678" />
                    <x-field label="Passport number" name="passport_no" :value="$customer?->passport_no" pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*" maxlength="32" placeholder="e.g. A1234567" />
                    <x-field label="Driving licence number" name="driving_license_no" :value="$customer?->driving_license_no" pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*" maxlength="32" placeholder="e.g. DL-1234567" />
                    <x-field label="Date of birth" name="date_of_birth" type="date" :value="$customer?->date_of_birth?->format('Y-m-d')" max="{{ now()->toDateString() }}" />
                    <x-field label="City" name="city" :value="$customer?->city" maxlength="100" placeholder="e.g. Mwanza" />
                    <x-field label="Address" name="address" :value="$customer?->address" maxlength="255" />
                    <x-field label="Emergency contact name" name="emergency_contact_name" :value="$customer?->emergency_contact_name" pattern="[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'. -]*" minlength="2" maxlength="255" placeholder="e.g. Ali Hamisi" />
                    <x-field label="Emergency contact phone" name="emergency_contact_phone" type="tel" :value="$customer?->emergency_contact_phone" pattern="0[0-9]{9}" inputmode="numeric" maxlength="10" placeholder="e.g. 0123456789" />
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
            <x-password label="New password" confirm-label="Confirm new password" required />

            <button type="submit"
                    class="rounded-lg border border-emerald-600 px-4 py-2 text-sm font-semibold text-emerald-700 hover:bg-emerald-50">
                Update password
            </button>
        </form>
    </x-card>
@endsection

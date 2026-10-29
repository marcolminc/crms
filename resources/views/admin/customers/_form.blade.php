@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div class="mb-4 lg:col-span-1">
        <label for="user_id" class="mb-1 block text-sm font-medium text-slate-700">Linked account</label>
        <select id="user_id" name="user_id" class="{{ $selectClass }}">
            <option value="">No login account (walk-in)</option>
            @foreach ($linkableUsers as $option)
                <option value="{{ $option->id }}" @selected((string) old('user_id', $customer?->user_id) === (string) $option->id)>
                    {{ $option->name }} — {{ $option->email }}
                </option>
            @endforeach
        </select>
        @error('user_id')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    <x-field label="National ID" name="national_id" :value="$customer?->national_id" pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*" maxlength="32" placeholder="e.g. 12345678" />
    <x-field label="Passport No" name="passport_no" :value="$customer?->passport_no" pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*" maxlength="32" placeholder="e.g. A1234567" />
    <x-field label="Driving Licence No" name="driving_license_no" :value="$customer?->driving_license_no" pattern="[A-Za-z0-9]+([ /\-][A-Za-z0-9]+)*" maxlength="32" placeholder="e.g. DL-1234567" />
    <x-field label="Date of birth" name="date_of_birth" type="date" :value="$customer?->date_of_birth?->format('Y-m-d')" max="{{ now()->subYears(18)->toDateString() }}" />
    <x-field label="City" name="city" :value="$customer?->city" maxlength="100" placeholder="e.g. Mwanza" />
    <x-field label="Address" name="address" :value="$customer?->address" maxlength="255" />
    <x-field label="Emergency contact name" name="emergency_contact_name" :value="$customer?->emergency_contact_name" pattern="[A-Za-z]{2,}.*" maxlength="255" placeholder="e.g. Ali Hamisi" />
    <x-field label="Emergency contact phone" name="emergency_contact_phone" type="tel" :value="$customer?->emergency_contact_phone" pattern="0[0-9]{9}" inputmode="numeric" maxlength="10" placeholder="e.g. 0123456789" />

    <div class="mb-4 sm:col-span-2 lg:col-span-3">
        <label for="notes" class="mb-1 block text-sm font-medium text-slate-700">Notes</label>
        <textarea id="notes" name="notes" rows="3" maxlength="5000"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('notes', $customer?->notes) }}</textarea>
        @error('notes')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>
</div>

<p class="text-xs text-slate-500">
    A walk-in customer can be saved without a login account. Name, email and phone are stored on the login
    account, so to record those details link an existing verified customer account above; otherwise only the
    profile fields on this form are kept and the record is shown as an unlinked customer. Date of birth must
    indicate the customer is at least 18 years old.
</p>

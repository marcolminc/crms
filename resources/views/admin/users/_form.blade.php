@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
    $isSelf = $user && $user->is(auth()->user());
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    <x-field label="Full Name" name="name" :value="$user->name ?? ''" required pattern="[A-Za-z]{2,}.*" hint="Letters only — a name made up entirely of numbers is not accepted." />
    <x-field label="Email" name="email" type="email" :value="$user->email ?? ''" required />
    <x-field label="Phone" name="phone" type="tel" :value="$user->phone ?? ''" placeholder="+255 7xx xxx xxx" pattern="\+?[0-9][0-9 .()\-]*[0-9]" hint="Digits only (spaces, dashes and a leading + are fine) — at least 7 digits." />

    <div class="mb-4">
        <label for="role" class="mb-1 block text-sm font-medium text-slate-700">Role <span class="text-rose-600">*</span></label>
        <select id="role" name="role" class="{{ $selectClass }}" required>
            @if ($isSelf)
                <option value="{{ $user->role?->value }}" selected>{{ $user->role?->label() }}</option>
            @else
                @foreach ($roles as $value => $label)
                    <option value="{{ $value }}" @selected(old('role', $user?->role?->value) === $value)>{{ $label }}</option>
                @endforeach
            @endif
        </select>
        @error('role')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
        @if ($isSelf)
            <p class="mt-1 text-xs text-slate-500">You cannot change your own role.</p>
        @endif
    </div>

    <x-password class="sm:col-span-2"
                :label="$user ? 'Password (leave blank to keep current)' : 'Password'"
                confirm-label="Confirm Password"
                :required="! $user"
                :blank-hint="$user ? 'Only fill both fields when you want to replace the password.' : null" />

    @if ($user && ! $isSelf)
        <div class="mb-4 sm:col-span-2">
            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1"
                       class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500"
                       @checked(old('is_active', $user->is_active))>
                Account active
            </label>
        </div>
    @elseif ($user)
        <p class="mb-4 text-sm text-slate-500 sm:col-span-2">This is your own account; it stays active and its role cannot be changed here.</p>
    @endif
</div>

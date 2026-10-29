@props(['label' => 'Password', 'name' => 'password', 'confirmName' => 'password_confirmation', 'confirmLabel' => 'Confirm password', 'required' => true, 'blankHint' => null])

{{--
    Password pair with a live strength meter (readme: password strength).
    The meter is advisory JavaScript only; the authoritative rule is
    Password::defaults() on the server (min 8, letters, numbers). The block
    ships hidden and is revealed by initPasswordMeter in app.js, so pages
    without JavaScript simply show the plain inputs.
--}}
<div {{ $attributes->merge(['class' => 'mb-4']) }} data-password-meter>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-slate-700">
        {{ $label }}@if ($required)<span class="text-rose-600"> *</span>@endif
    </label>
    <input type="password"
           id="{{ $name }}"
           name="{{ $name }}"
           autocomplete="new-password"
           @if ($required) required @endif
           data-password-input
           {{ $attributes->only(['minlength', 'maxlength'])->merge(['class' => 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500']) }}>
    @if ($blankHint)
        <p class="mt-1 text-xs text-slate-500">{{ $blankHint }}</p>
    @endif
    @error($name)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror

    <label for="{{ $confirmName }}" class="mb-1 mt-3 block text-sm font-medium text-slate-700">
        {{ $confirmLabel }}@if ($required)<span class="text-rose-600"> *</span>@endif
    </label>
    <input type="password"
           id="{{ $confirmName }}"
           name="{{ $confirmName }}"
           autocomplete="new-password"
           @if ($required) required @endif
           data-password-confirm
           {{ $attributes->only(['minlength', 'maxlength'])->merge(['class' => 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500']) }}>
    @error($confirmName)
        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
    @enderror

    <div hidden data-meter-panel class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3">
        <div class="flex items-center gap-2">
            <div class="flex h-1.5 flex-1 overflow-hidden rounded-full bg-slate-200" role="presentation">
                <div class="w-1/3 bg-rose-500 transition-opacity duration-200" data-meter-bar="1"></div>
                <div class="w-1/3 bg-amber-500 transition-opacity duration-200" data-meter-bar="2"></div>
                <div class="w-1/3 bg-emerald-500 transition-opacity duration-200" data-meter-bar="3"></div>
            </div>
            <span class="w-16 text-right text-xs font-medium text-slate-500" data-meter-label></span>
        </div>

        <ul class="mt-2 grid gap-1 text-xs text-slate-500 sm:grid-cols-2" data-meter-checks>
            <li data-check="length" class="flex items-center gap-1.5"><span class="w-4 text-center" data-check-mark>•</span> At least 8 characters</li>
            <li data-check="letters" class="flex items-center gap-1.5"><span class="w-4 text-center" data-check-mark>•</span> Contains letters</li>
            <li data-check="numbers" class="flex items-center gap-1.5"><span class="w-4 text-center" data-check-mark>•</span> Contains numbers</li>
            <li data-check="match" class="flex items-center gap-1.5 opacity-50" data-check-match-only><span class="w-4 text-center" data-check-mark>•</span> Passwords match</li>
        </ul>
    </div>
</div>

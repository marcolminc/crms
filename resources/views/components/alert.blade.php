@props(['type' => 'info'])

@php
    $styles = match ($type) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-sky-200 bg-sky-50 text-sky-800',
    };
@endphp

<div {{ $attributes->merge(['class' => "mb-6 rounded-lg border px-4 py-3 text-sm $styles"]) }}>
    {{ $slot }}
</div>

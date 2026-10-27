@props(['value'])

@php
    $colour = match (true) {
        in_array($value, ['available', 'paid', 'completed', 'confirmed']) => 'bg-emerald-100 text-emerald-800',
        in_array($value, ['pending', 'reserved', 'scheduled', 'partial']) => 'bg-amber-100 text-amber-800',
        in_array($value, ['active', 'in_progress', 'rented']) => 'bg-sky-100 text-sky-800',
        in_array($value, ['cancelled', 'rejected', 'overdue', 'damaged', 'out_of_service', 'maintenance']) => 'bg-rose-100 text-rose-800',
        default => 'bg-slate-100 text-slate-700',
    };
@endphp

<span {{ $attributes->merge(['class' => "inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize $colour"]) }}>
    {{ str_replace('_', ' ', $value ?? 'unknown') }}
</span>

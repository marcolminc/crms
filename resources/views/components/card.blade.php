@props(['title' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-slate-200 bg-white p-5 shadow-sm']) }}>
    @if ($title)
        <h3 class="mb-4 text-sm font-semibold text-slate-700">{{ $title }}</h3>
    @endif
    {{ $slot }}
</div>

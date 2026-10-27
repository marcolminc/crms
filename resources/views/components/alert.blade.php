@props(['type' => 'info', 'autoDismiss' => false])

@php
    $styles = match ($type) {
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'error' => 'border-rose-200 bg-rose-50 text-rose-800',
        'warning' => 'border-amber-200 bg-amber-50 text-amber-800',
        default => 'border-sky-200 bg-sky-50 text-sky-800',
    };
    // Errors deserve a little more reading time than successes.
    $dismissAfter = $type === 'error' ? 8000 : 5000;
@endphp

<div {{ $attributes->merge(['class' => "relative mb-6 rounded-lg border px-4 py-3 text-sm transition duration-500 ease-out $styles"]) }}
    @if ($autoDismiss) data-flash data-dismiss-after="{{ $dismissAfter }}" @endif>
    {{ $slot }}
    @if ($autoDismiss)
        <button type="button" data-flash-close aria-label="Dismiss"
                class="absolute right-2 top-2 rounded-md p-1 text-current opacity-50 transition hover:opacity-100">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
            </svg>
        </button>
    @endif
</div>
@if ($autoDismiss)
    @once
        <script>
            // Flash alerts fade out by themselves; the × dismisses immediately.
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-flash]').forEach((el) => {
                    const hide = () => {
                        el.style.opacity = '0';
                        el.style.transform = 'translateY(-0.5rem)';
                        setTimeout(() => el.remove(), 500);
                    };
                    el.querySelector('[data-flash-close]')?.addEventListener('click', hide);
                    setTimeout(hide, parseInt(el.dataset.dismissAfter, 10) || 5000);
                });
            });
        </script>
    @endonce
@endif

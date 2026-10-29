@props(['label' => 'Search', 'name' => 'q', 'value' => '', 'placeholder' => '', 'class' => null])

{{--
    Readme: dynamic keyword search. The form it lives in is marked
    data-live-search and app.js submits it as the visitor types, so there is
    no apply button; the × clears and re-searches instantly. Without
    JavaScript this degrades to a normal GET form field.
--}}
<div class="{{ $class }}">
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium text-slate-700">{{ $label }}</label>
    <div class="relative">
        <input type="text"
               id="{{ $name }}"
               name="{{ $name }}"
               value="{{ $value }}"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               data-live-q
               class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 pr-10 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">
        <button type="button"
                data-live-clear
                aria-label="Clear search"
                @if ($value === '') data-empty="1" @endif
                class="absolute right-2 top-1/2 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-full text-slate-400 hover:bg-slate-100 hover:text-slate-600">
            &times;
        </button>
    </div>
</div>

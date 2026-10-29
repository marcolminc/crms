@props(['vehicle', 'ratio' => 'aspect-[16/10]'])

@php
    $photos = $vehicle->gallery();
    $count = count($photos);
    $cover = $photos[0] ?? null;
    $name = $vehicle->display_name;
@endphp

{{--
    Static cover image for a vehicle card: the front photo (gallery()[0]) with
    a cue that a full gallery awaits on the detail page. The rotating carousel
    lives on vehicles/show, not here, so cards stay light.
--}}
<div {{ $attributes->merge(['class' => 'relative w-full overflow-hidden bg-slate-100 '.$ratio]) }}>
    @if ($cover)
        <img src="{{ $vehicle->photoUrl($cover['path']) }}"
             alt="{{ $name }}{{ $cover['label'] !== '' ? ' — '.$cover['label'] : '' }}"
             loading="lazy"
             class="h-full w-full object-cover">
        @if ($cover['label'] !== '')
            <span class="absolute bottom-2 left-2 rounded-full bg-slate-900/55 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-white">{{ $cover['label'] }}</span>
        @endif
        @if ($count > 1)
            <span class="absolute right-2 top-2 rounded-full bg-slate-900/55 px-2 py-0.5 text-[11px] font-medium tabular-nums text-white">{{ $count }} photos</span>
        @endif
    @else
        <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-slate-400">
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.293-9.193 2.056 2.056 0 0 0-1.52-.648H13.5a.375.375 0 0 0-.375.375v1.5m7.5 9.75v-.06m-2.25-12.833a2.056 2.056 0 0 1 1.52.648 17.9 17.9 0 0 1 2.113 2.65m-2.113-2.65A2.056 2.056 0 0 0 15.937 6.5H13.5m5.25 5.25h.087c.525 0 .963-.41.993-.934a17.847 17.847 0 0 0-.944-5.316M3 14.25h5.25v-2.25a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75v2.25Zm15-2.25v2.25h2.242M3 14.25v3.375M21 17.625V14.25M21 17.625a1.125 1.125 0 0 1-1.125 1.125h-.5M3 17.625v.068M3 17.625a1.125 1.125 0 0 0 1.125 1.125h.5" />
            </svg>
            <span class="text-xs font-medium">No photos yet</span>
        </div>
    @endif
</div>

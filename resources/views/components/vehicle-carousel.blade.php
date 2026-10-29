@props(['vehicle', 'ratio' => 'aspect-[4/3]', 'eager' => false, 'controls' => true])

@php
    $photos = $vehicle->gallery();
    $count = count($photos);
    $name = $vehicle->display_name;
@endphp

<div {{ $attributes->merge(['class' => 'group relative w-full overflow-hidden bg-slate-100 '.$ratio]) }}
    @if ($count > 1) data-carousel data-interval="3200" @endif>
    @if ($count === 0)
        <div class="flex h-full w-full flex-col items-center justify-center gap-2 text-slate-400">
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.293-9.193 2.056 2.056 0 0 0-1.52-.648H13.5a.375.375 0 0 0-.375.375v1.5m7.5 9.75v-.06m-2.25-12.833a2.056 2.056 0 0 1 1.52.648 17.9 17.9 0 0 1 2.113 2.65m-2.113-2.65A2.056 2.056 0 0 0 15.937 6.5H13.5m5.25 5.25h.087c.525 0 .963-.41.993-.934a17.847 17.847 0 0 0-.944-5.316M3 14.25h5.25v-2.25a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75v2.25Zm15-2.25v2.25h2.242M3 14.25v3.375M21 17.625V14.25M21 17.625a1.125 1.125 0 0 1-1.125 1.125h-.5M3 17.625v.068M3 17.625a1.125 1.125 0 0 0 1.125 1.125h.5" />
            </svg>
            <span class="text-xs font-medium">No photos yet</span>
        </div>
    @else
        <div class="flex h-full w-full transition-transform duration-700 ease-out will-change-transform" data-carousel-track>
            @foreach ($photos as $index => $photo)
                <div class="relative h-full w-full flex-none" data-slide-label="{{ $photo['label'] }}">
                    <img src="{{ $vehicle->photoUrl($photo['path']) }}"
                         alt="{{ $name }}{{ $photo['label'] !== '' ? ' — '.$photo['label'] : ' — photo '.($index + 1).' of '.$count }}"
                         class="h-full w-full object-cover"
                         loading="{{ ($eager && $index === 0) ? 'eager' : 'lazy' }}">
                    @if ($photo['label'] !== '')
                        <span class="absolute bottom-2 left-2 rounded-full bg-slate-900/55 px-2 py-0.5 text-[11px] font-medium uppercase tracking-wide text-white">{{ $photo['label'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($count > 1)
            <span class="absolute right-2 top-2 rounded-full bg-slate-900/55 px-2 py-0.5 text-[11px] font-medium tabular-nums text-white"
                  data-carousel-count>{{ $count }} photos</span>

            @if ($controls)
                <button type="button" data-carousel-prev aria-label="Previous photo"
                        class="absolute left-2 top-1/2 z-20 -translate-y-1/2 rounded-full bg-white/70 p-1.5 text-slate-700 opacity-0 shadow transition-opacity duration-200 hover:bg-white focus-visible:opacity-100 group-hover:opacity-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                </button>
                <button type="button" data-carousel-next aria-label="Next photo"
                        class="absolute right-2 top-1/2 z-20 -translate-y-1/2 rounded-full bg-white/70 p-1.5 text-slate-700 opacity-0 shadow transition-opacity duration-200 hover:bg-white focus-visible:opacity-100 group-hover:opacity-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                    </svg>
                </button>

                <button type="button" data-carousel-toggle aria-pressed="false" aria-label="Pause slideshow"
                        class="absolute bottom-2 right-2 z-20 rounded-full bg-white/70 p-1.5 text-slate-700 shadow transition-colors duration-200 hover:bg-white focus-visible:opacity-100">
                    <svg class="h-4 w-4" data-icon-pause fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M8 5.5A1.5 1.5 0 0 1 6.5 4h-1A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20h1A1.5 1.5 0 0 0 8 18.5v-13Zm12 0A1.5 1.5 0 0 1 18.5 4h-1A1.5 1.5 0 0 0 16 5.5v13A1.5 1.5 0 0 0 17.5 20h1a1.5 1.5 0 0 0 1.5-1.5v-13Z" />
                    </svg>
                    <svg class="h-4 w-4 hidden" data-icon-play fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6.5 4.5A1.5 1.5 0 0 0 5 6v12a1.5 1.5 0 0 0 2.3 1.28l10-6a1.5 1.5 0 0 0 0-2.56l-10-6A1.5 1.5 0 0 0 6.5 4.5Z" />
                    </svg>
                </button>

                <div class="absolute inset-x-0 bottom-2 flex items-center justify-center gap-1.5" data-carousel-dots></div>
            @endif
        @endif
    @endif
</div>

@props(['rows' => [], 'subject', 'alt' => 'photo'])

{{--
    Read-only grid of named-slot photos (pickup or return evidence).
    $rows comes from HasPhotoSlots::gallery(): slot/label/path triples.
--}}
@if ($rows !== [])
    <div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5']) }}>
        @foreach ($rows as $row)
            <figure class="overflow-hidden rounded-lg border border-slate-200 bg-white">
                <img src="{{ $subject->photoUrl($row['path']) }}"
                     alt="{{ $row['label'] !== '' ? $row['label'].' '.$alt : $alt }}"
                     loading="lazy"
                     class="aspect-[4/3] w-full object-cover">
                @if ($row['label'] !== '')
                    <figcaption class="bg-slate-50 px-2 py-1 text-[10px] font-semibold uppercase tracking-wide text-slate-500">
                        {{ $row['label'] }}
                    </figcaption>
                @endif
            </figure>
        @endforeach
    </div>
@endif

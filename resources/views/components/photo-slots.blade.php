@props(['map' => [], 'subject' => null, 'prefix' => 'photos'])

{{--
    Named photo-slot picker: one tile per angle (Front, Side, ...). Each tile
    shows the current photo (with a Remove tick), a Choose/Replace button and
    a JS preview of the pending upload. Expects a [data-photo-manager]
    ancestor and the slot picker behaviour from resources/js/app.js.
--}}
<div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5 {{ $attributes->get('class', '') }}">
    @foreach (App\Models\Vehicle::SLOTS as $slot => $slotLabel)
        <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2" data-slot="{{ $slot }}">
            <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $slotLabel }}</p>

            @if (isset($map[$slot]))
                <label class="group relative mb-2 block cursor-pointer overflow-hidden rounded-lg border border-slate-200">
                    <img src="{{ $subject->photoUrl($map[$slot]) }}" alt="Current {{ $slotLabel }} photo"
                         class="aspect-[4/3] w-full object-cover transition group-hover:opacity-60">
                    <input type="checkbox" name="remove_images[]" value="{{ $map[$slot] }}" class="peer sr-only" data-slot-remove>
                    <span class="pointer-events-none absolute inset-0 hidden items-center justify-center bg-rose-600/80 text-xs font-semibold text-white peer-checked:flex">
                        Remove
                    </span>
                </label>
            @endif

            <input type="file" name="{{ $prefix }}[{{ $slot }}]" accept="image/jpeg,image/png,image/webp"
                   class="hidden" data-slot-upload>
            <button type="button" data-slot-pick
                    class="w-full rounded-lg border border-dashed border-slate-300 px-2 py-1.5 text-xs font-medium text-slate-600 hover:border-emerald-400 hover:text-emerald-700">
                {{ isset($map[$slot]) ? 'Replace photo' : 'Choose photo' }}
            </button>

            <div class="mt-2 hidden overflow-hidden rounded-lg border border-emerald-300" data-slot-preview>
                <img src="" alt="New {{ $slotLabel }} photo preview" class="aspect-[4/3] w-full object-cover">
                <p class="truncate bg-emerald-600/90 px-1.5 py-0.5 text-[10px] font-medium text-white" data-slot-preview-name></p>
            </div>

            @error("{$prefix}.{$slot}")
                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
            @enderror
        </div>
    @endforeach
</div>

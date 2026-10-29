@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
    $textareaClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <x-field label="Plate Number" name="plate_number" :value="$vehicle->plate_number ?? ''" required />
    <x-field label="Make" name="make" :value="$vehicle->make ?? ''" required />
    <x-field label="Model" name="model" :value="$vehicle->model ?? ''" required />

    <x-field label="Year" name="year" type="number" :value="$vehicle->year ?? ''" required />
    <div class="mb-4">
        <label for="type" class="mb-1 block text-sm font-medium text-slate-700">Type <span class="text-rose-600">*</span></label>
        <select id="type" name="type" class="{{ $selectClass }}" required>
            @foreach ($types as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $vehicle?->type?->value) === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('type')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
    <x-field label="Seats" name="seats" type="number" :value="$vehicle->seats ?? 5" />

    <div class="mb-4">
        <label for="transmission" class="mb-1 block text-sm font-medium text-slate-700">Transmission <span class="text-rose-600">*</span></label>
        <select id="transmission" name="transmission" class="{{ $selectClass }}" required>
            @foreach (['manual' => 'Manual', 'automatic' => 'Automatic'] as $value => $label)
                <option value="{{ $value }}" @selected(old('transmission', $vehicle->transmission ?? 'manual') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('transmission')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
    <div class="mb-4">
        <label for="fuel_type" class="mb-1 block text-sm font-medium text-slate-700">Fuel Type <span class="text-rose-600">*</span></label>
        <select id="fuel_type" name="fuel_type" class="{{ $selectClass }}" required>
            @foreach (['petrol' => 'Petrol', 'diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric'] as $value => $label)
                <option value="{{ $value }}" @selected(old('fuel_type', $vehicle->fuel_type ?? 'petrol') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('fuel_type')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
    <x-field label="Colour" name="colour" :value="$vehicle->colour ?? ''" />

    <x-field label="Mileage (km)" name="mileage" type="number" :value="$vehicle->mileage ?? ''" />
    <x-field label="Rate Per Day (TZS)" name="rate_per_day" type="number" :value="$vehicle->rate_per_day ?? ''" required />
    <div class="mb-4">
        <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status</label>
        <select id="status" name="status" class="{{ $selectClass }}">
            @php
                $assignable = App\Enums\VehicleStatus::manuallyAssignable();
                if ($vehicle && ! in_array($vehicle->status, $assignable, true)) {
                    $assignable[] = $vehicle->status;
                }
            @endphp
            @foreach ($assignable as $option)
                <option value="{{ $option->value }}" @selected(old('status', $vehicle?->status?->value ?? '') === $option->value)>{{ $option->label() }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    @php
        $photoMap = $vehicle?->photoMap() ?? [];
    @endphp
    <div class="mb-4 sm:col-span-2 lg:col-span-3" data-photo-manager>
        <label class="mb-1 block text-sm font-medium text-slate-700">Photos</label>
        <p class="mb-3 text-xs text-slate-500">
            One shot per angle &mdash; the browse carousel captions itself from these slots.
            Uploading into a filled slot replaces its photo. JPG, PNG or WebP, 4 MB each.
        </p>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
            @foreach (\App\Models\Vehicle::SLOTS as $slot => $slotLabel)
                <div class="rounded-lg border border-slate-200 bg-slate-50/60 p-2" data-slot="{{ $slot }}">
                    <p class="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $slotLabel }}</p>

                    @if (isset($photoMap[$slot]))
                        <label class="group relative mb-2 block cursor-pointer overflow-hidden rounded-lg border border-slate-200">
                            <img src="{{ $vehicle->photoUrl($photoMap[$slot]) }}" alt="Current {{ $slotLabel }} photo"
                                 class="aspect-[4/3] w-full object-cover transition group-hover:opacity-60">
                            <input type="checkbox" name="remove_images[]" value="{{ $photoMap[$slot] }}" class="peer sr-only" data-slot-remove>
                            <span class="pointer-events-none absolute inset-0 hidden items-center justify-center bg-rose-600/80 text-xs font-semibold text-white peer-checked:flex">
                                Remove
                            </span>
                        </label>
                    @endif

                    <input type="file" name="photos[{{ $slot }}"] accept="image/jpeg,image/png,image/webp"
                           class="hidden" data-slot-upload>
                    <button type="button" data-slot-pick
                            class="w-full rounded-lg border border-dashed border-slate-300 px-2 py-1.5 text-xs font-medium text-slate-600 hover:border-emerald-400 hover:text-emerald-700">
                        {{ isset($photoMap[$slot]) ? 'Replace photo' : 'Choose photo' }}
                    </button>

                    <div class="mt-2 hidden overflow-hidden rounded-lg border border-emerald-300" data-slot-preview>
                        <img src="" alt="New {{ $slotLabel }} photo preview" class="aspect-[4/3] w-full object-cover">
                        <p class="truncate bg-emerald-600/90 px-1.5 py-0.5 text-[10px] font-medium text-white" data-slot-preview-name></p>
                    </div>

                    @error("photos.$slot")
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
            @endforeach
        </div>
    </div>

    <div class="mb-4 sm:col-span-2">
        <x-field label="Features" name="features" :value="implode(', ', $vehicle->features ?? [])" placeholder="Air conditioning, GPS, Baby seat" />
        <p class="-mt-3 text-xs text-slate-500">Separate multiple features with commas.</p>
    </div>

    <div class="mb-4 sm:col-span-2 lg:col-span-3">
        <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <textarea id="description" name="description" rows="3" class="{{ $textareaClass }}">{{ old('description', $vehicle->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>

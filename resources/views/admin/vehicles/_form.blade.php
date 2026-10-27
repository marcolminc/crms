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

    <x-field label="Image Path" name="image_path" :value="$vehicle->image_path ?? ''" placeholder="storage/vehicles/..." />
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

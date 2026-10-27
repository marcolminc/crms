@php
    $selectClass = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500';
@endphp

<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
    <div class="mb-4 lg:col-span-1">
        <label for="vehicle_id" class="mb-1 block text-sm font-medium text-slate-700">Vehicle <span class="text-rose-600">*</span></label>
        <select id="vehicle_id" name="vehicle_id" class="{{ $selectClass }}" required>
            <option value="">Select a vehicle</option>
            @foreach ($vehicles as $option)
                <option value="{{ $option->id }}" @selected((string) old('vehicle_id', $selectedVehicle ?? '') === (string) $option->id)>
                    {{ $option->plate_number }} — {{ $option->make }} {{ $option->model }}
                </option>
            @endforeach
        </select>
        @error('vehicle_id')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
    <x-field label="Work Title" name="title" :value="$maintenance->title ?? ''" required />
    <x-field label="Service Provider" name="service_provider" :value="$maintenance->service_provider ?? ''" />

    <x-field label="Start Date" name="start_date" type="date" :value="$maintenance?->start_date?->format('Y-m-d') ?? ''" required />
    <x-field label="End Date" name="end_date" type="date" :value="$maintenance?->end_date?->format('Y-m-d') ?? ''" />
    <x-field label="Cost (TZS)" name="cost" type="number" :value="$maintenance->cost ?? ''" />

    <div class="mb-4">
        <label for="status" class="mb-1 block text-sm font-medium text-slate-700">Status <span class="text-rose-600">*</span></label>
        <select id="status" name="status" class="{{ $selectClass }}" required>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected(old('status', $maintenance?->status?->value ?? 'scheduled') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('status')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-4 sm:col-span-2 lg:col-span-3">
        <label for="description" class="mb-1 block text-sm font-medium text-slate-700">Description</label>
        <textarea id="description" name="description" rows="3"
                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('description', $maintenance->description ?? '') }}</textarea>
        @error('description')
            <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
        @enderror
    </div>
</div>

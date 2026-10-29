@forelse ($vehicles as $vehicle)
    @php
        $window = array_filter(['from' => $filters['from'], 'to' => $filters['to']]);
        $detailUrl = route('vehicles.show', $vehicle).($window ? '?'.http_build_query($window) : '');
    @endphp
    <article class="relative overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md"
             data-reveal style="--reveal-delay: {{ ($loop->index % 3) * 90 }}ms">
        <div class="relative">
            <x-vehicle-thumbnail :vehicle="$vehicle" ratio="aspect-[16/10]" />
            <div class="pointer-events-none absolute left-3 top-3">
                <x-badge :value="$vehicle->status->value" />
            </div>
        </div>
        <div class="p-5">
            <h3 class="text-base font-semibold text-slate-900">
                <a href="{{ $detailUrl }}"
                   class="after:absolute after:inset-0 after:content-[''] hover:text-emerald-700">
                    {{ $vehicle->make }} {{ $vehicle->model }}
                </a>
            </h3>
            <p class="mt-1 text-sm text-slate-500">Plate {{ $vehicle->plate_number }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-sm text-slate-600">
                <div>{{ $vehicle->type->label() }}</div>
                <div>{{ $vehicle->seats }} seats</div>
                <div>{{ ucfirst($vehicle->transmission) }}</div>
                <div>{{ ucfirst($vehicle->fuel_type) }}</div>
            </dl>
            <div class="mt-4 flex items-end justify-between">
                <p class="text-lg font-semibold text-slate-900">
                    {{ number_format($vehicle->rate_per_day, 0) }} TZS
                    <span class="text-xs font-normal text-slate-500">/ day</span>
                </p>
                <span class="text-sm font-medium text-emerald-700">View details &rarr;</span>
            </div>
        </div>
    </article>
@empty
    <div class="sm:col-span-2 lg:col-span-3">
        <x-alert type="info">
            No vehicles match your filters for the selected dates. Try widening the dates or removing some filters.
        </x-alert>
    </div>
@endforelse

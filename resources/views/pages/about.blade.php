@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'About us')

@section('content')
    <x-page-header title="About Land Valley&rsquo;s Travels"
                   description="A Mwanza-based car rental company keeping the lake region moving since 2011." />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card title="Who we are">
                <div class="space-y-3 text-sm leading-6 text-slate-600">
                    <p>
                        Land Valley&rsquo;s Travels began with three vehicles ferrying business travellers around Mwanza and has
                        grown into one of the lake region&rsquo;s most dependable rental fleets. We serve corporate accounts,
                        NGOs, government delegations and holidaymakers who need a clean, well-maintained vehicle at short notice.
                    </p>
                    <p>
                        Every car in our fleet is serviced on schedule, inspected before each hire and tracked so that help is
                        never far away. Our drivers &mdash; where you choose to add one &mdash; are licensed, local and known to
                        the roads around Mwanza, Shinyanga and beyond.
                    </p>
                    <p>
                        Booking is simple: browse the fleet, request the dates you need, and our desk team confirms availability
                        and paperwork before pick-up. Online payment through AzamPay is available for a contact-free deposit.
                    </p>
                </div>
            </x-card>

            <x-card title="What we offer">
                <ul class="grid gap-3 sm:grid-cols-2">
                    @foreach ([
                        ['Self-drive hire', 'Pick a vehicle and go, with comprehensive cover and 24/7 roadside support.'],
                        ['Chauffeur services', 'Add a professional office driver for business travel and airport transfers.'],
                        ['Corporate accounts', 'Negotiated rates and monthly invoicing for organisations that hire often.'],
                        ['Long-term rental', 'Maintenance-inclusive leases for projects that need a vehicle for months.'],
                    ] as [$title, $body])
                        <li class="rounded-lg border border-slate-200 bg-slate-50/60 p-4">
                            <p class="font-semibold text-slate-800">{{ $title }}</p>
                            <p class="mt-1 text-sm text-slate-600">{{ $body }}</p>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card title="By the numbers">
                <dl class="space-y-4 text-sm">
                    <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Years on the road</dt><dd class="text-2xl font-semibold text-emerald-700">15+</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Vehicles in fleet</dt><dd class="text-2xl font-semibold text-emerald-700">60+</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Hires completed</dt><dd class="text-2xl font-semibold text-emerald-700">12k</dd></div>
                    <div class="flex items-baseline justify-between gap-4"><dt class="text-slate-500">Customer rating</dt><dd class="text-2xl font-semibold text-emerald-700">4.8/5</dd></div>
                </dl>
            </x-card>

            <x-card title="Ready to move?">
                <p class="text-sm text-slate-600">Browse the current fleet and reserve the vehicle that fits your trip.</p>
                <div class="mt-4 flex flex-col gap-2">
                    <a href="{{ route('vehicles.index') }}"
                       class="rounded-lg bg-emerald-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-600">Browse vehicles</a>
                    <a href="{{ route('pages.contact') }}"
                       class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">Talk to our team</a>
                </div>
            </x-card>
        </div>
    </div>
@endsection

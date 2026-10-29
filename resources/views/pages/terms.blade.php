@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Terms and conditions')

@php
    $sections = [
        'eligibility' => ['Eligibility to hire', 'Hirers must be at least 18 years old and hold a valid driving licence appropriate to the vehicle for the whole hire period. A national ID or passport is required at pick-up.'],
        'booking' => ['Reservations and confirmation', 'A reservation is a request, not a guarantee. It becomes firm only once our team confirms availability. Confirmed reservations may be converted into a rental agreement at pick-up.'],
        'rates' => ['Rates, charges and payment', 'Daily rates cover the hire period stated on the agreement unless otherwise agreed. Fuel, additional charges, traffic fines and damage are billed in addition. Deposits and balances can be settled in cash, by mobile money or through AzamPay online.'],
        'use' => ['Use of the vehicle', 'The vehicle must be driven lawfully, kept within Tanzania unless written consent is given, and may not be sub-hired, used for racing, towing, or carrying hazardous goods. The hirer is responsible for any passenger or third-party injury caused by negligent use.'],
        'fuel-mileage' => ['Fuel and mileage', 'Unless a different arrangement is agreed, the vehicle is handed over and expected back with the same fuel level; refuelling is charged at the prevailing rate. Advertised daily mileage limits apply to long hires.'],
        'damage-fines' => ['Damage, theft and traffic fines', 'The vehicle is insured against the standard perils, but the hirer remains liable for the excess and for damage caused by negligence, off-road use, or driving under the influence. Traffic and parking fines incurred during the hire are passed on to the hirer, together with any administration fee.'],
        'returns' => ['Returns and late hire', 'Vehicles are returned at the agreed time and location. Returning late incurs an additional daily charge. A return is inspected on receipt and any new damage is recorded and billed.'],
        'cancellation' => ['Cancellation', 'Cancellations made before a vehicle is released are refunded less any non-refundable deposit already taken. No-shows may forfeit the deposit.'],
        'liability' => ['Liability', 'To the extent permitted by law, Land Valley&rsquo;s Travels is not liable for indirect or consequential loss arising from a hire, including missed connections or lost business. Our liability for a claim is limited to the value of the hire agreement.'],
        'privacy' => ['Your information', 'We keep the details you provide solely to administer your hire, contact you about bookings, and meet legal and insurance obligations. We do not sell your personal data.'],
    ];
@endphp

@section('content')
    <x-page-header title="Terms and conditions"
                   description="The agreement that applies whenever you hire or reserve a vehicle from us." />

    <div class="grid gap-6 lg:grid-cols-4">
        <nav class="lg:col-span-1">
            <x-card title="On this page">
                <ul class="space-y-1 text-sm">
                    @foreach ($sections as $id => [$label])
                        <li><a href="#{{ $id }}" class="text-slate-600 hover:text-emerald-700">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </x-card>
        </nav>

        <div class="space-y-5 lg:col-span-3">
            @foreach ($sections as $id => [$label, $body])
                <x-card>
                    <h3 id="{{ $id }}" class="scroll-mt-24 text-base font-semibold text-slate-900">{{ $label }}</h3>
                    <p class="mt-2 text-sm leading-6 text-slate-600">{{ $body }}</p>
                </x-card>
            @endforeach

            <p class="text-xs text-slate-500">
                These terms are provided for guidance and form part of your rental agreement. Questions?
                <a href="{{ route('pages.contact') }}" class="font-medium text-emerald-700 hover:underline">Contact our team</a>.
            </p>
        </div>
    </div>
@endsection

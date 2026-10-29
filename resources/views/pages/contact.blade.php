@extends(auth()->check() ? 'layouts.app' : 'layouts.guest')

@section('title', 'Contact us')

@section('content')
    <x-page-header title="Contact us"
                   description="Questions about a hire, a corporate account or a return? Reach the desk team." />

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="lg:col-span-3">
            <x-card title="Send us a message">
                <form method="POST" action="{{ route('pages.contact.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-field label="Your name" name="name" required
                                 pattern="[A-Za-zÀ-ÿ][A-Za-zÀ-ÿ'. -]*" minlength="2" maxlength="120" placeholder="e.g. Ali Hamisi" />
                        <x-field label="Email address" name="email" type="email" required maxlength="255" placeholder="you@example.com" />
                        <x-field label="Phone number" name="phone" type="tel"
                                 pattern="0[0-9]{9}" inputmode="numeric" maxlength="10" placeholder="e.g. 0123456789" />
                        <x-field label="Subject" name="subject" maxlength="150" placeholder="e.g. Corporate hire enquiry" />
                    </div>

                    <div>
                        <label for="message" class="mb-1 block text-sm font-medium text-slate-700">
                            Message <span class="text-rose-600">*</span>
                        </label>
                        <textarea id="message" name="message" rows="5" required minlength="10" maxlength="5000"
                                  placeholder="How can we help? Tell us the dates and the vehicle you have in mind."
                                  class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-emerald-500 focus:ring-emerald-500">{{ old('message') }}</textarea>
                        @error('message')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>

                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        Send message
                    </button>
                </form>
            </x-card>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <x-card title="Visit or call">
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="font-medium text-slate-800">Office</dt>
                        <dd class="text-slate-600">Plot 12, Shekilango Road, Mwanza, Tanzania</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-800">Phone</dt>
                        <dd><a href="tel:+255262500000" class="text-emerald-700 hover:underline">+255 26 250 0000</a></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-800">Email</dt>
                        <dd><a href="mailto:info@landvalleystravels.co.tz" class="text-emerald-700 hover:underline">info@landvalleystravels.co.tz</a></dd>
                    </div>
                    <div>
                        <dt class="font-medium text-slate-800">Opening hours</dt>
                        <dd class="text-slate-600">Mon&ndash;Sat, 08:00&ndash;18:00 &middot; Sun closed</dd>
                    </div>
                </dl>
                <a href="https://www.google.com/maps/search/?api=1&query=Shekilango+Road+Mwanza+Tanzania"
                   target="_blank" rel="noopener"
                   class="mt-4 block rounded-lg border border-slate-300 bg-white px-4 py-2 text-center text-sm font-medium text-slate-700 hover:bg-slate-50">
                    Get directions
                </a>
            </x-card>

            <x-card title="Already hired with us?">
                <p class="text-sm text-slate-600">Sign in to view your reservations, invoices and to make an online payment.</p>
                <a href="{{ route('login') }}"
                   class="mt-3 block rounded-lg bg-emerald-700 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-600">Log in</a>
            </x-card>
        </div>
    </div>
@endsection

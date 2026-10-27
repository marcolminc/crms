@extends('layouts.app')

@section('title', 'Approve your payment')

@section('content')
    <x-page-header
        title="Approve the payment prompt"
        description="AzamPay is dialling your phone. Enter your mobile money PIN on the prompt to complete the payment.">
    </x-page-header>

    <x-card>
        <div class="mx-auto max-w-md py-6 text-center">
            <p class="font-medium text-slate-900">
                {{ number_format((float) $intent->amount, 0) }} TZS &middot; {{ $intent->payment_method->label() }}
            </p>
            <p class="mt-1 text-sm text-slate-500">Rental #{{ $rental?->id }}</p>

            <div id="waiting" class="mt-6">
                <div class="mx-auto h-8 w-8 animate-spin rounded-full border-2 border-emerald-600 border-t-transparent"></div>
                <p class="mt-4 text-sm text-slate-600">Waiting for AzamPay to confirm your payment&hellip;</p>
            </div>

            <div id="finished" class="mt-6 hidden">
                <p class="text-lg font-semibold text-emerald-700">Payment confirmed. Thank you!</p>
            </div>

            <div id="failed" class="mt-6 hidden">
                <p class="text-sm font-medium text-rose-700">
                    This payment did not go through. You can try again from your rentals page.
                </p>
            </div>

            <div class="mt-8 flex flex-col items-center gap-3">
                <form method="POST" action="{{ route('customer.payments.confirm', $intent) }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg bg-emerald-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700">
                        I have approved the prompt
                    </button>
                </form>
                <a href="{{ route('customer.rentals.index') }}" class="text-sm text-slate-500 hover:text-slate-800">
                    Back to my rentals
                </a>
            </div>
        </div>
    </x-card>

    <script>
        (function () {
            const statusUrl = @json(route('customer.payments.status', $intent));
            const rentalsUrl = @json(route('customer.rentals.index'));

            function show(id) {
                document.getElementById('waiting').classList.add('hidden');
                document.getElementById(id).classList.remove('hidden');
            }

            function poll() {
                fetch(statusUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                    .then((response) => response.ok ? response.json() : null)
                    .then((data) => {
                        if (data && data.finished) {
                            show('finished');
                            setTimeout(() => window.location.href = rentalsUrl, 2000);
                            return;
                        }
                        if (data && data.failed) {
                            show('failed');
                            return;
                        }
                        setTimeout(poll, 4000);
                    })
                    .catch(() => setTimeout(poll, 8000));
            }

            setTimeout(poll, 4000);
        })();
    </script>
@endsection

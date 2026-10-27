<?php

namespace Tests\Feature;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RentalStatus;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\PaymentGatewayEvent;
use App\Models\PaymentIntent;
use App\Models\Rental;
use App\Models\User;
use App\Services\PaymentDraft;
use App\Services\PaymentRecordingService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class OnlinePaymentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://azampay.test';

    private const AUTH = 'https://authenticator.azampay.test/AppRegistration/GenerateToken';

    private const PUSH = self::BASE.'/api/v1/Checkout/GetMobileNumber';

    private const HOSTED = self::BASE.'/api/v1/Checkout/PostCheckout';

    private const VERIFY = self::BASE.'/api/v1/TransactionStatus/GetTransactionStatus';

    private const LINK = 'https://checkout.azampay.test/pay/mock';

    private const SECRET = 'shared-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function configureGateway(bool $enabled = true): void
    {
        config([
            'services.azampay.app_name' => $enabled ? 'CRMS Test' : null,
            'services.azampay.client_id' => $enabled ? 'client-1' : null,
            'services.azampay.client_secret' => $enabled ? 'secret-1' : null,
            'services.azampay.webhook_secret' => $enabled ? self::SECRET : null,
            'services.azampay.base_url' => self::BASE,
            'services.azampay.auth_url' => self::AUTH,
            'services.azampay.currency' => 'TZS',
            'services.azampay.vendor_id' => $enabled ? 'vendor-1' : null,
            'services.azampay.vendor_name' => 'CRMS Rentals',
        ]);

        // Each test starts without a remembered bearer token so the request
        // counts below describe exactly what the flow did.
        Cache::clear();
    }

    private function rentalOwing(float $amount): Rental
    {
        $rental = Rental::factory()->active()->create([
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['total_amount' => $amount])->save();

        // A local Tanzanian MSISDN; the service hands AzamPay the +255 form.
        $rental->customer->user->update(['phone' => '0722345678']);

        return $rental;
    }

    private function counterRecords(Rental $rental, float $amount): Payment
    {
        return app(PaymentRecordingService::class)->record($rental, new PaymentDraft(
            amount: $amount,
            method: PaymentMethod::Cash,
            paidAt: Carbon::parse('2026-11-01'),
        ));
    }

    private function fakePush(): void
    {
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            self::PUSH => ['success' => true, 'transactionId' => 'AZAM-TX-9001'],
        ]);
    }

    private function fakeHosted(): void
    {
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            self::HOSTED => ['success' => true, 'data' => self::LINK],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function fakeVerify(PaymentIntent $intent, array $overrides = []): void
    {
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            (self::VERIFY.'*') => ['success' => true, 'data' => array_merge([
                'externalId' => $intent->tx_ref,
                'reference' => '9001',
                'mnoreference' => 'AZAM-MOCK-9001',
                'transactionStatus' => 'success',
                'amount' => (float) $intent->amount,
                'currency' => $intent->currency,
            ], $overrides)],
        ]);
    }

    private function fakeVerifyDown(): void
    {
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            (self::VERIFY.'*') => ['message' => 'Rate limited', 500],
        ]);
    }

    private function openPush(Rental $rental, string $method = 'mpesa'): PaymentIntent
    {
        $this->configureGateway();
        $this->fakePush();

        $response = $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => $method]);

        $intent = PaymentIntent::query()->where('rental_id', $rental->id)->latest('id')->firstOrFail();
        $response->assertRedirect(route('customer.payments.waiting', $intent));

        return $intent;
    }

    private function openCard(Rental $rental): PaymentIntent
    {
        $this->configureGateway();
        $this->fakeHosted();

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'credit_card'])
            ->assertRedirect(self::LINK);

        return PaymentIntent::query()->where('rental_id', $rental->id)->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, string|null>  $headers
     * @param  array<string, mixed>  $payload
     */
    private function webhook(PaymentIntent $intent, array $headers = [], array $payload = []): TestResponse
    {
        return $this->withHeaders(array_merge(['x-webhook-secret' => self::SECRET], $headers))
            ->postJson(route('webhooks.azampay'), array_merge([
                'utilityref' => $intent->tx_ref,
                'reference' => '9001',
                'transactionStatus' => 'success',
                'mnoreference' => 'AZAM-MOCK-9001',
                'amount' => (float) $intent->amount,
                'currency' => $intent->currency,
            ], $payload));
    }

    /**
     * @param  array<string, string>  $query
     */
    private function returnUrl(PaymentIntent $intent, string $status, array $query = []): string
    {
        return route('customer.payments.return', $intent).'?'.http_build_query(array_merge([
            'status' => $status,
            'tx_ref' => $intent->tx_ref,
            'reference' => '9001',
        ], $query));
    }

    public function test_the_pay_button_is_hidden_while_the_gateway_is_unconfigured(): void
    {
        $this->configureGateway(false);
        $rental = $this->rentalOwing(200000);

        $this->actingAs($rental->customer->user)
            ->get(route('customer.rentals.index'))
            ->assertOk()
            ->assertDontSee('Pay now');

        Http::assertNothingSent();
    }

    public function test_the_pay_button_offers_the_online_methods_once_the_gateway_is_configured(): void
    {
        $this->configureGateway();
        $rental = $this->rentalOwing(200000);

        $this->actingAs($rental->customer->user)
            ->get(route('customer.rentals.index'))
            ->assertOk()
            ->assertSee('Pay now')
            ->assertSee('M-Pesa')
            ->assertSee('Credit / Debit Card')
            ->assertDontSee('Bank Transfer');
    }

    public function test_the_button_disappears_once_the_rental_is_settled(): void
    {
        $this->configureGateway();
        $rental = $this->rentalOwing(200000);
        $this->counterRecords($rental, 200000);

        $this->actingAs($rental->customer->user)
            ->get(route('customer.rentals.index'))
            ->assertOk()
            ->assertDontSee('Pay now');
    }

    public function test_clicking_pay_pushes_a_prompt_to_the_customers_phone_for_mobile_money(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);

        $this->assertSame(PaymentIntentStatus::Started, $intent->status);
        $this->assertSame($rental->id, $intent->rental_id);
        $this->assertSame(PaymentMethod::Mpesa, $intent->payment_method);
        $this->assertEqualsWithDelta(200000.0, (float) $intent->amount, 0.01);
        $this->assertSame('TZS', $intent->currency);
        $this->assertStringStartsWith('CRMS'.$rental->id.'-', $intent->tx_ref);
        $this->assertLessThanOrEqual(30, strlen($intent->tx_ref));
        $this->assertSame('AZAM-TX-9001', $intent->gateway_tx_id);
        $this->assertNull($intent->checkout_url);
        $this->assertNull($intent->payment_id);
        $this->assertDatabaseCount('payments', 0);

        Http::assertSent(fn ($request): bool => $request->url() === self::PUSH
            && $request->data()['accountNumber'] === '255722345678'
            && $request->data()['amount'] === '200000.00'
            && $request->data()['provider'] === 'M-Pesa'
            && $request->data()['externalId'] === $intent->tx_ref
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_clicking_pay_opens_the_hosted_page_for_a_card(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);

        $this->assertSame(PaymentIntentStatus::Started, $intent->status);
        $this->assertSame(self::LINK, $intent->checkout_url);
        $this->assertNull($intent->gateway_tx_id);
        $this->assertSame(PaymentMethod::CreditCard, $intent->payment_method);

        Http::assertSent(fn ($request): bool => $request->url() === self::HOSTED
            && $request->data()['vendorId'] === 'vendor-1'
            && $request->data()['externalId'] === $intent->tx_ref
            && $request->data()['redirectSuccessURL'] === route('customer.payments.return', $intent));
    }

    public function test_the_checkout_amount_follows_the_balance_rather_than_the_customer(): void
    {
        $this->configureGateway();
        $this->fakePush();
        $rental = $this->rentalOwing(200000);
        $this->counterRecords($rental, 120000);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), [
                'payment_method' => 'tigo_pesa',
                'amount' => 1,
            ]);

        $intent = PaymentIntent::query()->sole();
        $this->assertEqualsWithDelta(80000.0, (float) $intent->amount, 0.01);
        $this->assertSame(PaymentMethod::TigoPesa, $intent->payment_method);

        Http::assertSent(fn ($request): bool => $request->url() === self::PUSH
            && $request->data()['amount'] === '80000.00'
            && $request->data()['provider'] === 'TigoPesa');
    }

    public function test_a_cancelled_rental_is_closed_to_online_payment(): void
    {
        $this->configureGateway();
        $this->fakePush();
        $rental = $this->rentalOwing(200000);
        $rental->update(['status' => RentalStatus::Cancelled]);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('error', 'Online payment is unavailable right now. Please pay at our office instead.');

        $this->assertDatabaseCount('payment_intents', 0);
        Http::assertNothingSent();
    }

    public function test_a_settled_rental_is_closed_to_online_payment(): void
    {
        $this->configureGateway();
        $this->fakePush();
        $rental = $this->rentalOwing(200000);
        $this->counterRecords($rental, 200000);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payment_intents', 0);
        Http::assertNothingSent();
    }

    public function test_a_second_click_reuses_the_prompt_that_is_already_live(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertRedirect(route('customer.payments.waiting', $intent));

        $this->assertDatabaseCount('payment_intents', 1);
        // Token + push, once. The live prompt must not raise a second USSD dial.
        Http::assertSentCount(2);
    }

    public function test_a_changed_balance_closes_the_stale_prompt_and_opens_a_new_one(): void
    {
        $rental = $this->rentalOwing(200000);
        $stale = $this->openPush($rental);

        $this->counterRecords($rental->fresh(), 50000);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa']);

        $this->assertDatabaseHas('payment_intents', [
            'id' => $stale->id,
            'status' => PaymentIntentStatus::Failed->value,
            'failure_reason' => GatewayCaptureOutcome::Declined->value,
        ]);
        $this->assertDatabaseHas('payment_intents', [
            'rental_id' => $rental->id,
            'status' => PaymentIntentStatus::Started->value,
            'amount' => 150000.00,
        ]);
        // Token, stale push, fresh push: the bearer token is remembered.
        Http::assertSentCount(3);
    }

    public function test_a_gateway_that_refuses_the_push_fails_gracefully(): void
    {
        $this->configureGateway();
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            self::PUSH => ['success' => false, 'message' => 'Invalid Data'],
        ]);
        $rental = $this->rentalOwing(200000);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('error', 'Online payment is unavailable right now. Please pay at our office instead.');

        $this->assertDatabaseHas('payment_intents', [
            'status' => PaymentIntentStatus::Failed->value,
            'failure_reason' => GatewayCaptureOutcome::GatewayUnavailable->value,
        ]);
    }

    public function test_keys_that_disappear_mid_flight_stop_the_checkout_from_opening(): void
    {
        $rental = $this->rentalOwing(200000);
        $this->openPush($rental);

        $this->configureGateway(false);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payment_intents', 1);
        Http::assertSentCount(2);
    }

    public function test_a_guest_is_asked_to_sign_in_before_paying(): void
    {
        $this->configureGateway();
        $rental = $this->rentalOwing(200000);

        $this->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertRedirect(route('login'));

        Http::assertNothingSent();
    }

    public function test_staff_cannot_run_the_customer_checkout(): void
    {
        $this->configureGateway();
        $rental = $this->rentalOwing(200000);
        $staff = User::factory()->counterAgent()->create();

        $this->actingAs($staff)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_a_customer_cannot_open_a_checkout_for_someone_elses_rental(): void
    {
        $this->configureGateway();
        $this->fakePush();
        $rental = $this->rentalOwing(200000);
        $intruder = Customer::factory()->create()->user;

        $this->actingAs($intruder)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertNotFound();

        $this->assertDatabaseCount('payment_intents', 0);
        Http::assertNothingSent();
    }

    public function test_money_that_cannot_be_paid_online_is_rejected_before_the_gateway(): void
    {
        $this->configureGateway();
        $rental = $this->rentalOwing(200000);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'cash'])
            ->assertSessionHasErrors([
                'payment_method' => 'That method cannot be paid online. Choose a card or mobile money option.',
            ]);

        $this->assertDatabaseCount('payment_intents', 0);
        Http::assertNothingSent();
    }

    public function test_a_webhook_confirmation_becomes_exactly_one_ledger_row(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent);

        $response = $this->webhook($intent);

        $response->assertOk()->assertJson(['status' => GatewayCaptureOutcome::Recorded->value]);

        $payment = Payment::query()->sole();
        $this->assertSame($rental->id, $payment->rental_id);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertSame(PaymentMethod::Mpesa, $payment->payment_method);
        $this->assertNotNull($payment->paid_at);
        $this->assertNull($payment->recorded_by);
        $this->assertMatchesRegularExpression('/^RC-\d{8}-\d{4}$/', $payment->receipt_number);
        $this->assertSame('AZAM-MOCK-9001', $payment->transaction_reference);
        $this->assertStringContainsString('Paid online with AzamPay', (string) $payment->notes);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
        $this->assertTrue($rental->fresh()->isSettled());

        $intent->refresh();
        $this->assertSame(PaymentIntentStatus::Recorded, $intent->status);
        $this->assertSame('AZAM-MOCK-9001', $intent->gateway_ref);
        $this->assertSame('9001', (string) $intent->gateway_tx_id);
        $this->assertSame($payment->id, $intent->payment_id);
        $this->assertNotNull($intent->verified_at);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $rental->customer->user_id,
            'type' => 'payment_recorded',
            'link' => route('customer.rentals.index'),
        ]);

        // The callback is journalled verbatim and marked handled (design 5.3).
        $event = PaymentGatewayEvent::query()->sole();
        $this->assertSame($intent->id, $event->payment_intent_id);
        $this->assertSame('success', $event->event_type);
        $this->assertTrue($event->processed);
        $this->assertSame('AZAM-MOCK-9001', $event->raw_payload['mnoreference']);
    }

    public function test_a_redelivered_webhook_records_the_payment_once(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent);

        $this->webhook($intent)->assertOk()->assertJson(['status' => GatewayCaptureOutcome::Recorded->value]);
        $this->webhook($intent)->assertOk()->assertJson(['status' => GatewayCaptureOutcome::AlreadyRecorded->value]);

        $this->assertDatabaseCount('payments', 1);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_a_webhook_and_a_browser_return_race_to_record_one_payment(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);
        $this->fakeVerify($intent);

        $this->webhook($intent)->assertOk();

        $this->actingAs($rental->customer->user)
            ->get($this->returnUrl($intent, 'success'))
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('status', 'Thank you, your online payment has been recorded against the rental.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseCount('payment_intents', 1);
    }

    public function test_a_forged_webhook_is_turned_away(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);

        $this->webhook($intent, ['x-webhook-secret' => 'guessed'])->assertForbidden();

        $this->postJson(route('webhooks.azampay'), ['utilityref' => $intent->tx_ref])
            ->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_gateway_events', 0);
        $this->assertDatabaseHas('payment_intents', ['id' => $intent->id, 'status' => PaymentIntentStatus::Started->value]);
        // Only the two requests that opened the prompt; the forgeries sent nothing.
        Http::assertSentCount(2);
    }

    public function test_a_callback_without_the_secret_relies_on_url_secrecy(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent);

        // AzamPay does not sign callbacks; when no extra secret is configured the
        // registered URL is the credential and plain posts are still processed.
        config(['services.azampay.webhook_secret' => null]);

        $this->postJson(route('webhooks.azampay'), [
            'utilityref' => $intent->tx_ref,
            'reference' => '9001',
            'transactionStatus' => 'success',
            'mnoreference' => 'AZAM-MOCK-9001',
        ])->assertOk()->assertJson(['status' => GatewayCaptureOutcome::Recorded->value]);

        $this->assertDatabaseCount('payments', 1);
    }

    public function test_a_reference_we_never_issued_is_ignored(): void
    {
        $this->configureGateway();

        $this->withHeaders(['x-webhook-secret' => self::SECRET])
            ->postJson(route('webhooks.azampay'), ['utilityref' => 'CRMS99-NOT-OURS', 'reference' => '5555'])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('payment_intents', 0);
    }

    public function test_a_payload_without_a_reference_is_ignored(): void
    {
        $this->configureGateway();

        $this->webhookFallback(['transactionStatus' => 'success'])
            ->assertOk()
            ->assertJson(['status' => 'ignored']);

        Http::assertNothingSent();
    }

    public function test_a_terminal_failure_callback_closes_the_attempt_without_verification(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);

        $this->webhook($intent, payload: ['transactionStatus' => 'cancelled'])
            ->assertOk()
            ->assertJson(['status' => GatewayCaptureOutcome::Declined->value]);

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Failed->value,
            'failure_reason' => GatewayCaptureOutcome::Declined->value,
        ]);
        $this->assertDatabaseCount('payments', 0);

        // Nothing was asked of the gateway beyond opening the push.
        Http::assertSentCount(2);
    }

    public function test_a_status_that_is_not_a_decision_is_settled_by_verification(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent, ['transactionStatus' => 'failed']);

        // 'request_sent' means the prompt is still out; the verify call decides.
        $this->webhook($intent, payload: ['transactionStatus' => 'request_sent'])
            ->assertOk()
            ->assertJson(['status' => GatewayCaptureOutcome::Declined->value]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertEqualsWithDelta(200000.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_money_that_does_not_match_the_checkout_amount_is_refused(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent, ['amount' => 199000]);

        $this->webhook($intent)
            ->assertOk()
            ->assertJson(['status' => GatewayCaptureOutcome::AmountMismatch->value]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('payment_intents', ['id' => $intent->id, 'failure_reason' => GatewayCaptureOutcome::AmountMismatch->value]);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_attention']);
    }

    public function test_money_in_another_currency_is_refused(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent, ['currency' => 'USD']);

        $this->webhook($intent)
            ->assertOk()
            ->assertJson(['status' => GatewayCaptureOutcome::CurrencyMismatch->value]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_attention']);
    }

    public function test_a_confirmation_borrowed_from_another_checkout_is_refused(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent, ['externalId' => 'CRMS44-SOMEONE-ELSE']);

        $this->webhook($intent)
            ->assertOk()
            ->assertJson(['status' => GatewayCaptureOutcome::ReferenceMismatch->value]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertEqualsWithDelta(200000.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_a_confirmation_for_a_cancelled_rental_is_refused(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $rental->update(['status' => RentalStatus::Cancelled]);
        $this->fakeVerify($intent);

        $this->webhook($intent)
            ->assertOk()
            ->assertJson(['status' => GatewayCaptureOutcome::RentalClosed->value]);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_attention']);
    }

    public function test_money_that_arrived_after_the_office_cleared_the_balance_is_refused(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->counterRecords($rental->fresh(), 200000);
        $this->fakeVerify($intent);

        $this->webhook($intent)
            ->assertOk()
            ->assertJson(['status' => GatewayCaptureOutcome::OverBalance->value]);

        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payment_intents', ['id' => $intent->id, 'failure_reason' => GatewayCaptureOutcome::OverBalance->value]);
        $this->assertDatabaseHas('notifications', ['type' => 'payment_attention']);
    }

    public function test_a_card_charge_keeps_its_reference_out_of_the_mobile_money_column(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);
        $this->fakeVerify($intent);

        $this->webhook($intent)->assertOk();

        $payment = Payment::query()->sole();
        $this->assertSame(PaymentMethod::CreditCard, $payment->payment_method);
        $this->assertNull($payment->transaction_reference);
        $this->assertStringContainsString('AZAM-MOCK-9001', (string) $payment->notes);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_a_transaction_the_gateway_cannot_read_is_asked_to_redeliver(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerifyDown();

        $this->webhook($intent)
            ->assertStatus(503)
            ->assertJson(['status' => 'retry']);

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('payment_intents', ['id' => $intent->id, 'status' => PaymentIntentStatus::Started->value]);

        // The raw callback stays journalled and unprocessed for the reconciler.
        $event = PaymentGatewayEvent::query()->sole();
        $this->assertFalse($event->processed);
    }

    public function test_the_webhook_is_exempt_from_the_browser_only_csrf_check(): void
    {
        $this->configureGateway();

        $excluded = $this->app->make(PreventRequestForgery::class)->getExcludedPaths();

        $this->assertContains('webhooks/azampay', $excluded);
        $this->assertNotContains('admin/payments', $excluded);
    }

    public function test_the_return_callback_records_what_the_gateway_confirms(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);
        $this->fakeVerify($intent);

        $this->actingAs($rental->customer->user)
            ->get($this->returnUrl($intent, 'success'))
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('status', 'Thank you, your online payment has been recorded against the rental.');

        $payment = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
        $this->assertDatabaseHas('payment_intents', ['id' => $intent->id, 'payment_id' => $payment->id]);
    }

    public function test_a_return_the_gateway_cannot_confirm_records_nothing(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);
        $this->fakeVerify($intent, ['transactionStatus' => 'failed']);

        $this->actingAs($rental->customer->user)
            ->get($this->returnUrl($intent, 'success'))
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('payments', 0);
        $this->assertEqualsWithDelta(200000.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_a_closed_checkout_is_abandoned_without_a_gateway_call(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);

        $this->actingAs($rental->customer->user)
            ->get($this->returnUrl($intent, 'cancelled', ['reference' => '']))
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('error', 'That checkout was closed before the payment went through.');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Failed->value,
            'failure_reason' => GatewayCaptureOutcome::Declined->value,
        ]);
        Http::assertSentCount(2);
    }

    public function test_a_return_without_a_reference_defers_to_the_callback(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);
        $this->fakeVerifyDown();

        $this->actingAs($rental->customer->user)
            ->get($this->returnUrl($intent, 'success', ['reference' => '']))
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('status', 'Your payment is being confirmed. We will update your balance as soon as AzamPay reports it.');

        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseHas('payment_intents', ['id' => $intent->id, 'status' => PaymentIntentStatus::Started->value]);
    }

    public function test_a_return_that_echoes_another_reference_is_rejected(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);

        $this->actingAs($rental->customer->user)
            ->get($this->returnUrl($intent, 'success', ['tx_ref' => 'CRMS99-SOMEONE-ELSE']))
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_customer_cannot_complete_someone_elses_checkout(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openCard($rental);
        $intruder = Customer::factory()->create()->user;

        $this->actingAs($intruder)
            ->get($this->returnUrl($intent, 'success'))
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_customer_without_a_completed_profile_is_sent_back_to_it(): void
    {
        $this->configureGateway();
        $this->fakePush();
        $rental = $this->rentalOwing(200000);

        $this->actingAs(User::factory()->customer()->create())
            ->post(route('customer.rentals.pay', $rental), ['payment_method' => 'mpesa'])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('error', 'Please complete your customer profile before paying online.');

        $this->assertDatabaseCount('payment_intents', 0);
        Http::assertNothingSent();
    }

    public function test_the_waiting_screen_shows_a_live_prompt_only_to_its_own_customer(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);

        $this->actingAs($rental->customer->user)
            ->get(route('customer.payments.waiting', $intent))
            ->assertOk()
            ->assertSee('Approve the payment prompt');

        $intruder = Customer::factory()->create()->user;
        $this->actingAs($intruder)
            ->get(route('customer.payments.waiting', $intent))
            ->assertNotFound();
    }

    public function test_the_waiting_screen_bounces_once_the_prompt_is_over(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $intent->update(['status' => PaymentIntentStatus::Failed, 'failure_reason' => GatewayCaptureOutcome::Declined->value]);

        $this->actingAs($rental->customer->user)
            ->get(route('customer.payments.waiting', $intent))
            ->assertRedirect(route('customer.rentals.index'));
    }

    public function test_the_status_endpoint_tracks_the_intent_for_the_polling_page(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);

        $this->actingAs($rental->customer->user)
            ->getJson(route('customer.payments.status', $intent))
            ->assertOk()
            ->assertJson(['status' => 'started', 'finished' => false, 'failed' => false]);

        $this->fakeVerify($intent);
        $this->webhook($intent)->assertOk();

        $this->actingAs($rental->customer->user)
            ->getJson(route('customer.payments.status', $intent))
            ->assertOk()
            ->assertJson(['status' => 'recorded', 'finished' => true, 'failed' => false]);
    }

    public function test_confirming_the_prompt_records_what_the_gateway_confirms(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.payments.confirm', $intent))
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('status', 'Thank you, your online payment has been recorded against the rental.');

        $this->assertDatabaseCount('payments', 1);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_confirming_before_the_gateway_sees_the_payment_records_nothing(): void
    {
        $rental = $this->rentalOwing(200000);
        $intent = $this->openPush($rental);
        $this->fakeVerify($intent, ['transactionStatus' => 'failed']);

        $this->actingAs($rental->customer->user)
            ->post(route('customer.payments.confirm', $intent))
            ->assertRedirect(route('customer.rentals.index'))
            ->assertSessionHas('error', 'The payment request was not approved on your phone. Nothing has been added to your account — you can try again.');

        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * A callback shaped like AzamPay's but without the fields we key on.
     *
     * @param  array<string, mixed>  $payload
     */
    private function webhookFallback(array $payload): TestResponse
    {
        return $this->withHeaders(['x-webhook-secret' => self::SECRET])
            ->postJson(route('webhooks.azampay'), $payload);
    }
}

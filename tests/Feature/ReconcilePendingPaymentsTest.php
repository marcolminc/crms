<?php

namespace Tests\Feature;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Rental;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReconcilePendingPaymentsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://azampay.test';

    private const AUTH = 'https://authenticator.azampay.test/AppRegistration/GenerateToken';

    private const VERIFY = self::BASE.'/api/v1/TransactionStatus/GetTransactionStatus';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.azampay.app_name' => 'CRMS Test',
            'services.azampay.client_id' => 'client-1',
            'services.azampay.client_secret' => 'secret-1',
            'services.azampay.base_url' => self::BASE,
            'services.azampay.auth_url' => self::AUTH,
            'services.azampay.currency' => 'TZS',
        ]);

        Cache::clear();
        Http::preventStrayRequests();
    }

    private function openIntent(Rental $rental): PaymentIntent
    {
        return PaymentIntent::factory()->pushed()->create([
            'rental_id' => $rental->id,
            'amount' => 200000,
        ]);
    }

    private function rentalOwing(): Rental
    {
        $rental = Rental::factory()->active()->create([
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['total_amount' => 200000])->save();

        return $rental;
    }

    private function fakeVerify(PaymentIntent $intent, string $status = 'success'): void
    {
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            (self::VERIFY.'*') => ['success' => true, 'data' => [
                'externalId' => $intent->tx_ref,
                'reference' => '9001',
                'mnoreference' => 'AZAM-MOCK-9001',
                'transactionStatus' => $status,
                'amount' => (float) $intent->amount,
                'currency' => 'TZS',
            ]],
        ]);
    }

    public function test_money_paid_but_never_callbacks_gets_settled_from_the_gateway(): void
    {
        $rental = $this->rentalOwing();
        $intent = $this->openIntent($rental);
        $this->fakeVerify($intent);

        // Past the grace period, so the reconciler considers it overdue for news.
        $this->travelTo(now()->addMinutes(10));

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Recorded->value,
        ]);
        $payment = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->balanceDue(), 0.01);
    }

    public function test_a_fresh_prompt_is_left_alone_to_its_callback(): void
    {
        $intent = $this->openIntent($this->rentalOwing());
        $this->fakeVerify($intent);

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertDatabaseHas('payment_intents', ['id' => $intent->id, 'status' => PaymentIntentStatus::Started->value]);
        Http::assertNothingSent();
    }

    public function test_money_the_gateway_has_not_seen_waits_for_another_round(): void
    {
        $intent = $this->openIntent($this->rentalOwing());
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            (self::VERIFY.'*') => ['message' => 'Rate limited', 500],
        ]);

        $this->travelTo(now()->addMinutes(10));

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Started->value,
            'payment_id' => null,
        ]);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_prompt_the_gateway_reports_as_unpaid_is_closed_as_declined(): void
    {
        $intent = $this->openIntent($this->rentalOwing());
        $this->fakeVerify($intent, 'failed');

        $this->travelTo(now()->addMinutes(10));

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Failed->value,
            'failure_reason' => GatewayCaptureOutcome::Declined->value,
        ]);
    }

    public function test_checkouts_that_survived_every_round_expire(): void
    {
        $intent = $this->openIntent($this->rentalOwing());
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            (self::VERIFY.'*') => ['message' => 'Rate limited', 500],
        ]);

        $this->travelTo(now()->addDay()->addMinutes(10));

        $this->artisan('payments:reconcile')->assertExitCode(0);

        $this->assertDatabaseHas('payment_intents', [
            'id' => $intent->id,
            'status' => PaymentIntentStatus::Failed->value,
            'failure_reason' => GatewayCaptureOutcome::Expired->value,
        ]);
    }

    public function test_an_unconfigured_gateway_makes_the_job_a_no_op(): void
    {
        config(['services.azampay.client_id' => null]);
        $this->openIntent($this->rentalOwing());

        $this->travelTo(now()->addMinutes(10));

        $this->artisan('payments:reconcile')->assertExitCode(0);

        Http::assertNothingSent();
    }
}

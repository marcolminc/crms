<?php

namespace Tests\Feature;

use App\Enums\GatewayCaptureOutcome;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Rental;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PaymentIntentTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function rental(): Rental
    {
        return Rental::factory()->active()->create([
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
    }

    public function test_a_checkout_is_live_only_inside_the_reuse_window(): void
    {
        $rental = $this->rental();
        $intent = PaymentIntent::factory()->started()->create(['rental_id' => $rental->id]);

        $this->travelTo(now()->addMinutes(29));
        $this->assertSame($intent->id, PaymentIntent::liveFor($rental)->id);

        $this->travelTo(now()->addMinutes(2));
        $this->assertNull(PaymentIntent::liveFor($rental));
    }

    public function test_a_settled_or_abandoned_checkout_is_never_reused(): void
    {
        $rental = $this->rental();

        PaymentIntent::factory()->recorded()->create(['rental_id' => $rental->id]);
        PaymentIntent::factory()->failed()->create(['rental_id' => $rental->id]);

        $this->assertNull(PaymentIntent::liveFor($rental));

        $live = PaymentIntent::factory()->create(['rental_id' => $rental->id]);
        $this->assertSame($live->id, PaymentIntent::liveFor($rental)->id);
    }

    public function test_a_live_checkout_is_looked_up_on_its_own_rental_only(): void
    {
        $mine = $this->rental();
        $theirs = $this->rental();
        PaymentIntent::factory()->started()->create(['rental_id' => $mine->id]);

        $this->assertNull(PaymentIntent::liveFor($theirs));
    }

    public function test_an_intent_is_found_by_the_reference_we_issued(): void
    {
        $intent = PaymentIntent::factory()->started()->create();

        $this->assertSame($intent->id, PaymentIntent::findByTxRef($intent->tx_ref)->id);
        $this->assertNull(PaymentIntent::findByTxRef('CRMS1-NO-SUCH-REF'));
    }

    public function test_the_reference_we_issued_cannot_be_issued_twice(): void
    {
        $intent = PaymentIntent::factory()->started()->create();

        $this->expectException(QueryException::class);

        PaymentIntent::factory()->started()->create(['tx_ref' => $intent->tx_ref]);
    }

    public function test_the_first_caller_wins_the_claim_and_the_second_is_shut_out(): void
    {
        $intent = PaymentIntent::factory()->started()->create();

        $this->assertTrue(PaymentIntent::claim($intent->id, ['gateway_ref' => 'AZAM-MOCK-1', 'gateway_tx_id' => '9001']));
        $this->assertFalse(PaymentIntent::claim($intent->id, ['gateway_ref' => 'AZAM-MOCK-2', 'gateway_tx_id' => '9002']));

        $this->assertSame('AZAM-MOCK-1', $intent->refresh()->gateway_ref);
    }

    public function test_one_gateway_transaction_cannot_settle_two_checkouts(): void
    {
        $rental = $this->rental();
        $first = PaymentIntent::factory()->started()->create(['rental_id' => $rental->id]);
        $second = PaymentIntent::factory()->started()->create(['rental_id' => $rental->id]);

        $this->assertTrue(PaymentIntent::claim($first->id, ['gateway_ref' => 'AZAM-MOCK-SHARED']));

        $this->expectException(QueryException::class);

        try {
            PaymentIntent::claim($second->id, ['gateway_ref' => 'AZAM-MOCK-SHARED']);
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) $exception->getCode());
            $this->assertStringContainsString('gateway_ref', $exception->getMessage());

            throw $exception;
        }
    }

    public function test_one_payment_cannot_settle_two_checkouts(): void
    {
        $rental = $this->rental();
        $payment = Payment::factory()->cash()->create(['rental_id' => $rental->id]);
        $first = PaymentIntent::factory()->started()->create(['rental_id' => $rental->id]);
        $second = PaymentIntent::factory()->started()->create(['rental_id' => $rental->id]);

        $first->update(['payment_id' => $payment->id, 'status' => PaymentIntentStatus::Recorded]);

        try {
            $second->update(['payment_id' => $payment->id]);
            $this->fail('A single payment was allowed to settle two checkouts.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', (string) $exception->getCode());
        }
    }

    public function test_a_recorded_intent_points_at_the_payment_it_settled(): void
    {
        $rental = $this->rental();
        $intent = PaymentIntent::factory()->recorded()->create(['rental_id' => $rental->id]);

        $this->assertSame(PaymentIntentStatus::Recorded, $intent->status);
        $this->assertNotNull($intent->gateway_ref);
        $this->assertNotNull($intent->verified_at);
        $this->assertSame($rental->id, $intent->payment->rental_id);
        $this->assertSame($rental->id, $intent->rental->id);
    }

    public function test_a_failed_intent_records_why_it_was_refused(): void
    {
        $intent = PaymentIntent::factory()->failed(GatewayCaptureOutcome::AmountMismatch)->create();

        $this->assertSame(PaymentIntentStatus::Failed, $intent->status);
        $this->assertSame(GatewayCaptureOutcome::AmountMismatch->value, $intent->failure_reason);
        $this->assertNull($intent->checkout_url);
    }

    public function test_the_gateway_reference_is_stored_without_exposing_the_ledger(): void
    {
        $intent = PaymentIntent::factory()->started()->create(['amount' => 250000]);

        $this->assertEqualsWithDelta(250000.0, (float) $intent->amount, 0.01);
        $this->assertSame('TZS', $intent->currency);
        $this->assertNull($intent->payment_id);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_a_live_prompt_is_awaiting_phone_approval_and_a_page_redirect_is_not(): void
    {
        $pushed = PaymentIntent::factory()->pushed()->create();
        $hosted = PaymentIntent::factory()->started()->create(['payment_method' => PaymentMethod::CreditCard]);

        $this->assertTrue($pushed->isAwaitingPhoneApproval());
        $this->assertFalse($hosted->isAwaitingPhoneApproval());
    }
}

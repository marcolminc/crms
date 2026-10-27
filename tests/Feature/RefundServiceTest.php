<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Payment;
use App\Models\Rental;
use App\Services\RefundService;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefundServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://azampay.test';

    private const AUTH = 'https://authenticator.azampay.test/AppRegistration/GenerateToken';

    private const DISBURSE = self::BASE.'/api/v1/Disbursement/Disburse';

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
            'services.azampay.payout_account_number' => '255700000000',
            'services.azampay.payout_account_name' => 'CRMS Rentals',
        ]);

        Cache::clear();
        Http::preventStrayRequests();
    }

    private function paidOffRental(float $amount): Rental
    {
        $rental = Rental::factory()->active()->create([
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['total_amount' => $amount])->save();
        $rental->customer->user->update(['phone' => '0722345678']);

        return $rental;
    }

    private function onlinePayment(Rental $rental, float $amount): Payment
    {
        return Payment::factory()->mobileMoney()->create([
            'rental_id' => $rental->id,
            'amount' => $amount,
        ]);
    }

    private function fakePayout(array $response): void
    {
        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            self::DISBURSE => $response,
        ]);
    }

    public function test_a_full_refund_pays_out_first_and_lands_as_a_linked_ledger_row(): void
    {
        $rental = $this->paidOffRental(100000);
        $payment = $this->onlinePayment($rental, 100000);
        $this->fakePayout(['success' => true, 'transactionId' => 'AZAM-DISB-77']);

        $refund = $this->app->make(RefundService::class)->refund($payment, 100000.0, 'Rental cancelled');

        $this->assertSame(PaymentType::Refund, $refund->type);
        $this->assertSame($payment->id, $refund->parent_payment_id);
        $this->assertSame('AZAM-DISB-77', $refund->transaction_reference);
        $this->assertStringContainsString('Refund via AzamPay', (string) $refund->notes);
        $this->assertEqualsWithDelta(100000.0, (float) $refund->amount, 0.01);

        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);

        // The refunded money is no longer counted as collected.
        $this->assertEqualsWithDelta(0.0, $rental->fresh()->paidAmount(), 0.01);
        $this->assertEqualsWithDelta(100000.0, $rental->fresh()->balanceDue(), 0.01);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $rental->customer->user_id,
            'type' => 'payment_refunded',
        ]);

        Http::assertSent(fn ($request): bool => $request->url() === self::DISBURSE
            && $request->data()['destination']['accountNumber'] === '255722345678');
    }

    public function test_a_partial_refund_leaves_the_payment_open_for_its_remainder(): void
    {
        $rental = $this->paidOffRental(200000);
        $payment = $this->onlinePayment($rental, 100000);
        $this->fakePayout(['success' => true, 'transactionId' => 'AZAM-DISB-40']);

        $service = $this->app->make(RefundService::class);
        $service->refund($payment, 40000.0, 'Day not used');

        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
        $this->assertEqualsWithDelta(60000.0, $payment->refundableAmount(), 0.01);
        $this->assertEqualsWithDelta(60000.0, $rental->fresh()->paidAmount(), 0.01);
    }

    public function test_money_the_gateway_refuses_never_reaches_the_ledger(): void
    {
        $rental = $this->paidOffRental(100000);
        $payment = $this->onlinePayment($rental, 100000);
        $this->fakePayout(['success' => false, 'message' => 'Insufficient wallet balance']);

        try {
            $this->app->make(RefundService::class)->refund($payment, 100000.0, 'Please refund');
            $this->fail('A refused payout was reported as a refund.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('did not accept', $exception->getMessage());
        }

        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(PaymentStatus::Paid, $payment->refresh()->status);
    }

    public function test_more_than_is_outstanding_is_refused_before_the_gateway_is_called(): void
    {
        $rental = $this->paidOffRental(100000);
        $payment = $this->onlinePayment($rental, 100000);
        $this->fakePayout(['success' => true, 'transactionId' => 'never']);

        $this->expectException(DomainException::class);

        try {
            $this->app->make(RefundService::class)->refund($payment, 150000.0, 'Over the top');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_cash_taken_at_the_office_is_not_an_azampay_refund(): void
    {
        $rental = $this->paidOffRental(100000);
        $payment = Payment::factory()->cash()->create(['rental_id' => $rental->id, 'amount' => 100000]);

        $this->assertFalse($this->app->make(RefundService::class)->refundsOnline($payment));
    }

    public function test_refunds_close_up_when_the_payout_wallet_is_not_registered(): void
    {
        config(['services.azampay.payout_account_number' => null]);

        $rental = $this->paidOffRental(100000);
        $payment = $this->onlinePayment($rental, 100000);

        $this->assertFalse($this->app->make(RefundService::class)->refundsOnline($payment));
    }
}

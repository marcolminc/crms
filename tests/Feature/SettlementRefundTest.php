<?php

namespace Tests\Feature;

use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Models\Payment;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * FR9: the settlement controller pays an over-paid hire back through the AzamPay
 * disbursement rail. It is gated to finance and managers and only refunds money
 * the gateway can actually push out.
 */
class SettlementRefundTest extends TestCase
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

    private function finance(): User
    {
        return User::factory()->finance()->create();
    }

    /**
     * A completed rental that has collected more than its final total.
     */
    private function overPaidRental(string $method = 'mobileMoney'): Rental
    {
        $rental = Rental::factory()->completed()->create([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
            'days' => 2,
            'rate_per_day' => 100000,
            'additional_charges' => 0,
            'discount' => 0,
        ]);
        $rental->forceFill(['subtotal' => 200000, 'total_amount' => 200000, 'status' => RentalStatus::Completed])->save();
        $rental->customer->user->update(['phone' => '0722345678']);

        $payment = $method === 'cash'
            ? Payment::factory()->cash()->create(['rental_id' => $rental->id])
            : Payment::factory()->mobileMoney()->create(['rental_id' => $rental->id]);

        $payment->forceFill(['amount' => 300000])->save();

        return $rental->fresh();
    }

    public function test_finance_settles_an_over_paid_rental_through_azampay(): void
    {
        $rental = $this->overPaidRental();
        $payment = $rental->payments()->first();

        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            self::DISBURSE => ['success' => true, 'transactionId' => 'AZAM-DISB-88'],
        ]);

        $this->actingAs($this->finance())
            ->from(route('admin.rentals.refund', $rental))
            ->post(route('admin.rentals.refund.store', $rental), [
                'amount' => 100000,
                'reason' => 'Damage charge reversed after payment',
            ])
            ->assertRedirect(route('admin.rentals.invoice', $rental))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('payments', [
            'rental_id' => $rental->id,
            'type' => PaymentType::Refund->value,
            'parent_payment_id' => $payment->id,
            'amount' => 100000,
            'transaction_reference' => 'AZAM-DISB-88',
        ]);

        // Collected drops to the final total, so nothing is owed back.
        $this->assertEqualsWithDelta(200000, $rental->fresh()->paidAmount(), 0.01);
        $this->assertEqualsWithDelta(0, max(0, $rental->fresh()->paidAmount() - (float) $rental->fresh()->total_amount), 0.01);

        $this->assertDatabaseHas('audit_logs', ['subject_type' => Rental::class, 'action' => 'settlement.refunded']);

        Http::assertSent(fn ($request): bool => $request->url() === self::DISBURSE);
    }

    public function test_a_refund_larger_than_the_surplus_is_refused_before_the_gateway_is_called(): void
    {
        $rental = $this->overPaidRental();

        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            self::DISBURSE => ['success' => true, 'transactionId' => 'never'],
        ]);

        $this->actingAs($this->finance())
            ->post(route('admin.rentals.refund.store', $rental), [
                'amount' => 250000, // surplus is only 100,000
                'reason' => 'Too much',
            ])
            ->assertSessionHas('error');

        $this->assertEqualsWithDelta(300000, $rental->fresh()->paidAmount(), 0.01);
        Http::assertNotSent(fn ($request): bool => $request->url() === self::DISBURSE);
    }

    public function test_a_counter_agent_cannot_settle_a_refund(): void
    {
        $rental = $this->overPaidRental();

        $this->actingAs(User::factory()->counterAgent()->create())
            ->get(route('admin.rentals.refund', $rental))
            ->assertForbidden();

        $this->actingAs(User::factory()->counterAgent()->create())
            ->post(route('admin.rentals.refund.store', $rental), ['amount' => 100000, 'reason' => 'Nope'])
            ->assertForbidden();
    }

    public function test_a_cash_only_overpayment_is_referred_to_the_office(): void
    {
        $rental = $this->overPaidRental('cash');

        Http::fake([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
            self::DISBURSE => ['success' => true, 'transactionId' => 'never'],
        ]);

        $this->actingAs($this->finance())
            ->post(route('admin.rentals.refund.store', $rental), [
                'amount' => 100000,
                'reason' => 'Cash paid at the desk',
            ])
            ->assertSessionHas('error');

        // Only the original cash payment exists; no refund was written.
        $this->assertDatabaseCount('payments', 1);
        $this->assertStringContainsString('office', session('error'));
        Http::assertNotSent(fn ($request): bool => $request->url() === self::DISBURSE);
    }
}

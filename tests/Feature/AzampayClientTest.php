<?php

namespace Tests\Feature;

use App\Services\AzampayClient;
use App\Services\GatewayCheckoutRequest;
use App\Services\GatewayPayoutRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AzampayClientTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const BASE = 'https://azampay.test';

    private const AUTH = 'https://authenticator.azampay.test/AppRegistration/GenerateToken';

    private const PUSH = self::BASE.'/azampay/mno/checkout';

    private const HOSTED = self::BASE.'/api/v1/Partner/PostCheckout';

    private const VERIFY = self::BASE.'/api/v1/Partner/GetTransactionStatus';

    private const DISBURSE = self::BASE.'/api/v1/Disbursement/Disburse';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.azampay.app_name' => 'CRMS Test',
            'services.azampay.client_id' => 'client-1',
            'services.azampay.client_secret' => 'secret-1',
            'services.azampay.webhook_secret' => null,
            'services.azampay.base_url' => self::BASE,
            'services.azampay.auth_url' => self::AUTH,
            'services.azampay.currency' => 'TZS',
            'services.azampay.vendor_id' => null,
            'services.azampay.vendor_name' => null,
            'services.azampay.payout_account_number' => null,
            'services.azampay.payout_account_name' => null,
        ]);

        Cache::clear();
        Http::preventStrayRequests();
    }

    private function gateway(): AzampayClient
    {
        return $this->app->make(AzampayClient::class);
    }

    /**
     * @param  array<string, mixed>  $stubs
     */
    private function fake(array $stubs): void
    {
        Http::fake(array_merge([
            self::AUTH => ['success' => true, 'data' => ['accessToken' => 'test-token', 'expiry' => now()->addMinutes(15)->toIso8601String()]],
        ], $stubs));
    }

    private function checkoutRequest(): GatewayCheckoutRequest
    {
        return new GatewayCheckoutRequest(
            txRef: 'CRMS1-ABC',
            amount: 200000.0,
            currency: 'TZS',
            redirectUrl: 'https://crms.test/payments/1/return',
            email: 'thug@example.com',
            name: 'Thug Juma',
            phoneNumber: '255722345678',
            provider: 'M-Pesa',
        );
    }

    public function test_the_gateway_is_off_until_all_three_credentials_are_present(): void
    {
        $this->assertTrue($this->gateway()->isEnabled());

        config(['services.azampay.client_secret' => null]);
        $this->assertFalse($this->gateway()->isEnabled());
    }

    public function test_the_hosted_page_and_the_payout_rail_have_their_own_extra_keys(): void
    {
        $this->assertFalse($this->gateway()->hostedEnabled());
        $this->assertFalse($this->gateway()->disburseEnabled());

        config(['services.azampay.vendor_id' => 'vendor-1']);
        $this->assertTrue($this->gateway()->hostedEnabled());

        config([
            'services.azampay.payout_account_number' => '255700000000',
            'services.azampay.payout_account_name' => 'CRMS Rentals',
        ]);
        $this->assertTrue($this->gateway()->disburseEnabled());
    }

    public function test_a_push_returns_the_gateway_transaction_id(): void
    {
        $this->fake([
            self::PUSH => ['success' => true, 'transactionId' => 'AZAM-TX-9001'],
        ]);

        $this->assertSame('AZAM-TX-9001', $this->gateway()->initiateMobileMoneyCheckout($this->checkoutRequest()));

        Http::assertSent(fn ($request): bool => $request->url() === self::PUSH
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && $request->data()['accountNumber'] === '255722345678'
            && $request->data()['amount'] === '200000.00'
            && $request->data()['provider'] === 'M-Pesa');
    }

    public function test_a_refused_push_is_reported_as_nothing(): void
    {
        $this->fake([
            self::PUSH => ['success' => false, 'message' => 'Invalid provider'],
        ]);

        $this->assertNull($this->gateway()->initiateMobileMoneyCheckout($this->checkoutRequest()));
    }

    public function test_the_bearer_token_is_remembered_until_it_ages_out(): void
    {
        $this->fake([
            self::PUSH => ['success' => true, 'transactionId' => 'AZAM-TX-1'],
        ]);

        $this->gateway()->initiateMobileMoneyCheckout($this->checkoutRequest());
        $this->gateway()->initiateMobileMoneyCheckout($this->checkoutRequest());

        // One token fetch, two pushes: three requests, not four.
        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => $request->url() === self::AUTH);

        $tokenRequests = 0;
        Http::assertSent(function ($request) use (&$tokenRequests): bool {
            if ($request->url() === self::AUTH) {
                $tokenRequests++;
            }

            return true;
        });
        $this->assertSame(1, $tokenRequests);
    }

    public function test_a_token_the_gateway_wont_hand_out_stops_every_call(): void
    {
        Http::fake([
            self::AUTH => ['success' => false, 'message' => 'Invalid credentials'],
            self::PUSH => ['success' => true, 'transactionId' => 'never'],
        ]);

        $this->assertNull($this->gateway()->initiateMobileMoneyCheckout($this->checkoutRequest()));

        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request): bool => $request->url() === self::PUSH);
    }

    public function test_the_hosted_page_answers_with_the_checkout_url(): void
    {
        config(['services.azampay.vendor_id' => 'vendor-1']);
        $this->fake([
            self::HOSTED => ['success' => true, 'data' => 'https://checkout.azampay.test/pay/abc'],
        ]);

        $this->assertSame(
            'https://checkout.azampay.test/pay/abc',
            $this->gateway()->initiateHostedCheckout($this->checkoutRequest())
        );
    }

    public function test_a_hosted_answer_that_is_not_a_url_is_nothing_to_show(): void
    {
        config(['services.azampay.vendor_id' => 'vendor-1']);
        $this->fake([
            self::HOSTED => ['success' => true, 'data' => true],
        ]);

        $this->assertNull($this->gateway()->initiateHostedCheckout($this->checkoutRequest()));
    }

    public function test_verification_reads_azamays_own_vocabulary(): void
    {
        $this->fake([
            (self::VERIFY.'*') => ['success' => true, 'data' => [
                'externalId' => 'CRMS1-ABC',
                'reference' => '9001',
                'mnoreference' => 'MZD38H292',
                'transactionStatus' => 'Successful',
                'amount' => 200000.00,
                'currency' => 'TZS',
            ]],
        ]);

        $result = $this->gateway()->verifyTransaction('9001');

        $this->assertNotNull($result);
        $this->assertTrue($result->successful);
        $this->assertSame('CRMS1-ABC', $result->txRef);
        $this->assertSame('MZD38H292', $result->gatewayRef);
        $this->assertSame('9001', $result->transactionId);
        $this->assertEqualsWithDelta(200000.0, $result->amount, 0.01);
    }

    public function test_verification_that_returns_nothing_is_a_retry_not_a_refusal(): void
    {
        $this->fake([
            (self::VERIFY.'*') => ['success' => false, 'message' => 'Not found'],
        ]);

        $this->assertNull($this->gateway()->verifyTransaction('9001'));
    }

    public function test_callbacks_are_open_only_until_a_secret_is_configured(): void
    {
        $this->assertTrue($this->gateway()->webhookAuthenticates(null));
        $this->assertTrue($this->gateway()->webhookAuthenticates('anything'));

        config(['services.azampay.webhook_secret' => 's3cret']);

        $this->assertFalse($this->gateway()->webhookAuthenticates(null));
        $this->assertFalse($this->gateway()->webhookAuthenticates('guessed'));
        $this->assertTrue($this->gateway()->webhookAuthenticates('s3cret'));
    }

    public function test_a_payout_carries_both_wallets_and_returns_the_gateway_reference(): void
    {
        config([
            'services.azampay.payout_account_number' => '255700000000',
            'services.azampay.payout_account_name' => 'CRMS Rentals',
        ]);
        $this->fake([
            self::DISBURSE => ['success' => true, 'transactionId' => 'AZAM-DISB-77'],
        ]);

        $reference = $this->gateway()->disburse(new GatewayPayoutRequest(
            externalId: 'CRMSF9-XYZ',
            amount: 50000.0,
            currency: 'TZS',
            destinationAccount: '255722345678',
            destinationName: 'Thug Juma',
            destinationProvider: 'M-Pesa',
            remarks: 'Cancellation refund',
        ));

        $this->assertSame('AZAM-DISB-77', $reference);

        Http::assertSent(fn ($request): bool => $request->url() === self::DISBURSE
            && $request->data()['source']['accountNumber'] === '255700000000'
            && $request->data()['source']['bankName'] === 'Azampesa'
            && $request->data()['destination']['bankName'] === 'M-Pesa'
            && $request->data()['transferDetails']['amount'] === '50000.00'
            && $request->data()['externalReferenceId'] === 'CRMSF9-XYZ');
    }

    public function test_an_accepted_payout_without_an_id_keeps_our_own_reference(): void
    {
        config([
            'services.azampay.payout_account_number' => '255700000000',
            'services.azampay.payout_account_name' => 'CRMS Rentals',
        ]);
        $this->fake([
            self::DISBURSE => ['success' => true, 'data' => true],
        ]);

        $reference = $this->gateway()->disburse(new GatewayPayoutRequest(
            externalId: 'CRMSF9-XYZ',
            amount: 50000.0,
            currency: 'TZS',
            destinationAccount: '255722345678',
            destinationName: 'Thug Juma',
            destinationProvider: 'M-Pesa',
        ));

        $this->assertSame('CRMSF9-XYZ', $reference);
    }

    public function test_no_payout_is_attempted_without_a_registered_wallet(): void
    {
        $this->fake([
            self::DISBURSE => ['success' => true, 'transactionId' => 'never'],
        ]);

        $this->assertNull($this->gateway()->disburse(new GatewayPayoutRequest(
            externalId: 'CRMSF9-XYZ',
            amount: 50000.0,
            currency: 'TZS',
            destinationAccount: '255722345678',
            destinationName: 'Thug Juma',
            destinationProvider: 'M-Pesa',
        )));

        Http::assertNothingSent();
    }
}

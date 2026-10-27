<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin HTTP layer over the AzamPay Tanzania API. It never decides anything about our
 * ledger: it reports what the gateway said, or null when the gateway could not be asked.
 *
 * Endpoints and payload fields follow AzamPay's developer documentation
 * (developerdocs.azampay.co.tz) as reproduced by its published SDKs. Confirm every
 * path with AzamPay before switching on production keys — the status lookup path in
 * particular is not publicly listed and may differ.
 */
final class AzampayClient
{
    private const CONNECT_TIMEOUT = 3;

    /**
     * The sandbox dials the payer's handset before it accepts the push; observed
     * acceptance took 30-90s, far beyond the ordinary request budget.
     */
    private const PUSH_TIMEOUT = 120;

    private const REQUEST_TIMEOUT = 15;

    private const TOKEN_CACHE_KEY = 'azampay.access_token';

    private const TOKEN_FALLBACK_TTL = 900;

    public function isEnabled(): bool
    {
        return $this->configValue('app_name') !== null
            && $this->configValue('client_id') !== null
            && $this->configValue('client_secret') !== null;
    }

    /**
     * The hosted (card) page is a separate partner registration at AzamPay; without
     * its vendor id we can only push mobile money.
     */
    public function hostedEnabled(): bool
    {
        return $this->isEnabled() && $this->configValue('vendor_id') !== null;
    }

    /**
     * Paying money out needs the merchant wallet registered with AzamPay as a
     * payout source; without it refunds stay manual at the office.
     */
    public function disburseEnabled(): bool
    {
        return $this->isEnabled()
            && $this->configValue('payout_account_number') !== null
            && $this->configValue('payout_account_name') !== null;
    }

    /**
     * Push a mobile-money checkout: AzamPay dials the payer's handset (USSD prompt)
     * and the outcome arrives later through the callback. Returns the gateway
     * transaction id, not a URL. This call is never retried: it is state-changing
     * and the API offers no idempotency key.
     */
    public function initiateMobileMoneyCheckout(GatewayCheckoutRequest $request): ?string
    {
        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        try {
            $response = $this->http($token)->timeout(self::PUSH_TIMEOUT)->post($this->url('azampay/mno/checkout'), [
                'accountNumber' => (string) $request->phoneNumber,
                'amount' => number_format($request->amount, 2, '.', ''),
                'currency' => $request->currency,
                'externalId' => $request->txRef,
                'provider' => (string) $request->provider,
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('AzamPay mobile money push could not be sent.', ['external_id' => $request->txRef]);

            return null;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::warning('AzamPay refused to open a mobile money push.', [
                'external_id' => $request->txRef,
                'status' => $response->status(),
                'message' => $response->json('message') ?? $response->json('msg'),
            ]);

            return null;
        }

        $transactionId = $response->json('transactionId')
            ?? $response->json('data.transactionId')
            ?? $response->json('data');

        return is_string($transactionId) && $transactionId !== '' ? $transactionId : null;
    }

    /**
     * Open a hosted checkout page (the card rail). AzamPay answers with a URL the
     * payer is redirected to; the payment still confirms through the callback.
     */
    public function initiateHostedCheckout(GatewayCheckoutRequest $request): ?string
    {
        if (! $this->hostedEnabled()) {
            return null;
        }

        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        try {
            $response = $this->http($token)->post($this->url('api/v1/Partner/PostCheckout'), [
                'amount' => number_format($request->amount, 2, '.', ''),
                'appName' => (string) $this->configValue('app_name'),
                'cart' => ['items' => [['name' => $request->name !== '' ? $request->name : 'Car rental payment']]],
                'clientId' => (string) $this->configValue('client_id'),
                'currency' => $request->currency,
                'externalId' => $request->txRef,
                'language' => 'EN',
                'redirectFailURL' => $request->redirectUrl.'&status=failed',
                'redirectSuccessURL' => $request->redirectUrl,
                'requestOrigin' => (string) config('app.url'),
                'vendorId' => (string) $this->configValue('vendor_id'),
                'vendorName' => $this->configValue('vendor_name') ?? (string) config('app.name'),
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('AzamPay hosted checkout request could not be sent.', ['external_id' => $request->txRef]);

            return null;
        }

        $link = $response->json('data');

        if (! $response->successful() || $response->json('success') !== true || ! is_string($link) || ! str_starts_with($link, 'http')) {
            Log::warning('AzamPay refused to open a hosted checkout.', [
                'external_id' => $request->txRef,
                'status' => $response->status(),
                'message' => $response->json('message') ?? $response->json('msg'),
            ]);

            return null;
        }

        return $link;
    }

    /**
     * Ask AzamPay what became of a transaction. The caller passes the gateway
     * reference when it has one, otherwise our own external id; the sandbox accepts
     * either, but confirm the lookup key against AzamPay's live documentation.
     */
    public function verifyTransaction(string $reference): ?GatewayTransactionResult
    {
        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        try {
            $response = $this->http($token)->get($this->url('api/v1/Partner/GetTransactionStatus'), [
                'pgReferenceId' => $reference,
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('AzamPay verification could not be reached.', ['reference' => $reference]);

            return null;
        }

        $data = $response->json('data') ?? [];

        if (! $response->successful() || $response->json('success') !== true || ! is_array($data)) {
            Log::warning('AzamPay verification did not return a transaction.', [
                'reference' => $reference,
                'status' => $response->status(),
                'message' => $response->json('message') ?? $response->json('msg'),
            ]);

            return null;
        }

        $gatewayStatus = strtolower((string) ($data['transactionStatus'] ?? $data['status'] ?? ''));

        return new GatewayTransactionResult(
            transactionId: (string) ($data['reference'] ?? $data['pgReferenceId'] ?? $data['transactionId'] ?? $reference),
            txRef: (string) ($data['externalId'] ?? $data['utilityref'] ?? ''),
            gatewayRef: isset($data['mnoreference']) && is_string($data['mnoreference']) ? $data['mnoreference'] : null,
            successful: in_array($gatewayStatus, ['success', 'successful', 'completed'], true),
            amount: (float) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? config('services.azampay.currency') ?? ''),
            gatewayStatus: $gatewayStatus,
        );
    }

    /**
     * Pay money out of the merchant wallet to a mobile money account (refunds,
     * deposit releases). Like the push, this call changes state at the gateway
     * and is never retried; AzamPay confirms synchronously with its own reference.
     */
    public function disburse(GatewayPayoutRequest $payout): ?string
    {
        if (! $this->disburseEnabled()) {
            return null;
        }

        $token = $this->accessToken();

        if ($token === null) {
            return null;
        }

        $account = [
            'accountNumber' => (string) $this->configValue('payout_account_number'),
            'fullName' => (string) $this->configValue('payout_account_name'),
            'bankName' => 'Azampesa',
            'currency' => $payout->currency,
            'countryCode' => 'TZ',
        ];

        try {
            $response = $this->http($token)->post($this->url('api/v1/Disbursement/Disburse'), [
                'source' => $account,
                'destination' => [
                    'accountNumber' => $payout->destinationAccount,
                    'fullName' => $payout->destinationName,
                    'bankName' => $payout->destinationProvider,
                    'currency' => $payout->currency,
                    'countryCode' => 'TZ',
                ],
                'transferDetails' => [
                    'amount' => number_format($payout->amount, 2, '.', ''),
                    'type' => 'SendMoney',
                ],
                'externalReferenceId' => $payout->externalId,
                'remarks' => $payout->remarks,
            ]);
        } catch (ConnectionException $exception) {
            Log::warning('AzamPay disbursement could not be sent.', ['external_reference_id' => $payout->externalId]);

            return null;
        }

        if (! $response->successful() || $response->json('success') !== true) {
            Log::warning('AzamPay refused a disbursement.', [
                'external_reference_id' => $payout->externalId,
                'status' => $response->status(),
                'message' => $response->json('message') ?? $response->json('msg'),
            ]);

            return null;
        }

        $reference = $response->json('transactionId')
            ?? $response->json('data.transactionId')
            ?? $response->json('data.reference')
            ?? $response->json('data');

        // An accepted payout we cannot cite is still a payout: fall back to the id
        // we issued so the ledger always carries a traceable reference.
        return is_string($reference) && $reference !== '' ? $reference : $payout->externalId;
    }

    /**
     * AzamPay does not sign its callbacks, so the registered URL is the shared
     * secret; when AZAMPAY_WEBHOOK_SECRET is configured it must also arrive as the
     * x-webhook-secret header.
     */
    public function webhookAuthenticates(?string $token): bool
    {
        $secret = $this->configValue('webhook_secret');

        if ($secret === null) {
            return true;
        }

        return is_string($token) && hash_equals($secret, $token);
    }

    /**
     * Turns a success callback into a transaction result. The status endpoint is
     * not always a usable witness — the sandbox answers "pending" to every lookup,
     * including nonsense — so the callback itself carries the evidence when it
     * unambiguously reports success. Capture still re-checks every invariant
     * (reference, amount, currency, balance) against our own records.
     *
     * @param  array<string, mixed>  $payload
     */
    public function transactionFromCallback(array $payload): ?GatewayTransactionResult
    {
        // AzamPay's samples disagree on the status key's casing; message is the
        // payer-side outcome and arrives as "SUCCESS".
        $status = strtolower((string) ($payload['transactionStatus'] ?? $payload['transactionstatus'] ?? ''));
        $message = strtoupper(trim((string) ($payload['message'] ?? '')));

        if (! in_array($status, ['success', 'successful', 'completed'], true) && $message !== 'SUCCESS') {
            return null;
        }

        $txRef = (string) ($payload['utilityref'] ?? '');

        if ($txRef === '') {
            return null;
        }

        $transactionId = (string) ($payload['externalreference'] ?? $payload['transid'] ?? $payload['reference'] ?? '');

        return new GatewayTransactionResult(
            transactionId: $transactionId !== '' ? $transactionId : $txRef,
            txRef: $txRef,
            gatewayRef: isset($payload['mnoreference']) && is_string($payload['mnoreference']) && $payload['mnoreference'] !== ''
                ? $payload['mnoreference']
                : null,
            successful: true,
            amount: (float) ($payload['amount'] ?? 0),
            currency: (string) ($payload['currency'] ?? config('services.azampay.currency', 'TZS')),
            gatewayStatus: $status !== '' ? $status : 'success',
        );
    }

    /**
     * AzamPay hands out bearer tokens that expire; the token is cached with its own
     * budget, a minute short of the stated expiry so calls never race the cutoff.
     */
    private function accessToken(): ?string
    {
        $cached = Cache::get(self::TOKEN_CACHE_KEY);

        if (is_array($cached)
            && isset($cached['token'], $cached['expires_at'])
            && is_string($cached['token'])
            && now()->lt(CarbonImmutable::parse($cached['expires_at']))) {
            return $cached['token'];
        }

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->connectTimeout(self::CONNECT_TIMEOUT)
                ->timeout(self::REQUEST_TIMEOUT)
                ->post((string) config('services.azampay.auth_url'), [
                    'appName' => (string) $this->configValue('app_name'),
                    'clientId' => (string) $this->configValue('client_id'),
                    'clientSecret' => (string) $this->configValue('client_secret'),
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('AzamPay could not be reached to request a token.');

            return null;
        }

        $token = $response->json('data.accessToken');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            Log::warning('AzamPay rejected our application credentials.', [
                'status' => $response->status(),
                'message' => $response->json('message') ?? $response->json('msg'),
            ]);

            return null;
        }

        $seconds = $this->tokenTtl($response->json('data.expiry'));

        Cache::put(self::TOKEN_CACHE_KEY, [
            'token' => $token,
            'expires_at' => now()->addSeconds($seconds)->toIso8601String(),
        ], $seconds);

        return $token;
    }

    private function tokenTtl(mixed $expiry): int
    {
        $seconds = self::TOKEN_FALLBACK_TTL;

        if (is_string($expiry) && $expiry !== '') {
            try {
                $seconds = max(60, CarbonImmutable::parse($expiry)->diffInSeconds(now()) - 60);
            } catch (\Throwable) {
                // Unparseable expiry: keep the conservative default.
            }
        }

        return min($seconds, self::TOKEN_FALLBACK_TTL);
    }

    private function http(string $token): PendingRequest
    {
        return Http::asJson()
            ->acceptJson()
            ->withToken($token)
            ->connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::REQUEST_TIMEOUT);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.azampay.base_url', 'https://sandbox.azampay.co.tz'), '/').'/'.$path;
    }

    private function configValue(string $key): ?string
    {
        $value = config('services.azampay.'.$key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}

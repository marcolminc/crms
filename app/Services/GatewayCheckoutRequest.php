<?php

namespace App\Services;

/**
 * Everything the gateway needs to open a payment, whoever the gateway is.
 */
final class GatewayCheckoutRequest
{
    public function __construct(
        public readonly string $txRef,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $redirectUrl,
        public readonly string $email,
        public readonly string $name,
        public readonly ?string $phoneNumber = null,
        public readonly ?string $provider = null,
    ) {}
}

<?php

namespace App\Services;

/**
 * Everything AzamPay needs to pay money out to one mobile money account.
 */
final class GatewayPayoutRequest
{
    public function __construct(
        public readonly string $externalId,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $destinationAccount,
        public readonly string $destinationName,
        public readonly string $destinationProvider,
        public readonly string $remarks = '',
    ) {}
}

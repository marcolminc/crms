<?php

namespace App\Services;

/**
 * What the gateway says happened to one of its transactions.
 */
final class GatewayTransactionResult
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $txRef,
        public readonly ?string $gatewayRef,
        public readonly bool $successful,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $gatewayStatus,
    ) {}
}

<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use Carbon\Carbon;

/**
 * A payment exactly as it is about to be written to the ledger, whoever is writing it.
 */
final class PaymentDraft
{
    public function __construct(
        public readonly float $amount,
        public readonly PaymentMethod $method,
        public readonly ?Carbon $paidAt = null,
        public readonly ?string $reference = null,
        public readonly ?int $recordedBy = null,
        public readonly ?string $notes = null,
    ) {}
}

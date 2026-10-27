<?php

namespace App\Enums;

/**
 * What happened to a gateway confirmation, so refusals stay machine-readable in
 * payment_intents.failure_reason.
 */
enum GatewayCaptureOutcome: string
{
    case Recorded = 'recorded';
    case AlreadyRecorded = 'already_recorded';
    case Declined = 'declined';
    case AmountMismatch = 'amount_mismatch';
    case CurrencyMismatch = 'currency_mismatch';
    case ReferenceMismatch = 'reference_mismatch';
    case OverBalance = 'over_balance';
    case RentalClosed = 'rental_closed';
    case GatewayUnavailable = 'gateway_unavailable';
    case UnknownIntent = 'unknown_intent';
    case Expired = 'expired';

    public function isFailure(): bool
    {
        return $this !== self::Recorded && $this !== self::AlreadyRecorded;
    }

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Payment recorded',
            self::AlreadyRecorded => 'Already recorded',
            self::Declined => 'Payment was not completed',
            self::AmountMismatch => 'The gateway amount does not match our record',
            self::CurrencyMismatch => 'The gateway currency does not match our record',
            self::ReferenceMismatch => 'The gateway reference does not match our record',
            self::OverBalance => 'There is no balance left to pay',
            self::RentalClosed => 'The rental is no longer open',
            self::GatewayUnavailable => 'The payment provider could not be reached',
            self::UnknownIntent => 'We have no record of that transaction',
            self::Expired => 'That checkout expired before it was paid',
        };
    }
}

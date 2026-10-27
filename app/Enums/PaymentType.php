<?php

namespace App\Enums;

/**
 * What a row in the payments ledger actually represents (design §8.3). Money
 * only ever arrives as Payment; the refund and deposit cases keep the ledger
 * one-directional so balances cannot be quietly rewritten.
 */
enum PaymentType: string
{
    case Payment = 'payment';
    case Deposit = 'deposit';
    case Refund = 'refund';
    case DepositRelease = 'deposit_release';

    public function label(): string
    {
        return match ($this) {
            self::Payment => 'Payment',
            self::Deposit => 'Deposit',
            self::Refund => 'Refund',
            self::DepositRelease => 'Deposit Release',
        };
    }

    /**
     * Rows that count as money collected from the customer.
     */
    public function isCollected(): bool
    {
        return $this === self::Payment || $this === self::Deposit;
    }

    /**
     * Rows that push money back out to the customer.
     */
    public function isOutgoing(): bool
    {
        return $this === self::Refund || $this === self::DepositRelease;
    }
}

<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Mpesa = 'mpesa';
    case TigoPesa = 'tigo_pesa';
    case AirtelMoney = 'airtel_money';
    case HaloPesa = 'halopesa';
    case BankTransfer = 'bank_transfer';
    case CreditCard = 'credit_card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Mpesa => 'M-Pesa',
            self::TigoPesa => 'Tigo Pesa',
            self::AirtelMoney => 'Airtel Money',
            self::HaloPesa => 'Halo Pesa',
            self::BankTransfer => 'Bank Transfer',
            self::CreditCard => 'Credit / Debit Card',
            self::Other => 'Other',
        };
    }

    /**
     * Mobile money methods require a transaction reference on the payment.
     */
    public static function mobileMoney(): array
    {
        return [
            self::Mpesa,
            self::TigoPesa,
            self::AirtelMoney,
            self::HaloPesa,
        ];
    }

    /**
     * The provider key AzamPay expects for a mobile money push. Confirm the exact
     * spellings against developerdocs.azampay.co.tz before going live; the published
     * SDKs list Airtel, Tigo, Halopesa, Azampesa and M-Pesa.
     */
    public function azampayProvider(): ?string
    {
        return match ($this) {
            self::Mpesa => 'M-Pesa',
            // Names must match the gateway's payment-partner registry
            // (GET /api/v1/Partner/GetPaymentPartners): Tigopesa, Airtel, HaloPesa.
            self::TigoPesa => 'Tigopesa',
            self::AirtelMoney => 'Airtel',
            self::HaloPesa => 'Halopesa',
            default => null,
        };
    }

    public function isOnlineCapable(): bool
    {
        return in_array($this, self::onlineCapable(), true);
    }

    /**
     * Methods the AzamPay integration is able to settle in Tanzania: mobile money
     * by push, card through the hosted checkout page.
     */
    public static function onlineCapable(): array
    {
        return [
            self::CreditCard,
            self::Mpesa,
            self::TigoPesa,
            self::AirtelMoney,
            self::HaloPesa,
        ];
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $m) => $m->value, self::cases()),
            array_map(fn (self $m) => $m->label(), self::cases())
        );
    }
}

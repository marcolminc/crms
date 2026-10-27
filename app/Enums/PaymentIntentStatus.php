<?php

namespace App\Enums;

enum PaymentIntentStatus: string
{
    case Pending = 'pending';
    case Started = 'started';
    case Recorded = 'recorded';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting checkout',
            self::Started => 'Checkout opened',
            self::Recorded => 'Recorded',
            self::Failed => 'Failed',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $s) => $s->value, self::cases()),
            array_map(fn (self $s) => $s->label(), self::cases())
        );
    }
}

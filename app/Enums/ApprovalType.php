<?php

namespace App\Enums;

enum ApprovalType: string
{
    case Discount = 'discount';
    case Damage = 'damage';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Discount',
            self::Damage => 'Damage / additional charge',
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

<?php

namespace App\Enums;

enum FineLiability: string
{
    case Customer = 'customer';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Customer',
            self::Company => 'Company',
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

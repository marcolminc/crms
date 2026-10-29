<?php

namespace App\Enums;

enum FineStatus: string
{
    case Pending = 'pending';
    case Billed = 'billed';
    case Absorbed = 'absorbed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Billed => 'Billed to customer',
            self::Absorbed => 'Absorbed by company',
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

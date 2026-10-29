<?php

namespace App\Enums;

enum FuelLevel: string
{
    case Full = 'full';
    case ThreeQuarter = 'three_quarter';
    case Half = 'half';
    case Quarter = 'quarter';

    public function label(): string
    {
        return match ($this) {
            self::Full => 'Full',
            self::ThreeQuarter => '3/4',
            self::Half => '1/2',
            self::Quarter => '1/4',
        };
    }

    /**
     * The whole-tank fraction, used when a level has to be read as a number.
     */
    public function fraction(): float
    {
        return match ($this) {
            self::Full => 1.0,
            self::ThreeQuarter => 0.75,
            self::Half => 0.5,
            self::Quarter => 0.25,
        };
    }

    /**
     * Backing value => label, ordered from a full tank down.
     */
    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $c) => $c->value, self::cases()),
            array_map(fn (self $c) => $c->label(), self::cases())
        );
    }
}

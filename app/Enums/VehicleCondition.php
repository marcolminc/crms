<?php

namespace App\Enums;

enum VehicleCondition: string
{
    case Excellent = 'excellent';
    case Good = 'good';
    case Fair = 'fair';
    case Damaged = 'damaged';

    public function label(): string
    {
        return match ($this) {
            self::Excellent => 'Excellent',
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Damaged => 'Damaged',
        };
    }

    /**
     * Vehicles returned in one of these conditions may attract damage charges.
     */
    public static function needsCharge(): array
    {
        return [self::Damaged];
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $c) => $c->value, self::cases()),
            array_map(fn (self $c) => $c->label(), self::cases())
        );
    }
}

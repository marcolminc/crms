<?php

namespace App\Enums;

enum VehicleType: string
{
    case Sedan = 'sedan';
    case Suv = 'suv';
    case Hatchback = 'hatchback';
    case Pickup = 'pickup';
    case Minibus = 'minibus';
    case Coach = 'coach';
    case Truck = 'truck';

    public function label(): string
    {
        return match ($this) {
            self::Sedan => 'Sedan',
            self::Suv => 'SUV',
            self::Hatchback => 'Hatchback',
            self::Pickup => 'Pickup',
            self::Minibus => 'Minibus',
            self::Coach => 'Coach',
            self::Truck => 'Truck',
        };
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $t) => $t->value, self::cases()),
            array_map(fn (self $t) => $t->label(), self::cases())
        );
    }
}

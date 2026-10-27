<?php

namespace App\Enums;

enum VehicleStatus: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Rented = 'rented';
    case Maintenance = 'maintenance';
    case OutOfService = 'out_of_service';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Reserved => 'Reserved',
            self::Rented => 'Rented',
            self::Maintenance => 'Under Maintenance',
            self::OutOfService => 'Out of Service',
        };
    }

    /**
     * Statuses a fleet manager/staff may assign manually. Reservation and rental
     * statuses are driven by the booking workflow instead of free choice.
     */
    public static function manuallyAssignable(): array
    {
        return [self::Available, self::Maintenance, self::OutOfService];
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $s) => $s->value, self::cases()),
            array_map(fn (self $s) => $s->label(), self::cases())
        );
    }
}

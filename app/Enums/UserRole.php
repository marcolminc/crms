<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case FleetManager = 'fleet_manager';
    case RentalStaff = 'rental_staff';
    case Accountant = 'accountant';
    case Manager = 'manager';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'System Administrator',
            self::FleetManager => 'Fleet Manager',
            self::RentalStaff => 'Rental Staff',
            self::Accountant => 'Accountant',
            self::Manager => 'Manager',
            self::Customer => 'Customer',
        };
    }

    /**
     * Roles considered internal staff rather than customers.
     */
    public static function staff(): array
    {
        return [
            self::Admin,
            self::FleetManager,
            self::RentalStaff,
            self::Accountant,
            self::Manager,
        ];
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $r) => $r->value, self::cases()),
            array_map(fn (self $r) => $r->label(), self::cases())
        );
    }
}

<?php

namespace App\Enums;

/**
 * The actor catalogue of the CRMS Analysis and Design Document, section 2.
 *
 * Every "Primary, human" actor in the document is a login-able role here.
 * AzamPay and the SMS/Email service are external systems (no user accounts),
 * and the Scheduler is an internal automated actor (console commands), so
 * neither appears as a UserRole.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case CounterAgent = 'counter_agent';
    case BranchManager = 'branch_manager';
    case FleetStaff = 'fleet_staff';
    case Finance = 'finance';
    case Driver = 'driver';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::CounterAgent => 'Counter Agent',
            self::BranchManager => 'Branch Manager',
            self::FleetStaff => 'Fleet / Maintenance Staff',
            self::Finance => 'Finance / Accountant',
            self::Driver => 'Driver (Chauffeur)',
            self::Customer => 'Customer',
        };
    }

    /**
     * Back-office staff actors who administer the system, i.e. everyone but
     * the Customer. The Driver is staff per the document, but its workplace
     * is the trip-log portal (UC24), not the admin screens, so it is kept
     * out of this list until that portal exists.
     */
    public static function staff(): array
    {
        return [
            self::Admin,
            self::CounterAgent,
            self::BranchManager,
            self::FleetStaff,
            self::Finance,
        ];
    }

    public static function options(): array
    {
        return array_combine(
            array_map(fn (self $r) => $r->value, self::cases()),
            array_map(fn (self $r) => $r->label(), self::cases())
        );
    }

    /**
     * The closed set of raw role strings. Used to build the MySQL ENUM so the
     * database and this enum always allow exactly the same values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }
}

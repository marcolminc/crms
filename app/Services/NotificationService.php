<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\User;

class NotificationService
{
    public function forCustomer(?Customer $customer, string $title, string $message, ?string $link = null, ?string $type = null): void
    {
        $customer?->user?->recordAlert($title, $message, $link, $type);
    }

    public function forUsers(array $userIds, string $title, string $message, ?string $link = null, ?string $type = null): void
    {
        foreach (User::query()->whereIn('id', $userIds)->cursor() as $user) {
            $user->recordAlert($title, $message, $link, $type);
        }
    }

    /**
     * Every staff account that oversees the vehicle fleet. The Admin is
     * deliberately excluded: per design doc §2 the administrator runs the
     * system (users, roles, oversight), not day-to-day fleet operations.
     */
    public function fleetTeam(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::FleetStaff, UserRole::CounterAgent])
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    /**
     * The actors who work reservations and rentals at the desk: counter
     * agents execute them, branch managers approve them (design doc §2).
     */
    public function bookingDesk(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::CounterAgent, UserRole::BranchManager])
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    /**
     * Branch managers carry escalation duty for the hire desk (overdue
     * returns), independent of who handled the rental. The Admin is excluded.
     */
    public function branchManagers(): array
    {
        return User::query()
            ->where('role', UserRole::BranchManager)
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }

    /**
     * Money matters that need a human: the accountant reconciles, the
     * administrator is told because gateway outages are system incidents.
     */
    public function paymentDesk(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::Finance, UserRole::Admin])
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }
}

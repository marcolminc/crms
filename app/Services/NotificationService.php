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
     * Every staff account that oversees the vehicle fleet.
     */
    public function fleetTeam(): array
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin, UserRole::FleetManager, UserRole::RentalStaff])
            ->where('is_active', true)
            ->pluck('id')
            ->all();
    }
}

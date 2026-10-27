<?php

namespace App\Models;

use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    public function customer()
    {
        return $this->hasOne(Customer::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class)->latest();
    }

    /**
     * Back-office staff (design doc §2 actors who administer the system).
     * Customers and Drivers are not staff; the Driver actor works through
     * the trip-log portal (UC24), not the admin screens.
     */
    public function isStaff(): bool
    {
        return $this->role !== null && in_array($this->role, UserRole::staff(), true);
    }

    public function isDriver(): bool
    {
        return $this->role === UserRole::Driver;
    }

    public function isCustomer(): bool
    {
        return $this->role === UserRole::Customer;
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function unreadNotifications()
    {
        return $this->notifications()->whereNull('read_at');
    }

    public function recordAlert(string $title, string $message, ?string $link = null, ?string $type = null): Notification
    {
        return $this->notifications()->create([
            'title' => $title,
            'message' => $message,
            'link' => $link,
            'type' => $type,
        ]);
    }
}

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
        'is_on_duty',
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
            'is_on_duty' => 'boolean',
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
     * UC23/UC24: the rentals this account is driving, newest hire first.
     */
    public function assignedRentals()
    {
        return $this->hasMany(Rental::class, 'driver_id');
    }

    /**
     * UC24: the daily trip logs this driver has recorded.
     */
    public function trips()
    {
        return $this->hasMany(DriverTrip::class, 'driver_id')->latest('trip_date');
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
            // Store path+query only. An absolute URL freezes the host that
            // happened to serve the creating request (localhost vs the ngrok
            // tunnel); clicking it later hops the user onto a different
            // cookie domain, where their session — and role — is not the
            // one they expect. Relative links resolve on whatever host the
            // reader is browsing, keeping them inside their own session.
            'link' => $link === null ? null : (string) parse_url($link, PHP_URL_PATH)
                . (parse_url($link, PHP_URL_QUERY) ? '?'.parse_url($link, PHP_URL_QUERY) : ''),
            'type' => $type,
        ]);
    }
}

<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'vehicle_id',
        'start_date',
        'end_date',
        'estimated_total',
        'status',
        'notes',
        'rejection_reason',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'confirmed_at' => 'datetime',
            'estimated_total' => 'decimal:2',
            'status' => ReservationStatus::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function rental(): HasOne
    {
        return $this->hasOne(Rental::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed]);
    }

    public function days(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * UC07: the customer may change the dates themselves only while the booking
     * is still open (pending or confirmed), has not started yet, and has not
     * been picked up as a rental.
     */
    public function isModifiableByCustomer(): bool
    {
        return in_array($this->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)
            && $this->start_date->gte(Carbon::today())
            && $this->rental === null;
    }

    /**
     * True when another live reservation for the same vehicle covers any requested day.
     */
    public function conflictsWithLiveBookings(): bool
    {
        return static::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->whereKeyNot($this->id)
            ->active()
            ->where('start_date', '<=', $this->end_date)
            ->where('end_date', '>=', $this->start_date)
            ->exists();
    }
}

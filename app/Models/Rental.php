<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Models\Concerns\HasPhotoSlots;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Rental extends Model
{
    use HasFactory, HasPhotoSlots;

    protected $fillable = [
        'reservation_id',
        'customer_id',
        'vehicle_id',
        'staff_id',
        'driver_id',
        'start_date',
        'end_date',
        'days',
        'rate_per_day',
        'subtotal',
        'additional_charges',
        'driver_fee',
        'discount',
        'total_amount',
        'status',
        'return_requested_at',
        'rating',
        'rating_comment',
        'rated_at',
        'pickup_location',
        'dropoff_location',
        'odometer_at_pickup',
        'fuel_at_pickup',
        'pickup_photos',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days' => 'integer',
            'rate_per_day' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'additional_charges' => 'decimal:2',
            'driver_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => RentalStatus::class,
            'return_requested_at' => 'datetime',
            'rating' => 'integer',
            'rated_at' => 'datetime',
            'odometer_at_pickup' => 'integer',
            'pickup_photos' => 'array',
        ];
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function trips(): HasMany
    {
        return $this->hasMany(DriverTrip::class)->latest('trip_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function vehicleReturn(): HasOne
    {
        return $this->hasOne(VehicleReturn::class);
    }

    public function fines(): HasMany
    {
        return $this->hasMany(TrafficFine::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [RentalStatus::Active, RentalStatus::Overdue]);
    }

    /**
     * Only money actually kept counts: a promised payment that has not been
     * collected must not reduce the balance a customer is chased for, and money
     * handed back through a refund must not stay counted as collected.
     */
    public function paidAmount(): float
    {
        $collected = (float) $this->payments()
            ->whereNotNull('paid_at')
            ->where('status', '!=', PaymentStatus::Refunded->value)
            ->where('type', PaymentType::Payment->value)
            ->sum('amount');

        // Refunds against a fully-refunded payment are skipped: that payment has
        // already left the total above, and counting its refund again double-deducts.
        $paidBack = (float) $this->payments()
            ->whereNotNull('paid_at')
            ->where('type', PaymentType::Refund->value)
            ->whereHas('parentPayment', fn ($query) => $query->where('status', '!=', PaymentStatus::Refunded->value))
            ->sum('amount');

        return max(0, $collected - $paidBack);
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total_amount - $this->paidAmount());
    }

    public function isSettled(): bool
    {
        return $this->balanceDue() <= 0;
    }

    /**
     * UC21: a hire can be rated once it has been returned and only if the
     * customer has not already left a rating.
     */
    public function isRateable(): bool
    {
        return $this->status === RentalStatus::Completed && $this->rating === null;
    }

    /**
     * UC09 (customer side): the driver can tell us the car is back while the
     * hire is still open, the desk has not checked it in, and they have not
     * already raised the request.
     */
    public function canRequestReturn(): bool
    {
        return in_array($this->status, [RentalStatus::Active, RentalStatus::Overdue], true)
            && $this->vehicleReturn === null
            && $this->return_requested_at === null;
    }

    /**
     * FR15: the hire is chauffeured once a driver is attached to it.
     */
    public function isChauffured(): bool
    {
        return $this->driver_id !== null;
    }

    /**
     * UC10: whether the same vehicle can be kept for the extra days implied by
     * the proposed drop-off. Only the days beyond the current end are examined,
     * so the rental being extended never blocks itself.
     */
    public function extensionWouldConflict(Carbon $newEnd): bool
    {
        $from = $this->end_date->copy()->addDay()->startOfDay();

        if ($newEnd->lt($from)) {
            return false;
        }

        $reservationClash = Reservation::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->where('start_date', '<=', $newEnd)
            ->where('end_date', '>=', $from)
            ->exists();

        $rentalClash = static::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->whereKeyNot($this->id)
            ->whereIn('status', [RentalStatus::Active, RentalStatus::Overdue])
            ->where('start_date', '<=', $newEnd)
            ->where('end_date', '>=', $from)
            ->exists();

        $maintenanceClash = Maintenance::query()
            ->where('vehicle_id', $this->vehicle_id)
            ->whereIn('status', [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress])
            ->where('start_date', '<=', $newEnd)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->exists();

        return $reservationClash || $rentalClash || $maintenanceClash;
    }

    /**
     * Keeps the stored money columns in step with the rate and period, so a
     * late edit to charges or discount cannot leave the total stale.
     */
    public function recalculate(): void
    {
        $this->subtotal = (float) $this->rate_per_day * $this->days;
        $this->total_amount = max(
            0,
            (float) $this->subtotal
                + (float) $this->additional_charges
                + (float) $this->driver_fee
                - (float) $this->discount
        );
    }
}

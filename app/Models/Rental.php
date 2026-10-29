<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\RentalStatus;
use App\Models\Concerns\HasPhotoSlots;
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
        'start_date',
        'end_date',
        'days',
        'rate_per_day',
        'subtotal',
        'additional_charges',
        'discount',
        'total_amount',
        'status',
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
            'discount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => RentalStatus::class,
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function vehicleReturn(): HasOne
    {
        return $this->hasOne(VehicleReturn::class);
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
     * Keeps the stored money columns in step with the rate and period, so a
     * late edit to charges or discount cannot leave the total stale.
     */
    public function recalculate(): void
    {
        $this->subtotal = (float) $this->rate_per_day * $this->days;
        $this->total_amount = max(
            0,
            (float) $this->subtotal + (float) $this->additional_charges - (float) $this->discount
        );
    }
}

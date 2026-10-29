<?php

namespace App\Models;

use App\Enums\VehicleCondition;
use App\Models\Concerns\HasPhotoSlots;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleReturn extends Model
{
    use HasFactory, HasPhotoSlots;

    protected $fillable = [
        'rental_id',
        'vehicle_id',
        'returned_at',
        'condition',
        'fuel_level',
        'mileage_at_return',
        'damage_notes',
        'photos',
        'additional_charges',
        'processed_by',
    ];

    protected function casts(): array
    {
        return [
            'returned_at' => 'datetime',
            'additional_charges' => 'decimal:2',
            'condition' => VehicleCondition::class,
            'mileage_at_return' => 'integer',
            'photos' => 'array',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function attractsDamageCharge(): bool
    {
        return in_array($this->condition, VehicleCondition::needsCharge(), true);
    }
}

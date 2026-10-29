<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FR15 / UC24: one chauffeur's log for a single working day on a rental - the
 * start and end of the day, the odometer at each bookend, and any incident.
 */
class DriverTrip extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'driver_id',
        'trip_date',
        'started_at',
        'ended_at',
        'odometer_start',
        'odometer_end',
        'incident_notes',
    ];

    protected function casts(): array
    {
        return [
            'trip_date' => 'date',
            'odometer_start' => 'integer',
            'odometer_end' => 'integer',
        ];
    }

    public function rental(): BelongsTo
    {
        return $this->belongsTo(Rental::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    /**
     * Kilometres covered on the day, or null when a bookend is missing.
     */
    public function distanceCovered(): ?int
    {
        if ($this->odometer_start === null || $this->odometer_end === null) {
            return null;
        }

        return max(0, $this->odometer_end - $this->odometer_start);
    }
}

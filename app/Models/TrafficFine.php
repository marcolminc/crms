<?php

namespace App\Models;

use App\Enums\FineLiability;
use App\Enums\FineStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrafficFine extends Model
{
    use HasFactory;

    protected $fillable = [
        'rental_id',
        'vehicle_id',
        'reference',
        'description',
        'amount',
        'issued_at',
        'liability',
        'status',
        'recorded_by',
        'decided_by',
        'decided_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'issued_at' => 'date',
            'liability' => FineLiability::class,
            'status' => FineStatus::class,
            'decided_at' => 'datetime',
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

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === FineStatus::Pending;
    }
}

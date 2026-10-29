<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use App\Models\Concerns\HasPhotoSlots;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;
    use HasPhotoSlots {
        gallery as traitGallery;
    }

    protected $fillable = [
        'plate_number',
        'make',
        'model',
        'year',
        'type',
        'seats',
        'transmission',
        'fuel_type',
        'colour',
        'mileage',
        'rate_per_day',
        'status',
        'description',
        'features',
        'image_path',
        'images',
    ];

    protected function casts(): array
    {
        return [
            'type' => VehicleType::class,
            'status' => VehicleStatus::class,
            'rate_per_day' => 'decimal:2',
            'features' => 'array',
            'images' => 'array',
            'year' => 'integer',
            'seats' => 'integer',
            'mileage' => 'integer',
        ];
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function rentals(): HasMany
    {
        return $this->hasMany(Rental::class);
    }

    public function maintenances(): HasMany
    {
        return $this->hasMany(Maintenance::class);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        return $query->when($term, function (Builder $q) use ($term) {
            $like = '%'.$term.'%';
            $q->where(function (Builder $inner) use ($like) {
                $inner->where('plate_number', 'like', $like)
                    ->orWhere('make', 'like', $like)
                    ->orWhere('model', 'like', $like);
            });
        });
    }

    /**
     * Vehicles a customer may book for the whole of the given period.
     */
    public function scopeAvailableBetween(Builder $query, $from, $to): Builder
    {
        return $query->where('status', VehicleStatus::Available)
            ->whereDoesntHave('reservations', function (Builder $q) use ($from, $to) {
                $q->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
                    ->where('start_date', '<=', $to)
                    ->where('end_date', '>=', $from);
            })
            ->whereDoesntHave('rentals', function (Builder $q) use ($from, $to) {
                $q->whereIn('status', [RentalStatus::Active, RentalStatus::Overdue])
                    ->where('start_date', '<=', $to)
                    ->where('end_date', '>=', $from);
            })
            ->whereDoesntHave('maintenances', function (Builder $q) use ($from, $to) {
                $q->whereIn('status', ['scheduled', 'in_progress'])
                    ->where('start_date', '<=', $to)
                    ->where(function (Builder $inner) use ($from) {
                        $inner->whereNull('end_date')->orWhere('end_date', '>=', $from);
                    });
            });
    }

    public function isFreeBetween($from, $to): bool
    {
        return static::query()->whereKey($this->id)->availableBetween($from, $to)->exists();
    }

    public function getDisplayNameAttribute(): string
    {
        return trim("{$this->make} {$this->model} {$this->plate_number}");
    }

    /**
     * Ordered slides for the carousel; a lone legacy image_path still works
     * as an uncaptioned fallback when the slot gallery is empty.
     *
     * @return list<array{slot:?string,label:string,path:string}>
     */
    public function gallery(string $column = 'images'): array
    {
        $rows = $this->traitGallery($column);

        if ($rows === [] && $column === 'images' && is_string($this->image_path) && $this->image_path !== '') {
            $rows[] = ['slot' => null, 'label' => '', 'path' => $this->image_path];
        }

        return $rows;
    }
}

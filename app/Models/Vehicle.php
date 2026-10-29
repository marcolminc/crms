<?php

namespace App\Models;

use App\Enums\RentalStatus;
use App\Enums\ReservationStatus;
use App\Enums\VehicleStatus;
use App\Enums\VehicleType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Named gallery slots in display order. One photo per slot keeps the
     * catalogue coherent and tells the carousel what angle it is showing.
     *
     * @var array<string,string>
     */
    public const SLOTS = [
        'front' => 'Front',
        'side' => 'Side',
        'back' => 'Back',
        'interior' => 'Interior',
        'extra' => 'Extra angle',
    ];

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
     * Gallery as slot => path on the public disk. The images column holds a
     * keyed map; a legacy plain list is re-keyed onto the slots in order so
     * older rows stay manageable, and a lone image_path still works as a
     * slot-less fallback.
     *
     * @return array<string,string>
     */
    public function photoMap(): array
    {
        $images = is_array($this->images) ? $this->images : [];

        if (array_is_list($images)) {
            $legacy = [];
            foreach ($images as $index => $path) {
                if (is_string($path) && $path !== '') {
                    $legacy[$index] = $path;
                }
            }
            $images = [];
            foreach (array_keys(self::SLOTS) as $slot) {
                $path = array_shift($legacy);
                if ($path !== null) {
                    $images[$slot] = $path;
                }
            }
        } else {
            $ordered = [];
            foreach (array_keys(self::SLOTS) as $slot) {
                $path = $images[$slot] ?? null;
                if (is_string($path) && $path !== '') {
                    $ordered[$slot] = $path;
                }
            }
            $images = $ordered;
        }

        return $images;
    }

    /**
     * Ordered slides for the carousel.
     *
     * @return list<array{slot:?string,label:string,path:string}>
     */
    public function gallery(): array
    {
        $rows = [];
        foreach ($this->photoMap() as $slot => $path) {
            $rows[] = ['slot' => $slot, 'label' => self::SLOTS[$slot], 'path' => $path];
        }

        if ($rows === [] && is_string($this->image_path) && $this->image_path !== '') {
            $rows[] = ['slot' => null, 'label' => '', 'path' => $this->image_path];
        }

        return $rows;
    }

    public function hasPhotos(): bool
    {
        return $this->gallery() !== [];
    }

    /**
     * Public URL for a gallery path or web URI stored by the legacy text field.
     */
    public function photoUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return str_starts_with($path, 'storage/') ? asset($path) : Storage::disk('public')->url($path);
    }
}

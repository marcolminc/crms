<?php

namespace App\Models\Concerns;

/**
 * Named photo slots (front, side, back, interior, extra) stored as a
 * slot => path JSON map on the public disk. Shared by the vehicle gallery
 * and the handover evidence attached to rentals and returns, so every
 * image in the system knows which angle it shows.
 */
trait HasPhotoSlots
{
    /**
     * Gallery slots in display order. One photo per slot keeps the
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

    /**
     * Gallery as slot => path on the public disk. The column holds a keyed
     * map; a legacy plain list is re-keyed onto the slots in order so older
     * rows stay manageable.
     *
     * @return array<string,string>
     */
    public function photoMap(string $column = 'images'): array
    {
        $images = is_array($this->{$column}) ? $this->{$column} : [];

        if (array_is_list($images)) {
            $legacy = [];
            foreach ($images as $path) {
                if (is_string($path) && $path !== '') {
                    $legacy[] = $path;
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
     * Ordered slides for a carousel or photo grid.
     *
     * @return list<array{slot:?string,label:string,path:string}>
     */
    public function gallery(string $column = 'images'): array
    {
        $rows = [];
        foreach ($this->photoMap($column) as $slot => $path) {
            $rows[] = ['slot' => $slot, 'label' => self::SLOTS[$slot], 'path' => $path];
        }

        return $rows;
    }

    public function hasPhotos(string $column = 'images'): bool
    {
        return $this->gallery($column) !== [];
    }

    /**
     * Public URL for a gallery path or web URI. Built from the current
     * request (not APP_URL) so the same page works over localhost, the
     * tunnel or the future production domain.
     */
    public function photoUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return asset('storage/'.$path);
    }
}

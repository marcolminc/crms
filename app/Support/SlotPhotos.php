<?php

namespace App\Support;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Moves uploaded named-slot photos off the request and onto the public
 * disk, returning the slot => path map the gallery columns store.
 */
final class SlotPhotos
{
    /**
     * @return array<string,string> slot => stored path (only filled slots)
     */
    public static function store(Request $request, string $inputKey, string $directory): array
    {
        $paths = [];

        foreach (array_keys(Vehicle::SLOTS) as $slot) {
            $file = $request->file("$inputKey.$slot");
            if ($file instanceof UploadedFile) {
                $paths[$slot] = $file->store($directory, 'public');
            }
        }

        return $paths;
    }
}

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A vehicle now carries a photo gallery (front, back, side, interior shots)
     * rather than the single legacy image_path. Paths are relative to the
     * public disk, e.g. "vehicles/ab12cd.jpg".
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A vehicle now carries a photo gallery rather than the single legacy
     * image_path. The JSON holds a map of named slot => path on the public
     * disk, e.g. {"front": "vehicles/ab12cd.jpg", "interior": ...}, so the
     * database always knows which angle each photo shows.
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

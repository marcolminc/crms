<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Handover evidence: the odometer reading, fuel level and angle photos
     * taken when the customer drives away, plus inspection photos recorded
     * with the return. Photo columns hold the same named-slot JSON map the
     * vehicle gallery uses, e.g. {"front": "rentals/ab12.jpg"}.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->unsignedInteger('odometer_at_pickup')->nullable()->after('dropoff_location');
            $table->string('fuel_at_pickup', 20)->nullable()->after('odometer_at_pickup');
            $table->json('pickup_photos')->nullable()->after('fuel_at_pickup');
        });

        Schema::table('vehicle_returns', function (Blueprint $table) {
            $table->json('photos')->nullable()->after('damage_notes');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn(['odometer_at_pickup', 'fuel_at_pickup', 'pickup_photos']);
        });

        Schema::table('vehicle_returns', function (Blueprint $table) {
            $table->dropColumn('photos');
        });
    }
};

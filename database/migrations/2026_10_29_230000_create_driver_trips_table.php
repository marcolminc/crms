<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR15 / UC24: a chauffeur logs one row per working day against the rental
     * they are driving - the start and end of the day, the odometer at each
     * bookend, and any incident on the day. This is the "track daily trips"
     * record the design document asks for.
     */
    public function up(): void
    {
        Schema::create('driver_trips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('users')->restrictOnDelete();
            $table->date('trip_date');
            $table->time('started_at')->nullable();
            $table->time('ended_at')->nullable();
            $table->unsignedBigInteger('odometer_start')->nullable();
            $table->unsignedBigInteger('odometer_end')->nullable();
            $table->text('incident_notes')->nullable();
            $table->timestamps();

            $table->index(['driver_id', 'trip_date']);
            $table->index(['rental_id', 'trip_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_trips');
    }
};

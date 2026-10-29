<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FR15: an office driver (chauffeur) is an optional add-on chosen at
     * booking time. The flag rides along to the quote so the desk prices the
     * hire before pick-up; the driver themselves is assigned to the rental
     * later (UC23) once a real, available chauffeur is known.
     */
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->boolean('needs_driver')->default(false)->after('estimated_total');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn('needs_driver');
        });
    }
};

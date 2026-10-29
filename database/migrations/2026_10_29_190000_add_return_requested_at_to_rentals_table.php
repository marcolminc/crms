<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UC09 (customer side): the "I've dropped the car off" signal. It is a
     * request, not the return itself - the desk still performs the inspection
     * and records mileage/fuel/condition. The timestamp lets the desk see a
     * hire that the customer believes is back but has not yet been checked in.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->timestamp('return_requested_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn('return_requested_at');
        });
    }
};

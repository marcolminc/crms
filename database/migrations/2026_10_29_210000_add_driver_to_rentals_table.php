<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * UC23: a chauffeur is assigned to a rental (not a reservation) because the
     * right driver is only chosen once the car actually goes out. driver_fee is
     * the priced chauffeur charge for the hire, folded into the rental total.
     */
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()
                ->after('staff_id')->constrained('users')->nullOnDelete();
            $table->decimal('driver_fee', 12, 2)->default(0)->after('additional_charges');
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropColumn('driver_fee');
        });
    }
};

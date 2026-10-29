<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UC25: traffic fines raised against a rental. At return inspection an
        // agent/manager/finance user lists each fine, then decides liability -
        // bill it to the customer (added to the rental) or absorb it (company
        // bears the cost). A fine starts unassigned and pending.
        Schema::create('traffic_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('issued_at')->nullable();
            $table->string('liability', 16)->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('rental_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traffic_fines');
    }
};

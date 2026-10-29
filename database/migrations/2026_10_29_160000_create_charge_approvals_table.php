<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // UC14: manager sign-off over material discounts and damage/additional
        // charges. The charge itself is already applied to the rental; this row
        // records that a manager must review it, who asked, and the decision.
        Schema::create('charge_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->string('status', 16)->default('pending');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['rental_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('charge_approvals');
    }
};

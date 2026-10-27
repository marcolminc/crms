<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained()->cascadeOnDelete();
            $table->string('tx_ref', 64)->unique();
            $table->string('checkout_url', 500)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('TZS');
            $table->string('payment_method', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->string('flw_tx_id', 64)->nullable();
            $table->string('flw_ref', 64)->nullable()->unique();
            $table->foreignId('payment_id')->nullable()->unique()->constrained('payments')->nullOnDelete();
            $table->string('failure_reason', 40)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['rental_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_intents');
    }
};

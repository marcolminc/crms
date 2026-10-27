<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Design §8.3 (5.3): every gateway callback is kept verbatim before we act on
 * it. Money decisions never come from this table — it exists so operations can
 * replay exactly what AzamPay told us when a settlement goes sideways.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateway_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_intent_id')->nullable()
                ->constrained('payment_intents')->nullOnDelete();
            $table->string('gateway_ref', 64)->nullable();
            $table->string('event_type', 40);
            $table->json('raw_payload');
            $table->boolean('signature_valid')->default(false);
            $table->boolean('processed')->default(false);
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['gateway_ref', 'event_type']);
            $table->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_events');
    }
};

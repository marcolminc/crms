<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The payment_intents columns carried their provider's name. With AzamPay taking
     * over the gateway role they become provider-neutral in place, so any checkout
     * already opened keeps its ledger links through the move.
     */
    public function up(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->renameColumn('flw_tx_id', 'gateway_tx_id');
            $table->renameColumn('flw_ref', 'gateway_ref');
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table) {
            $table->renameColumn('gateway_tx_id', 'flw_tx_id');
            $table->renameColumn('gateway_ref', 'flw_ref');
        });
    }
};

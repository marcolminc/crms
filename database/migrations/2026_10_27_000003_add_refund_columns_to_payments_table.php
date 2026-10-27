<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ledger becomes two-directional (design §8.3): every row is typed, and a
 * refund points back at the payment it returns money from. Original rows keep
 * their amounts — history is never edited, only appended to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('type', 30)->default('payment')->after('receipt_number')->index();
            $table->foreignId('parent_payment_id')->nullable()->after('type')
                ->constrained('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_payment_id');
            $table->dropColumn('type');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->unsignedSmallInteger('reconciliation_attempts')->default(0)->after('provider_payload');
            $table->timestamp('last_reconciled_at')->nullable()->after('reconciliation_attempts');
            $table->timestamp('next_reconciliation_at')->nullable()->after('last_reconciled_at');
            $table->text('reconciliation_error')->nullable()->after('next_reconciliation_at');
            $table->index(['status', 'next_reconciliation_at'], 'payment_attempts_reconciliation_due_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->dropIndex('payment_attempts_reconciliation_due_index');
            $table->dropColumn([
                'reconciliation_attempts',
                'last_reconciled_at',
                'next_reconciliation_at',
                'reconciliation_error',
            ]);
        });
    }
};

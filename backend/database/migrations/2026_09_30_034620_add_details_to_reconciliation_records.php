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
        Schema::table('reconciliation_batches', function (Blueprint $table) {
            $table->string('source', 24)->default('manual')->after('status');
            $table->string('provider', 32)->nullable()->after('source');
            $table->json('summary')->nullable()->after('provider');
            $table->timestamp('started_at')->nullable()->after('summary');
            $table->timestamp('completed_at')->nullable()->after('started_at');
            $table->index(['source', 'date']);
        });

        Schema::table('reconciliation_entries', function (Blueprint $table) {
            $table->string('provider_status', 24)->nullable()->after('status');
            $table->string('internal_status', 24)->nullable()->after('provider_status');
            $table->unsignedBigInteger('provider_fee')->nullable()->after('internal_status');
            $table->unsignedBigInteger('internal_fee')->nullable()->after('provider_fee');
            $table->string('settlement_reference')->nullable()->after('internal_fee');
            $table->json('discrepancy_types')->nullable()->after('settlement_reference');
            $table->json('provider_payload')->nullable()->after('discrepancy_types');
            $table->index(['status', 'created_at']);
            $table->index(['batch_id', 'payment_gateway_reference'], 'reconciliation_entry_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reconciliation_batches', function (Blueprint $table) {
            $table->dropIndex(['source', 'date']);
            $table->dropColumn(['source', 'provider', 'summary', 'started_at', 'completed_at']);
        });

        Schema::table('reconciliation_entries', function (Blueprint $table) {
            $table->dropIndex(['status', 'created_at']);
            $table->dropIndex('reconciliation_entry_lookup_index');
            $table->dropColumn([
                'provider_status',
                'internal_status',
                'provider_fee',
                'internal_fee',
                'settlement_reference',
                'discrepancy_types',
                'provider_payload',
            ]);
        });
    }
};

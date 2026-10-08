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
        Schema::table('payout_batches', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->after('provider');
            $table->json('provider_payload')->nullable()->after('provider_reference');
            $table->text('failure_reason')->nullable()->after('provider_payload');
            $table->timestamp('processed_at')->nullable()->after('notes');
            $table->timestamp('completed_at')->nullable()->after('processed_at');
        });

        Schema::table('payout_items', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payout_items', function (Blueprint $table) {
            $table->dropColumn('provider_reference');
        });

        Schema::table('payout_batches', function (Blueprint $table) {
            $table->dropColumn([
                'provider_reference',
                'provider_payload',
                'failure_reason',
                'processed_at',
                'completed_at',
            ]);
        });
    }
};

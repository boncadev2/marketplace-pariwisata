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
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->unique()->after('decision_notes');
            $table->json('provider_payload')->nullable()->after('provider_reference');
            $table->timestamp('processed_at')->nullable()->after('provider_payload');
            $table->text('failure_reason')->nullable()->after('processed_at');
            $table->unique('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropUnique(['order_id']);
            $table->dropUnique(['provider_reference']);
            $table->dropColumn(['provider_reference', 'provider_payload', 'processed_at', 'failure_reason']);
        });
    }
};

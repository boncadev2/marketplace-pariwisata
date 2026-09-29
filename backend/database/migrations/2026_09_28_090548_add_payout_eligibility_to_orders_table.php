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
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('service_completed_at')->nullable();
            $table->timestamp('dispute_until')->nullable();
            $table->boolean('has_dispute')->default(false);
            $table->string('payout_status', 32)->default('pending'); // pending, eligible, requested, paid
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['service_completed_at', 'dispute_until', 'has_dispute', 'payout_status']);
        });
    }
};

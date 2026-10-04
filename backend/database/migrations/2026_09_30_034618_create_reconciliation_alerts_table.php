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
        Schema::create('reconciliation_alerts', function (Blueprint $table) {
            $table->id();
            $table->char('fingerprint', 64)->unique();
            $table->string('type', 48);
            $table->string('severity', 16)->default('high');
            $table->string('status', 16)->default('open');
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_attempt_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('refund_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payout_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_hold_id')->nullable()->constrained()->nullOnDelete();
            $table->json('details')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'detected_at']);
            $table->index(['type', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_alerts');
    }
};

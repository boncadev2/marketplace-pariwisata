<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_payments', fn (Blueprint $table) => $table->string('provider', 30)->default('midtrans_sandbox'));
        Schema::create('reservation_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_payment_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255);
            $table->string('status', 30)->default('requested')->index();
            $table->unsignedBigInteger('refundable_amount');
            $table->string('decision_notes', 255)->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->string('provider_reference')->nullable();
            $table->json('provider_payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_refunds');
        Schema::table('reservation_payments', fn (Blueprint $table) => $table->dropColumn('provider'));
    }
};

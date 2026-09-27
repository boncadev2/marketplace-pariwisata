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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 128)->unique();
            $table->string('guest_access_hash');
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('status', 24)->default('pending_payment');
            $table->string('currency', 3)->default('IDR');
            $table->unsignedBigInteger('total');
            $table->json('policy_snapshot');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

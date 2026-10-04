<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umkm_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('umkm_product_id')->constrained()->restrictOnDelete();
            $table->string('idempotency_key', 100);
            $table->string('payload_hash', 64);
            $table->json('snapshot');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('total');
            $table->string('customer_name', 120);
            $table->string('customer_phone', 30);
            $table->string('notes', 500)->nullable();
            $table->string('status', 24)->default('reserved_sandbox');
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umkm_orders');
    }
};

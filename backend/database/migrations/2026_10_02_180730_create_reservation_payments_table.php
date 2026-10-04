<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 16);
            $table->unsignedBigInteger('booking_id');
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('created');
            $table->text('checkout_url')->nullable();
            $table->text('snap_token')->nullable();
            $table->string('provider_status', 32)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'booking_id']);
            $table->index(['status', 'last_checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_payments');
    }
};

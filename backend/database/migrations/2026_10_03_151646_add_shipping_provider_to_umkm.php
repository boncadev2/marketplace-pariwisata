<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umkm_products', function (Blueprint $table) {
            $table->string('origin_postal_code', 5)->nullable();
            $table->unsignedInteger('weight_grams')->nullable();
        });
        Schema::create('shipping_quotes', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('umkm_product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('postal_code', 5);
            $table->string('product_revision', 64);
            $table->string('courier', 40);
            $table->string('service', 80);
            $table->unsignedInteger('fee');
            $table->string('duration', 120)->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
        Schema::table('umkm_orders', function (Blueprint $table) {
            $table->string('shipping_provider', 30)->nullable();
            $table->string('shipping_service', 80)->nullable();
            $table->string('provider_tracking_id', 100)->nullable();
            $table->json('tracking_snapshot')->nullable();
            $table->timestamp('tracking_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('umkm_orders', fn (Blueprint $table) => $table->dropColumn(['shipping_provider', 'shipping_service', 'provider_tracking_id', 'tracking_snapshot', 'tracking_checked_at']));
        Schema::dropIfExists('shipping_quotes');
        Schema::table('umkm_products', fn (Blueprint $table) => $table->dropColumn(['origin_postal_code', 'weight_grams']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umkm_products', function (Blueprint $table): void {
            $table->boolean('delivery_available')->default(false);
            $table->unsignedInteger('shipping_fee')->default(0);
        });
        Schema::table('umkm_orders', function (Blueprint $table): void {
            $table->string('fulfillment', 16)->default('pickup');
            $table->unsignedInteger('shipping_fee')->default(0);
            $table->text('shipping_address')->nullable();
            $table->string('postal_code', 5)->nullable();
            $table->string('carrier', 80)->nullable();
            $table->string('tracking_number', 100)->nullable();
            $table->timestamp('shipped_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('umkm_products', fn (Blueprint $table) => $table->dropColumn(['delivery_available', 'shipping_fee']));
        Schema::table('umkm_orders', fn (Blueprint $table) => $table->dropColumn(['fulfillment', 'shipping_fee', 'shipping_address', 'postal_code', 'carrier', 'tracking_number', 'shipped_at']));
    }
};

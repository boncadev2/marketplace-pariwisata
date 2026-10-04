<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('culinary_places', function (Blueprint $table): void {
            $table->boolean('is_active')->default(false);
        });
        Schema::table('meal_slots', function (Blueprint $table): void {
            $table->unsignedInteger('reserved')->default(0);
            $table->boolean('is_active')->default(false);
            $table->index(['culinary_place_id', 'time_slot']);
        });
        Schema::create('meal_bookings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('meal_slot_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->string('package_name');
            $table->dateTime('time_slot');
            $table->string('status');
            $table->string('idempotency_key', 100);
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_bookings');
        Schema::table('meal_slots', function (Blueprint $table): void {
            $table->dropIndex(['culinary_place_id', 'time_slot']);
            $table->dropColumn(['reserved', 'is_active']);
        });
        Schema::table('culinary_places', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};

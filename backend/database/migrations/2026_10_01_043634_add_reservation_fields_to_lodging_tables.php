<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            $table->boolean('is_active')->default(false);
        });
        Schema::table('room_inventories', function (Blueprint $table): void {
            $table->unique(['room_type_id', 'date']);
        });
        Schema::table('room_rates', function (Blueprint $table): void {
            $table->unique(['room_type_id', 'date']);
        });
        Schema::table('lodging_bookings', function (Blueprint $table): void {
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('guests')->default(1);
            $table->string('idempotency_key', 100)->nullable();
            $table->json('nightly_prices')->nullable();
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('lodging_bookings', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'idempotency_key']);
            $table->dropColumn(['quantity', 'guests', 'idempotency_key', 'nightly_prices']);
        });
        Schema::table('room_rates', fn (Blueprint $table) => $table->dropUnique(['room_type_id', 'date']));
        Schema::table('room_inventories', fn (Blueprint $table) => $table->dropUnique(['room_type_id', 'date']));
        Schema::table('room_types', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['lodging_bookings', 'meal_bookings'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->timestamp('confirmed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['lodging_bookings', 'meal_bookings'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('confirmed_at'));
        }
    }
};

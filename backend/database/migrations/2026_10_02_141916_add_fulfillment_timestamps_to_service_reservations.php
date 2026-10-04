<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lodging_bookings', function (Blueprint $table): void {
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
        Schema::table('meal_bookings', fn (Blueprint $table) => $table->timestamp('completed_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('lodging_bookings', fn (Blueprint $table) => $table->dropColumn(['checked_in_at', 'completed_at']));
        Schema::table('meal_bookings', fn (Blueprint $table) => $table->dropColumn('completed_at'));
    }
};

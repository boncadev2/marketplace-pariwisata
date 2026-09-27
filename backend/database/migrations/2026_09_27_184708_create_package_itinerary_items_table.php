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
        Schema::create('package_itinerary_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->unsignedSmallInteger('sequence');
            $table->string('title');
            $table->time('starts_at');
            $table->unsignedSmallInteger('duration_minutes');
            $table->text('description')->nullable();
            $table->unique(['tour_package_id', 'day_number', 'sequence'], 'itinerary_package_day_sequence_unique');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_itinerary_items');
    }
};

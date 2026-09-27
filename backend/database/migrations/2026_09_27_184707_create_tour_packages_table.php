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
        Schema::create('tour_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('departure_type', 16)->default('fixed');
            $table->unsignedSmallInteger('duration_days');
            $table->string('meeting_point');
            $table->text('transportation')->nullable();
            $table->text('guide_information')->nullable();
            $table->json('inclusions')->nullable();
            $table->json('exclusions')->nullable();
            $table->string('pricing_mode', 16);
            $table->unsignedInteger('minimum_participants');
            $table->unsignedInteger('maximum_participants');
            $table->unsignedSmallInteger('booking_cutoff_hours')->default(24);
            $table->string('status', 16)->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tour_packages');
    }
};

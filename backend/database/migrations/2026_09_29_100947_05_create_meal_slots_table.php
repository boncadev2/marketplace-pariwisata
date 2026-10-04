<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('culinary_place_id')->constrained()->cascadeOnDelete();
            $table->dateTime('time_slot');
            $table->string('package_name');
            $table->decimal('price', 15, 2);
            $table->integer('capacity')->default(10);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meal_slots');
    }
};

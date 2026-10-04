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
        Schema::table('room_types', function (Blueprint $table) {
            $table->string('exterior_image_url', 1000)->nullable();
            $table->string('interior_image_url', 1000)->nullable();
            $table->boolean('photos_are_illustrations')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn(['exterior_image_url', 'interior_image_url', 'photos_are_illustrations']);
        });
    }
};

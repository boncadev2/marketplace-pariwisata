<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            $table->string('exterior_photo_path')->nullable();
            $table->string('interior_photo_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            $table->dropColumn(['exterior_photo_path', 'interior_photo_path']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            $table->string('location', 500)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('location_is_demo')->default(false);
        });
        Schema::table('culinary_places', function (Blueprint $table): void {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('location_is_demo')->default(false);
            $table->string('image_url', 1000)->nullable();
            $table->boolean('photos_are_illustrations')->default(false);
        });
        DB::table('culinary_places')->where('name', 'Dapur Demonstrasi — bukan penawaran nyata')->where('location', 'Desa Demonstrasi')->update(['location_is_demo' => true]);
    }

    public function down(): void
    {
        Schema::table('room_types', fn (Blueprint $table) => $table->dropColumn(['location', 'latitude', 'longitude', 'location_is_demo']));
        Schema::table('culinary_places', fn (Blueprint $table) => $table->dropColumn(['latitude', 'longitude', 'location_is_demo', 'image_url', 'photos_are_illustrations']));
    }
};

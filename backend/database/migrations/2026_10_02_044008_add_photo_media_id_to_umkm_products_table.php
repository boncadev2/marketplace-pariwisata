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
        Schema::table('umkm_products', function (Blueprint $table) {
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('umkm_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('photo_media_id');
        });
    }
};

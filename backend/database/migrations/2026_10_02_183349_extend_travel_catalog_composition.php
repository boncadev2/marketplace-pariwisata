<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table): void {
            $table->string('address')->nullable();
        });
        Schema::table('tour_packages', function (Blueprint $table): void {
            $table->text('description')->nullable();
        });
        Schema::table('package_itinerary_items', function (Blueprint $table): void {
            $table->foreignId('culinary_place_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('umkm_product_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('included')->default(true);
            $table->unsignedBigInteger('additional_cost')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('package_itinerary_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('culinary_place_id');
            $table->dropConstrainedForeignId('umkm_product_id');
            $table->dropColumn(['quantity', 'included', 'additional_cost']);
        });
        Schema::table('tour_packages', fn (Blueprint $table) => $table->dropColumn('description'));
        Schema::table('destinations', fn (Blueprint $table) => $table->dropColumn('address'));
    }
};

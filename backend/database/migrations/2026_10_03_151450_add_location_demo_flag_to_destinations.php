<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', fn (Blueprint $table) => $table->boolean('location_is_demo')->default(false));
        DB::table('destinations')->where('slug', 'like', 'destinasi-demo-%')->orWhere('slug', 'destinasi-pilot-demo')->orWhere('name', 'like', '% — Demo')->update(['location_is_demo' => true]);
    }

    public function down(): void
    {
        Schema::table('destinations', fn (Blueprint $table) => $table->dropColumn('location_is_demo'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('image_url', 1000)->nullable()->after('slug');
            $table->string('category', 60)->nullable()->after('image_url');
            $table->string('author_name', 100)->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['image_url', 'category', 'author_name']);
        });
    }
};

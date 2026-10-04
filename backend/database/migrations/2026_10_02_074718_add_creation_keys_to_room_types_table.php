<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            $table->string('creation_key', 64)->nullable()->unique();
            $table->string('creation_fingerprint', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('room_types', function (Blueprint $table): void {
            $table->dropUnique(['creation_key']);
            $table->dropColumn(['creation_key', 'creation_fingerprint']);
        });
    }
};

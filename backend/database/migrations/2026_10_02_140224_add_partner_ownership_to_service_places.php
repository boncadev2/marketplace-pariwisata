<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['room_types', 'culinary_places'] as $table) {
            Schema::table($table, function (Blueprint $table): void {
                $table->foreignId('partner_id')->nullable()->constrained()->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['room_types', 'culinary_places'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropConstrainedForeignId('partner_id'));
        }
    }
};

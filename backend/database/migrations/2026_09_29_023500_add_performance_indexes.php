<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'orders_status_created_at_index');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->index(['publication_status', 'name'], 'destinations_publication_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_status_created_at_index');
        });

        Schema::table('destinations', function (Blueprint $table) {
            $table->dropIndex('destinations_publication_name_index');
        });
    }
};

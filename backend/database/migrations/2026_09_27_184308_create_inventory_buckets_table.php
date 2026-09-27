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
        Schema::create('inventory_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->date('service_date');
            $table->string('session_key', 64)->default('default');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('held')->default(0);
            $table->unsignedInteger('confirmed')->default(0);
            $table->boolean('is_closed')->default(false);
            $table->unique(['product_id', 'service_date', 'session_key']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_buckets');
    }
};

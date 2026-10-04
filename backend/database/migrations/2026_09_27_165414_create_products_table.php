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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 32);
            $table->string('currency', 3)->default('IDR');
            $table->unsignedBigInteger('base_price')->default(0);
            $table->string('status', 24)->default('draft');
            $table->softDeletes();
            $table->index(['partner_id', 'type', 'status']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umkm_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description');
            $table->string('location');
            $table->unsignedBigInteger('price');
            $table->string('unit', 50)->default('pcs');
            $table->string('status', 24)->default('draft');
            $table->boolean('is_demo')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umkm_products');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('discount_type'); // 'percentage', 'fixed'
            $table->decimal('discount_value', 15, 2);
            $table->decimal('minimum_spend', 15, 2)->default(0);
            $table->decimal('maximum_discount', 15, 2)->nullable();
            $table->integer('global_quota')->nullable();
            $table->integer('used_quota')->default(0);
            $table->integer('user_quota')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};

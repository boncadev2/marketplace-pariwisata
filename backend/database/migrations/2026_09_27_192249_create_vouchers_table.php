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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_item_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->text('token');
            $table->date('service_date');
            $table->unsignedInteger('admissions');
            $table->unsignedInteger('used_admissions')->default(0);
            $table->string('status', 16)->default('active');
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};

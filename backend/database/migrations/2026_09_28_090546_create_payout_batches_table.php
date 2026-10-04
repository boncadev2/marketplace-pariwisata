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
        Schema::create('payout_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number')->unique();
            $table->foreignId('maker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('checker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider'); // manual, bank_transfer, api
            $table->string('status', 32)->default('requested'); // requested, approved, processing, paid, failed
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payout_batches');
    }
};

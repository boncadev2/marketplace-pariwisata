<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('reconciliation_batches')->cascadeOnDelete();
            $table->string('payment_gateway_reference');
            $table->unsignedBigInteger('amount');
            $table->foreignId('internal_payment_attempt_id')->nullable()->constrained('payment_attempts')->nullOnDelete();
            $table->string('status'); // matched, mismatched, not_found
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_entries');
    }
};

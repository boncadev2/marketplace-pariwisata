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
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type'); // asset, liability, equity, revenue, expense
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_type')->nullable(); // Order, RefundRequest, PaymentWebhookEvent
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('journal_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('ledger_account_id')->constrained('ledger_accounts')->restrictOnDelete();
            $table->string('type', 10); // 'debit' or 'credit'
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journal_transactions');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('ledger_accounts');
    }
};

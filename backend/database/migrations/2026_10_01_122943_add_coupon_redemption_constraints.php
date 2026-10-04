<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            $table->unique('order_id');
            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('coupon_redemptions', function (Blueprint $table): void {
            $table->dropUnique(['order_id']);
            $table->dropIndex(['coupon_id', 'user_id']);
        });
    }
};

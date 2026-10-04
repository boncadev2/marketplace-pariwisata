<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umkm_products', function (Blueprint $table): void {
            $table->unsignedInteger('stock')->default(0);
        });
        if (app()->environment(['local', 'testing'])) {
            DB::table('umkm_products')->where('is_demo', true)->whereIn('slug', ['kopi-lokal-demo', 'tas-anyaman-demo', 'keripik-pisang-demo'])->update(['stock' => 20]);
        }
    }

    public function down(): void
    {
        Schema::table('umkm_products', function (Blueprint $table): void {
            $table->dropColumn('stock');
        });
    }
};

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
        Schema::create('package_departures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->date('local_date');
            $table->string('timezone', 64);
            $table->timestamp('cutoff_at');
            $table->unsignedInteger('capacity');
            $table->unsignedInteger('confirmed')->default(0);
            $table->string('status', 16)->default('open');
            $table->timestamp('guaranteed_at')->nullable();
            $table->string('guide_assignment')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->unique(['tour_package_id', 'local_date']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_departures');
    }
};

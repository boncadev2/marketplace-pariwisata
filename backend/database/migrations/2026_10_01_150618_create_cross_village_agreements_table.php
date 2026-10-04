<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_packages', function (Blueprint $table): void {
            $table->unsignedInteger('cross_village_revision')->default(0);
        });
        Schema::create('cross_village_agreements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tour_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('partner_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->string('configuration_version', 64);
            $table->string('decision', 16);
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['tour_package_id', 'revision', 'partner_id'], 'cross_village_agreement_revision_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cross_village_agreements');
        Schema::table('tour_packages', function (Blueprint $table): void {
            $table->dropColumn('cross_village_revision');
        });
    }
};

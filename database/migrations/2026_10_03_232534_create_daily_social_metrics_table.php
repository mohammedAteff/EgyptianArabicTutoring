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
        Schema::create('daily_social_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('reporting_timezone', 64);
            $table->date('metric_date');
            $table->string('dimension_hash', 64);
            $table->json('dimensions')->nullable();
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->timestamps();
            $table->unique(['reporting_timezone', 'metric_date', 'dimension_hash'], 'daily_social_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_social_metrics');
    }
};

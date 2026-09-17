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
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('weekday'); // 0=Sunday, 1=Monday, ..., 6=Saturday
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('session_duration_minutes')->nullable();
            $table->unsignedInteger('buffer_minutes')->nullable();
            $table->unsignedInteger('min_notice_hours')->nullable();
            $table->unsignedInteger('max_horizon_days')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['weekday', 'enabled']);
        });

        Schema::create('availability_exceptions', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('type', 32); // 'blocked', 'special_hours'
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_exceptions');
        Schema::dropIfExists('availability_rules');
    }
};

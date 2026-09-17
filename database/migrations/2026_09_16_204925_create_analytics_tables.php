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
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('visitor_token', 64)->unique();
            $table->dateTime('first_seen_at');
            $table->dateTime('last_seen_at')->index();
            $table->string('device_type', 30)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('visitor_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_token', 64)->unique();
            $table->foreignId('visitor_id')->constrained('visitors')->cascadeOnDelete();
            $table->dateTime('started_at');
            $table->dateTime('last_activity_at')->index();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->text('referrer')->nullable();
            $table->string('landing_page')->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_name', 64)->index();
            $table->string('visitor_token', 64)->nullable()->index();
            $table->string('session_token', 64)->nullable()->index();
            $table->string('page')->nullable();
            $table->text('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->boolean('is_bot')->default(false)->index();
            $table->dateTime('created_at')->index();
        });

        Schema::create('daily_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date')->index();
            $table->string('metric_name', 64);
            $table->string('dimension_key', 64)->nullable();
            $table->string('dimension_value', 128)->nullable();
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'metric_name', 'dimension_key', 'dimension_value'], 'daily_metrics_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_metrics');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('visitor_sessions');
        Schema::dropIfExists('visitors');
    }
};

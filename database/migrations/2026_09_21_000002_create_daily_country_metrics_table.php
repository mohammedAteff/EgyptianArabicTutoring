<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('daily_country_metrics')) {
            Schema::create('daily_country_metrics', function (Blueprint $table) {
                $table->id();
                $table->date('metric_date');
                $table->char('country_code', 2);
                $table->unsignedInteger('unique_visitors')->default(0);
                $table->unsignedInteger('booking_cta_clicks')->default(0);
                $table->unsignedInteger('bookings_completed')->default(0);
                $table->unsignedInteger('resource_requests')->default(0);
                // Compatibility columns retained for the existing traffic report.
                $table->unsignedInteger('visitors')->default(0);
                $table->unsignedInteger('sessions')->default(0);
                $table->unsignedInteger('page_views')->default(0);
                $table->timestamps();

                $table->unique(['metric_date', 'country_code'], 'uq_daily_country_metrics');
                $table->index(['country_code', 'metric_date'], 'idx_country_metric_date');
                $table->index('metric_date', 'idx_country_metric_date_only');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('daily_country_metrics') && DB::table('daily_country_metrics')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000002: daily_country_metrics contains historical country metric records. Reversing this migration would destroy country analytics history.');
        }

        Schema::dropIfExists('daily_country_metrics');
    }
};

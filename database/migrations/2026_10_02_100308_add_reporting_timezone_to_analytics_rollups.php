<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_metrics', function (Blueprint $table): void {
            $table->string('reporting_timezone', 64)->default('Africa/Cairo');
            $table->dropUnique('daily_metrics_unique');
            $table->unique(['reporting_timezone', 'metric_date', 'metric_name', 'dimension_key', 'dimension_value'], 'daily_metrics_timezone_unique');
        });
        Schema::table('daily_country_metrics', function (Blueprint $table): void {
            $table->string('reporting_timezone', 64)->default('Africa/Cairo');
            $table->dropUnique('uq_daily_country_metrics');
            $table->unique(['reporting_timezone', 'metric_date', 'country_code'], 'country_metrics_timezone_unique');
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Reporting timezone partitions preserve historical analytics and cannot be dropped automatically.');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the activity dimensions required by the country analytics contract.
     */
    public function up(): void
    {
        if (Schema::hasTable('daily_country_metrics')) {
            Schema::table('daily_country_metrics', function (Blueprint $table): void {
                if (! Schema::hasColumn('daily_country_metrics', 'unique_visitors')) {
                    $table->unsignedInteger('unique_visitors')->default(0)->after('country_code');
                }
                if (! Schema::hasColumn('daily_country_metrics', 'booking_cta_clicks')) {
                    $table->unsignedInteger('booking_cta_clicks')->default(0)->after('sessions');
                }
                if (! Schema::hasColumn('daily_country_metrics', 'bookings_completed')) {
                    $table->unsignedInteger('bookings_completed')->default(0)->after('booking_cta_clicks');
                }
                if (! Schema::hasColumn('daily_country_metrics', 'resource_requests')) {
                    $table->unsignedInteger('resource_requests')->default(0)->after('bookings_completed');
                }
            });

            // The initial V2 migration used the old visitors/page_views names.
            // Keep them as compatibility aliases while the canonical columns above
            // become the source of truth for new rollups.
            Schema::table('daily_country_metrics', function (Blueprint $table): void {
                if (! Schema::hasColumn('daily_country_metrics', 'visitors')) {
                    $table->unsignedInteger('visitors')->default(0)->after('country_code');
                }
                if (! Schema::hasColumn('daily_country_metrics', 'page_views')) {
                    $table->unsignedInteger('page_views')->default(0)->after('visitors');
                }
            });
        }

        if (Schema::hasTable('marketing_touches') && ! Schema::hasColumn('marketing_touches', 'is_direct')) {
            Schema::table('marketing_touches', function (Blueprint $table): void {
                $table->boolean('is_direct')->default(false)->after('referrer');
            });
        }
    }

    /**
     * Reverse only the additive columns. Historical rows are retained.
     */
    public function down(): void
    {
        if (Schema::hasTable('daily_country_metrics')
            && DB::table('daily_country_metrics')->exists()) {
            throw new RuntimeException('Cannot rollback 2026_09_21_000004 while daily_country_metrics contains historical rows.');
        }

        if (Schema::hasTable('marketing_touches')
            && Schema::hasColumn('marketing_touches', 'is_direct')
            && DB::table('marketing_touches')->exists()) {
            throw new RuntimeException('Cannot rollback 2026_09_21_000004 while marketing_touches contains touch history.');
        }

        if (Schema::hasTable('daily_country_metrics')) {
            Schema::table('daily_country_metrics', function (Blueprint $table): void {
                foreach (['unique_visitors', 'booking_cta_clicks', 'bookings_completed', 'resource_requests', 'visitors', 'page_views'] as $column) {
                    if (Schema::hasColumn('daily_country_metrics', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('marketing_touches') && Schema::hasColumn('marketing_touches', 'is_direct')) {
            Schema::table('marketing_touches', function (Blueprint $table): void {
                $table->dropColumn('is_direct');
            });
        }
    }
};

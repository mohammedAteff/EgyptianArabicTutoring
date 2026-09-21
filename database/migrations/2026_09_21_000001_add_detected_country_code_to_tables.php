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
        Schema::table('visitors', function (Blueprint $table) {
            if (! Schema::hasColumn('visitors', 'detected_country_code')) {
                $table->char('detected_country_code', 2)->nullable()->index();
            }
        });

        Schema::table('visitor_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('visitor_sessions', 'detected_country_code')) {
                $table->char('detected_country_code', 2)->nullable()->index();
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'detected_country_code')) {
                $table->char('detected_country_code', 2)->nullable()->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('visitors') && Schema::hasColumn('visitors', 'detected_country_code') && DB::table('visitors')->whereNotNull('detected_country_code')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000001: visitors table contains records with populated detected_country_code. Reversing this migration would destroy geolocation data.');
        }

        if (Schema::hasTable('visitor_sessions') && Schema::hasColumn('visitor_sessions', 'detected_country_code') && DB::table('visitor_sessions')->whereNotNull('detected_country_code')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000001: visitor_sessions table contains records with populated detected_country_code. Reversing this migration would destroy geolocation data.');
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'detected_country_code') && DB::table('bookings')->whereNotNull('detected_country_code')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000001: bookings table contains records with populated detected_country_code. Reversing this migration would destroy geolocation data.');
        }

        Schema::table('visitors', function (Blueprint $table) {
            if (Schema::hasColumn('visitors', 'detected_country_code')) {
                $table->dropColumn('detected_country_code');
            }
        });

        Schema::table('visitor_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('visitor_sessions', 'detected_country_code')) {
                $table->dropColumn('detected_country_code');
            }
        });

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'detected_country_code')) {
                $table->dropColumn('detected_country_code');
            }
        });
    }
};

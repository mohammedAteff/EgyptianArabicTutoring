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
            if (! Schema::hasColumn('visitors', 'visitor_id')) {
                $table->string('visitor_id', 64)->nullable()->after('visitor_token')->index();
            }
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            if (! Schema::hasColumn('analytics_events', 'visitor_id')) {
                $table->string('visitor_id', 64)->nullable()->after('visitor_token')->index();
            }
        });

        Schema::table('visitor_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('visitor_sessions', 'session_id')) {
                $table->string('session_id', 64)->nullable()->after('session_token')->index();
            }
        });

        // Populate existing tokens into id fields
        DB::table('visitors')->whereNull('visitor_id')->update(['visitor_id' => DB::raw('visitor_token')]);
        DB::table('analytics_events')->whereNull('visitor_id')->update(['visitor_id' => DB::raw('visitor_token')]);
        DB::table('visitor_sessions')->whereNull('session_id')->update(['session_id' => DB::raw('session_token')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('visitors') && Schema::hasColumn('visitors', 'visitor_id') && DB::table('visitors')->whereNotNull('visitor_id')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000003: visitors contains compatibility identity data.');
        }

        if (Schema::hasTable('analytics_events') && Schema::hasColumn('analytics_events', 'visitor_id') && DB::table('analytics_events')->whereNotNull('visitor_id')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000003: analytics_events contains compatibility identity data.');
        }

        if (Schema::hasTable('visitor_sessions') && Schema::hasColumn('visitor_sessions', 'session_id') && DB::table('visitor_sessions')->whereNotNull('session_id')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000003: visitor_sessions contains compatibility identity data.');
        }

        Schema::table('visitors', function (Blueprint $table) {
            if (Schema::hasColumn('visitors', 'visitor_id')) {
                $table->dropColumn('visitor_id');
            }
        });

        Schema::table('analytics_events', function (Blueprint $table) {
            if (Schema::hasColumn('analytics_events', 'visitor_id')) {
                $table->dropColumn('visitor_id');
            }
        });

        Schema::table('visitor_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('visitor_sessions', 'session_id')) {
                $table->dropColumn('session_id');
            }
        });
    }
};

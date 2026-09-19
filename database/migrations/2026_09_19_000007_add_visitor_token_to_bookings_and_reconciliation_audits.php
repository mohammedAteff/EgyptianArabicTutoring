<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'visitor_token')) {
                $table->string('visitor_token', 64)->nullable()->after('contact_id')->index();
            }
        });

        Schema::create('analytics_reconciliation_audits', function (Blueprint $table) {
            $table->id();
            $table->string('audit_type', 50)->default('rebuild');
            $table->timestamp('cutover_at')->nullable();
            $table->integer('rebuilt_visitors_count')->default(0);
            $table->integer('preserved_historical_count')->default(0);
            $table->integer('authoritative_bookings_count')->default(0);
            $table->timestamp('non_comparable_before')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('analytics_reconciliation_audits') && DB::table('analytics_reconciliation_audits')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_19_000007: analytics_reconciliation_audits contains historical audit records. Reversing this migration would destroy auditable analytics history.');
        }

        if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'visitor_token') && DB::table('bookings')->whereNotNull('visitor_token')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_19_000007: bookings table contains records with populated visitor_token. Reversing this migration would destroy attribution linking.');
        }

        Schema::dropIfExists('analytics_reconciliation_audits');

        Schema::table('bookings', function (Blueprint $table) {
            if (Schema::hasColumn('bookings', 'visitor_token')) {
                $table->dropColumn('visitor_token');
            }
        });
    }
};

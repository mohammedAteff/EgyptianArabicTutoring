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
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('referrer', 500)->nullable()->after('term');
            $table->dateTime('touch_at')->nullable()->after('referrer');
        });

        Schema::create('marketing_touches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('visitors')->cascadeOnDelete();
            $table->string('visitor_token', 64)->index();
            $table->string('session_token', 64)->nullable()->index();
            $table->string('utm_source', 100)->nullable();
            $table->string('utm_medium', 100)->nullable();
            $table->string('utm_campaign', 100)->nullable();
            $table->string('utm_content', 100)->nullable();
            $table->string('utm_term', 100)->nullable();
            $table->string('referrer', 500)->nullable();
            $table->dateTime('touch_at')->index();
            $table->timestamps();

            $table->index(['visitor_token', 'touch_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('marketing_touches') && DB::table('marketing_touches')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_19_000005: marketing_touches contains historical touch attribution records. Reversing this migration would destroy marketing attribution history.');
        }

        if (Schema::hasTable('bookings') &&
            Schema::hasColumn('bookings', 'referrer') &&
            Schema::hasColumn('bookings', 'touch_at') &&
            DB::table('bookings')->where(function ($q) {
                $q->whereNotNull('referrer')->orWhereNotNull('touch_at');
            })->exists()
        ) {
            throw new RuntimeException('Cannot rollback migration 2026_09_19_000005: bookings table contains records with populated marketing attribution data. Reversing this migration would destroy touch attribution.');
        }

        Schema::dropIfExists('marketing_touches');

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['referrer', 'touch_at']);
        });
    }
};

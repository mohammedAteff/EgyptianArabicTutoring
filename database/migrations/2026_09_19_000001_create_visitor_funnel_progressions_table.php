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
        Schema::create('visitor_funnel_progressions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->unique()->constrained('visitors')->cascadeOnDelete();
            $table->date('cohort_date')->index(); // First-visit date in Africa/Cairo
            $table->dateTime('visitor_at')->index(); // First qualifying visit timestamp

            $table->dateTime('booking_cta_observed_at')->nullable();
            $table->dateTime('booking_cta_qualified_at')->nullable();
            $table->string('booking_cta_provenance', 20)->default('none'); // observed, imputed, none

            $table->dateTime('booking_started_observed_at')->nullable();
            $table->dateTime('booking_started_qualified_at')->nullable();
            $table->string('booking_started_provenance', 20)->default('none');

            $table->dateTime('slot_held_observed_at')->nullable();
            $table->dateTime('slot_held_qualified_at')->nullable();
            $table->string('slot_held_provenance', 20)->default('none');

            $table->dateTime('booking_completed_observed_at')->nullable();
            $table->dateTime('booking_completed_qualified_at')->nullable();
            $table->string('booking_completed_provenance', 20)->default('none');

            $table->dateTime('reconciled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('visitor_funnel_progressions') && DB::table('visitor_funnel_progressions')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_19_000001: visitor_funnel_progressions contains historical progression records. Reversing this migration would destroy funnel cohort history.');
        }

        Schema::dropIfExists('visitor_funnel_progressions');
    }
};

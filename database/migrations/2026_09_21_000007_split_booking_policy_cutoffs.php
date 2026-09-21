<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate the canonical four-hour cancellation rule from the
     * twenty-four-hour direct-contact rescheduling notice.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now('UTC');
        $legacyPolicy = 'Cancellations and rescheduling are accepted up to 24 hours in advance.';
        $canonicalPolicy = 'Cancellations with at least 4 hours notice do not forfeit the session credit. Rescheduling requests must be made directly to Abdallah at least 24 hours before class.';

        $cancellation = DB::table('settings')->where('key', 'booking_cancellation_cutoff_hours')->first();
        if (! $cancellation) {
            DB::table('settings')->insert([
                'key' => 'booking_cancellation_cutoff_hours',
                'value' => '4',
                'group' => 'booking',
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ((string) $cancellation->value === '24') {
            DB::table('settings')->where('key', 'booking_cancellation_cutoff_hours')->update([
                'value' => '4',
                'updated_at' => $now,
            ]);
        }

        $reschedule = DB::table('settings')->where('key', 'booking_reschedule_cutoff_hours')->first();
        if (! $reschedule) {
            DB::table('settings')->insert([
                'key' => 'booking_reschedule_cutoff_hours',
                'value' => '24',
                'group' => 'booking',
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('settings')
            ->where('key', 'cancellation_policy')
            ->where('value', $legacyPolicy)
            ->update([
                'value' => $canonicalPolicy,
                'updated_at' => $now,
            ]);
    }

    /**
     * Preserve live settings on rollback; this migration never deletes
     * administrator-authored configuration.
     */
    public function down(): void
    {
        // Intentionally non-destructive. The new setting may have been
        // edited after deployment and must remain available to the app.
    }
};

<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Establish an explicit, auditable boundary for comparable analytics.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings') || DB::table('settings')->where('key', 'analytics_authoritative_cutover_date')->exists()) {
            return;
        }

        $configuredDate = env('ANALYTICS_AUTHORITATIVE_CUTOVER_DATE');
        $cutoverDate = is_string($configuredDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $configuredDate)
            ? $configuredDate
            : CarbonImmutable::now('Africa/Cairo')->toDateString();

        DB::table('settings')->insert([
            'key' => 'analytics_authoritative_cutover_date',
            'value' => $cutoverDate,
            'group' => 'analytics',
            'is_public' => false,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
    }

    /**
     * Remove only the setting created by this migration; never alter analytics data.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'analytics_authoritative_cutover_date')->delete();
        }
    }
};

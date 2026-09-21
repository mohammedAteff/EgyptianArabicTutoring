<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reconcile only the known pre-V2 default website name while preserving
     * any administrator-authored custom branding.
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $now = now('UTC');
        $setting = DB::table('settings')->where('key', 'site_name')->first();

        if (! $setting) {
            DB::table('settings')->insert([
                'key' => 'site_name',
                'value' => 'Egyptian Arabic with Abdallah',
                'group' => 'general',
                'is_public' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        if ((string) $setting->value === 'Egyptian Arabic Tutoring') {
            DB::table('settings')->where('key', 'site_name')->update([
                'value' => 'Egyptian Arabic with Abdallah',
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Preserve live branding on rollback; an administrator may have edited it.
     */
    public function down(): void
    {
        // Intentionally non-destructive.
    }
};

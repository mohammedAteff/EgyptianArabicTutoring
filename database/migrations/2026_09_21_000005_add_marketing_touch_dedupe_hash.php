<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add an atomic, deterministic duplicate guard without deleting touch
     * history. Existing collisions keep their first row's hash and retain all
     * later rows with a NULL hash.
     */
    public function up(): void
    {
        if (! Schema::hasTable('marketing_touches')) {
            return;
        }

        if (! Schema::hasColumn('marketing_touches', 'dedupe_hash')) {
            Schema::table('marketing_touches', function (Blueprint $table): void {
                $table->string('dedupe_hash', 64)->nullable()->after('is_direct');
            });
        }

        $seen = [];

        DB::table('marketing_touches')
            ->select([
                'id',
                'visitor_id',
                'session_token',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_content',
                'utm_term',
                'referrer',
                'touch_at',
            ])
            ->orderBy('id')
            ->each(function (object $touch) use (&$seen): void {
                $touchAt = $touch->touch_at
                    ? CarbonImmutable::parse((string) $touch->touch_at)
                    : null;
                $values = [
                    'visitor_id' => (string) $touch->visitor_id,
                    'session_token' => $touch->session_token,
                    'utm_source' => $touch->utm_source,
                    'utm_medium' => $touch->utm_medium,
                    'utm_campaign' => $touch->utm_campaign,
                    'utm_content' => $touch->utm_content,
                    'utm_term' => $touch->utm_term,
                    'referrer' => $touch->referrer,
                    'time_bucket' => $touchAt ? intdiv($touchAt->timestamp, 5) : null,
                ];
                $hash = hash('sha256', (string) json_encode($values, JSON_UNESCAPED_SLASHES));

                if (isset($seen[$hash])) {
                    DB::table('marketing_touches')->where('id', $touch->id)->update(['dedupe_hash' => null]);

                    return;
                }

                $seen[$hash] = true;
                DB::table('marketing_touches')->where('id', $touch->id)->update(['dedupe_hash' => $hash]);
            });

        Schema::table('marketing_touches', function (Blueprint $table): void {
            $table->unique('dedupe_hash', 'marketing_touches_dedupe_hash_unique');
        });
    }

    /**
     * Never discard populated duplicate-guard state during a rollback.
     */
    public function down(): void
    {
        if (! Schema::hasTable('marketing_touches') || ! Schema::hasColumn('marketing_touches', 'dedupe_hash')) {
            return;
        }

        if (DB::table('marketing_touches')->whereNotNull('dedupe_hash')->exists()) {
            throw new RuntimeException('Cannot rollback migration 2026_09_21_000005 while marketing_touches contains populated duplicate-guard history.');
        }

        Schema::table('marketing_touches', function (Blueprint $table): void {
            $table->dropUnique('marketing_touches_dedupe_hash_unique');
            $table->dropColumn('dedupe_hash');
        });
    }
};

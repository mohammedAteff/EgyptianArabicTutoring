<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $dbName = DB::getDatabaseName();

        if (Schema::hasColumn('resource_requests', 'consumed_challenge_hash')) {
            $colMeta = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $dbName)
                ->where('TABLE_NAME', 'resource_requests')
                ->where('COLUMN_NAME', 'consumed_challenge_hash')
                ->first();

            if (! $colMeta || strtolower($colMeta->DATA_TYPE) !== 'varchar' || (int) $colMeta->CHARACTER_MAXIMUM_LENGTH !== 64 || $colMeta->IS_NULLABLE !== 'YES' || $colMeta->COLUMN_DEFAULT !== null) {
                throw new RuntimeException('PREFLIGHT ABORT: Existing resource_requests.consumed_challenge_hash has incompatible column topology.');
            }

            // Assert exact single-column unique index exists on consumed_challenge_hash
            $uniqueIndexMatches = (bool) DB::selectOne("
                SELECT 1
                FROM information_schema.STATISTICS
                WHERE TABLE_SCHEMA = ?
                  AND TABLE_NAME = 'resource_requests'
                  AND INDEX_NAME = 'resource_requests_consumed_challenge_hash_unique'
                  AND NON_UNIQUE = 0
                GROUP BY INDEX_NAME
                HAVING COUNT(*) = 1
                   AND SUM(
                       CASE WHEN COLUMN_NAME = 'consumed_challenge_hash' AND SEQ_IN_INDEX = 1 THEN 1 ELSE 0 END
                   ) = 1
            ", [$dbName]);

            if (! $uniqueIndexMatches) {
                throw new RuntimeException('PREFLIGHT ABORT: Existing consumed_challenge_hash is missing the required single-column unique index.');
            }
        } else {
            Schema::table('resource_requests', function (Blueprint $table) {
                $table->string('consumed_challenge_hash', 64)
                    ->nullable()
                    ->unique('resource_requests_consumed_challenge_hash_unique')
                    ->after('session_token');
            });
        }
    }

    public function down(): void
    {
        throw new LogicException('Rolling back security replay hash column is prohibited.');
    }
};

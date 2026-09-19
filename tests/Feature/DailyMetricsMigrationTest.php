<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DailyMetricsMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preflight_reconciles_legacy_duplicate_null_dimension_rows(): void
    {
        // 1. Temporarily revert schema to legacy state where dimension_key and dimension_value are nullable
        Schema::table('daily_metrics', function (Blueprint $table) {
            $table->string('dimension_key', 64)->nullable()->default(null)->change();
            $table->string('dimension_value', 128)->nullable()->default(null)->change();
        });

        // 2. Clear table and seed legacy duplicate rows with NULL dimensions for same (metric_date, metric_name)
        DB::table('daily_metrics')->truncate();

        // In legacy schema, MySQL allowed multiple NULLs under unique constraint
        DB::table('daily_metrics')->insert([
            [
                'metric_date' => '2026-09-10',
                'metric_name' => 'unique_visitors',
                'count' => 50,
                'dimension_key' => null,
                'dimension_value' => null,
                'created_at' => '2026-09-10 10:00:00',
                'updated_at' => '2026-09-10 10:00:00',
            ],
            [
                'metric_date' => '2026-09-10',
                'metric_name' => 'unique_visitors',
                'count' => 30,
                'dimension_key' => null,
                'dimension_value' => null,
                'created_at' => '2026-09-10 12:00:00',
                'updated_at' => '2026-09-10 12:00:00',
            ],
            // Legitimate distinct dimension row
            [
                'metric_date' => '2026-09-10',
                'metric_name' => 'page_views',
                'count' => 120,
                'dimension_key' => 'page',
                'dimension_value' => '/about',
                'created_at' => '2026-09-10 10:00:00',
                'updated_at' => '2026-09-10 10:00:00',
            ],
        ]);

        $this->assertEquals(3, DB::table('daily_metrics')->count());

        // 3. Execute hardened migration 6 up()
        $migration = require database_path('migrations/2026_09_19_000006_harden_daily_metrics_uniqueness.php');
        $migration->up();

        // 4. Assert duplicates were reconciled into exactly one row with SUM(count) = 80
        $this->assertEquals(2, DB::table('daily_metrics')->count());

        $reconciled = DB::table('daily_metrics')
            ->where('metric_date', '2026-09-10')
            ->where('metric_name', 'unique_visitors')
            ->first();

        $this->assertNotNull($reconciled);
        $this->assertEquals(80, $reconciled->count, 'Duplicate rows must be deterministically aggregated via SUM');
        $this->assertEquals('', $reconciled->dimension_key, 'NULL dimensions must be normalized to empty string');
        $this->assertEquals('', $reconciled->dimension_value, 'NULL dimensions must be normalized to empty string');

        // 5. Assert distinct dimension row is untouched
        $distinct = DB::table('daily_metrics')
            ->where('metric_date', '2026-09-10')
            ->where('metric_name', 'page_views')
            ->first();

        $this->assertNotNull($distinct);
        $this->assertEquals(120, $distinct->count);
        $this->assertEquals('page', $distinct->dimension_key);
        $this->assertEquals('/about', $distinct->dimension_value);
    }

    public function test_migration_reconciles_mixed_null_and_empty_rows_without_mysql_1062_collision(): void
    {
        // 1. Revert schema to legacy nullable dimensions
        Schema::table('daily_metrics', function (Blueprint $table) {
            $table->string('dimension_key', 64)->nullable()->default(null)->change();
            $table->string('dimension_value', 128)->nullable()->default(null)->change();
        });

        DB::table('daily_metrics')->truncate();

        // 2. Insert mixed NULL and empty rows where the NULL row has the lower ID
        // In legacy MySQL, (NULL, NULL) and ('', '') can coexist under composite unique index
        $id1 = DB::table('daily_metrics')->insertGetId([
            'metric_date' => '2026-09-15',
            'metric_name' => 'sessions',
            'count' => 25,
            'dimension_key' => null,
            'dimension_value' => null,
            'created_at' => '2026-09-15 08:00:00',
            'updated_at' => '2026-09-15 08:00:00',
        ]);

        $id2 = DB::table('daily_metrics')->insertGetId([
            'metric_date' => '2026-09-15',
            'metric_name' => 'sessions',
            'count' => 35,
            'dimension_key' => '',
            'dimension_value' => '',
            'created_at' => '2026-09-15 09:00:00',
            'updated_at' => '2026-09-15 09:00:00',
        ]);

        $this->assertLessThan($id2, $id1, 'NULL row must have lower ID than empty-string row');
        $this->assertEquals(2, DB::table('daily_metrics')->count());

        // 3. Execute migration 6 up() - MUST NOT crash with MySQL 1062 duplicate key error
        $migration = require database_path('migrations/2026_09_19_000006_harden_daily_metrics_uniqueness.php');
        $migration->up();

        // 4. Assert rows were merged into exactly one row with combined count = 60
        $this->assertEquals(1, DB::table('daily_metrics')->count());

        $row = DB::table('daily_metrics')->where('metric_date', '2026-09-15')->where('metric_name', 'sessions')->first();
        $this->assertNotNull($row);
        $this->assertEquals(60, $row->count);
        $this->assertEquals('', $row->dimension_key);
        $this->assertEquals('', $row->dimension_value);

        // 5. Verify repeatability/idempotency: running up() again on clean schema causes no error
        $migration->up();
        $this->assertEquals(1, DB::table('daily_metrics')->count());
    }

    public function test_migration_deduplication_is_crash_safe_and_retains_data_on_interruption_rollback(): void
    {
        // 1. Revert schema to legacy nullable dimensions
        Schema::table('daily_metrics', function (Blueprint $table) {
            $table->string('dimension_key', 64)->nullable()->default(null)->change();
            $table->string('dimension_value', 128)->nullable()->default(null)->change();
        });

        // Synchronize transaction state after DDL
        DB::disconnect();
        DB::reconnect();

        DB::table('daily_metrics')->truncate();

        // 2. Insert duplicate rows with total count = 100
        $idA = DB::table('daily_metrics')->insertGetId([
            'metric_date' => '2026-09-18',
            'metric_name' => 'unique_visitors',
            'count' => 40,
            'dimension_key' => null,
            'dimension_value' => null,
            'created_at' => '2026-09-18 10:00:00',
            'updated_at' => '2026-09-18 10:00:00',
        ]);

        $idB = DB::table('daily_metrics')->insertGetId([
            'metric_date' => '2026-09-18',
            'metric_name' => 'unique_visitors',
            'count' => 60,
            'dimension_key' => '',
            'dimension_value' => '',
            'created_at' => '2026-09-18 11:00:00',
            'updated_at' => '2026-09-18 11:00:00',
        ]);

        $this->assertEquals(2, DB::table('daily_metrics')->count());

        // 3. Simulate an interruption/failure between delete and update inside a transaction
        $threw = false;
        try {
            DB::transaction(function () use ($idB) {
                // Delete colliding row B
                DB::table('daily_metrics')->where('id', $idB)->delete();

                // Unexpected failure/crash occurs before update completes
                throw new \RuntimeException('Process terminated / interrupted mid-merge');
            });
        } catch (\RuntimeException $e) {
            $threw = true;
            $this->assertEquals('Process terminated / interrupted mid-merge', $e->getMessage());
        }

        $this->assertTrue($threw);

        // 4. Assert that transaction rollback completely restored both rows and their original counts
        $this->assertEquals(2, DB::table('daily_metrics')->count(), 'Rollback must preserve both original rows');
        $this->assertDatabaseHas('daily_metrics', ['id' => $idA, 'count' => 40]);
        $this->assertDatabaseHas('daily_metrics', ['id' => $idB, 'count' => 60]);

        // 5. Retry the migration to completion: must succeed cleanly and produce exact aggregate 100
        $migration = require database_path('migrations/2026_09_19_000006_harden_daily_metrics_uniqueness.php');
        $migration->up();

        $this->assertEquals(1, DB::table('daily_metrics')->count());
        $survivor = DB::table('daily_metrics')->where('metric_date', '2026-09-18')->where('metric_name', 'unique_visitors')->first();
        $this->assertNotNull($survivor);
        $this->assertEquals(100, $survivor->count, 'Retry must yield the exact combined aggregate value');
        $this->assertEquals('', $survivor->dimension_key);
        $this->assertEquals('', $survivor->dimension_value);
    }
}

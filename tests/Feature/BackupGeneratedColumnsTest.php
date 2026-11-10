<?php

namespace Tests\Feature;

use App\Domains\System\Services\BackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class BackupGeneratedColumnsTest extends TestCase
{
    use DatabaseTruncation;

    #[TestWith(['virtual'])]
    #[TestWith(['stored'])]
    public function test_dump_restores_generated_columns_and_original_values_in_strict_mode(string $kind): void
    {
        Storage::fake('local');
        Storage::fake('managed_backups');
        Schema::create('backup_generated_columns', function (Blueprint $table) use ($kind): void {
            $table->unsignedBigInteger('id')->primary();
            $table->integer('base_value');
            $computed = $table->integer('computed_value');
            if ($kind === 'virtual') {
                $computed->virtualAs('base_value * 2');
            } else {
                $computed->storedAs('base_value * 2');
            }
            $computed->unique();
            $table->string('original_text');
            $table->binary('payload', 32);
            $table->decimal('amount', 16, 4);
            $table->string('nullable_text')->nullable();
            $table->timestamp('recorded_at')->useCurrent();
        });
        $mode = DB::selectOne('SELECT @@SESSION.sql_mode mode')->mode;
        $strictMode = 'ONLY_FULL_GROUP_BY,STRICT_ALL_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_VALUE_ON_ZERO,NO_ENGINE_SUBSTITUTION';

        try {
            $records = [];
            for ($index = 0; $index < 101; $index++) {
                $records[] = [
                    'id' => $index,
                    'base_value' => $index + 7,
                    'original_text' => $index === 0 ? 'DEFAULT' : "00123 Arabic العربية; quote '\\ and newline\n{$index}",
                    'payload' => "\x00\x01\x7f\x80\xff'\\\r\n",
                    'amount' => '123.4500',
                    'nullable_text' => null,
                    'recorded_at' => '2019-01-02 03:04:05',
                ];
            }
            DB::table('backup_generated_columns')->insert($records);
            $expected = DB::table('backup_generated_columns')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all();
            $schema = DB::selectOne('SHOW CREATE TABLE backup_generated_columns')->{'Create Table'};
            $service = app(BackupService::class);
            $objects = $service->snapshotDatabaseObjects();
            $path = Storage::disk('local')->path('generated-columns.sql');

            $service->dumpDatabase($path);
            $sql = file_get_contents($path);
            $this->assertMatchesRegularExpression('/INSERT INTO `backup_generated_columns` VALUES\s+\([^\n]*, DEFAULT,/', $sql);
            $strictSql = str_replace('SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";', 'SET SQL_MODE="'.$strictMode.'";', $sql, $replacements);
            $this->assertSame(1, $replacements);
            DB::unprepared($strictSql);
            DB::statement('SET AUTOCOMMIT=1');
            $service->restoreDatabaseObjects($objects);

            $this->assertSame($expected, DB::table('backup_generated_columns')->orderBy('id')->get()->map(fn (object $row): array => (array) $row)->all());
            $this->assertSame($schema, DB::selectOne('SHOW CREATE TABLE backup_generated_columns')->{'Create Table'});
            $this->assertSame($objects, $service->snapshotDatabaseObjects());
            $this->assertStringContainsString('STRICT_ALL_TABLES', DB::selectOne('SELECT @@SESSION.sql_mode mode')->mode);
        } finally {
            DB::statement('SET AUTOCOMMIT=1');
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            DB::statement('SET SESSION sql_mode = ?', [$mode]);
            Schema::dropIfExists('backup_generated_columns');
        }
    }
}

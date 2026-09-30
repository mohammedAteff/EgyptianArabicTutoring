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

        if (! Schema::hasTable('form_triggers')) {
            throw new RuntimeException('PREFLIGHT ABORT: form_triggers table missing. Migration A must run before Migration B.');
        }

        if (Schema::hasColumn('forms', 'prompt_trigger')) {
            $promptCol = DB::table('information_schema.COLUMNS')
                ->where('TABLE_SCHEMA', $dbName)
                ->where('TABLE_NAME', 'forms')
                ->where('COLUMN_NAME', 'prompt_trigger')
                ->first();

            if (
                ! $promptCol
                || strtolower((string) $promptCol->DATA_TYPE) !== 'enum'
                || (string) $promptCol->COLUMN_TYPE !== "enum('none','after_booking','after_reschedule','next_session_check')"
                || $promptCol->IS_NULLABLE !== 'NO'
                || $promptCol->COLUMN_DEFAULT !== "'none'"
            ) {
                throw new RuntimeException('PREFLIGHT ABORT: forms.prompt_trigger does not match verified legacy topology.');
            }

            $unmigratedTriggers = DB::select("
                SELECT f.id, f.prompt_trigger
                FROM forms f
                LEFT JOIN form_triggers ft ON f.id = ft.form_id AND f.prompt_trigger = ft.trigger_name
                WHERE f.prompt_trigger IS NOT NULL
                  AND f.prompt_trigger != 'none'
                  AND ft.id IS NULL
            ");

            if (! empty($unmigratedTriggers)) {
                throw new RuntimeException('PREFLIGHT ABORT: Found unmigrated legacy prompt_trigger entries. Refusing to drop column.');
            }

            Schema::table('forms', function (Blueprint $table) {
                $table->dropColumn('prompt_trigger');
            });
        }
    }

    public function down(): void
    {
        throw new LogicException('Migration B permanently removes forms.prompt_trigger. Restore from backup or run reviewed forward migration; automatic rollback is prohibited.');
    }
};

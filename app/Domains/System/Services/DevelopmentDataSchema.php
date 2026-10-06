<?php

namespace App\Domains\System\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DevelopmentDataSchema
{
    /** @var array<string, array<string, mixed>>|null */
    private ?array $metadata = null;

    /** Metadata is request-scoped; MariaDB discovery uses three bounded catalog queries.
     * @return array<string, array<string, mixed>> */
    public function read(): array
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return $this->frameworkSchema();
        }
        $database = DB::connection()->getDatabaseName();
        $columns = DB::select('SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA, GENERATION_EXPRESSION FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, ORDINAL_POSITION', [$database]);
        $keys = DB::select('SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME, k.REFERENCED_TABLE_SCHEMA, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME, r.UPDATE_RULE, r.DELETE_RULE FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE k JOIN INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS r ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME AND r.TABLE_NAME = k.TABLE_NAME WHERE k.TABLE_SCHEMA = ? AND k.REFERENCED_TABLE_NAME IS NOT NULL ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION', [$database]);
        $indexes = DB::select('SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, COLUMN_NAME FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX', [$database]);
        $metadata = [];
        foreach ($columns as $column) {
            $table = $column->TABLE_NAME;
            if (! preg_match('/^[a-z][a-z0-9_]*$/D', $table)) {
                throw ValidationException::withMessages(['scope' => 'An unsupported schema identifier requires review.']);
            }
            $metadata[$table] ??= ['columns' => [], 'foreign_keys' => [], 'primary' => [], 'unique_indexes' => []];
            $metadata[$table]['columns'][$column->COLUMN_NAME] = ['name' => $column->COLUMN_NAME, 'type' => $column->COLUMN_TYPE,
                'nullable' => $column->IS_NULLABLE === 'YES', 'auto_increment' => str_contains($column->EXTRA, 'auto_increment'),
                'generated' => str_contains(strtoupper($column->EXTRA), 'GENERATED'), 'generation' => $column->GENERATION_EXPRESSION];
            if ($column->COLUMN_KEY === 'PRI') {
                $metadata[$table]['primary'][] = $column->COLUMN_NAME;
            }
        }
        foreach ($keys as $key) {
            if ($key->REFERENCED_TABLE_SCHEMA !== $database) {
                throw ValidationException::withMessages(['scope' => 'Cross-database ownership requires an explicit review before using development tools.']);
            }
            $table = $key->TABLE_NAME;
            $name = $key->CONSTRAINT_NAME;
            $metadata[$table]['foreign_keys'][$name] ??= ['name' => $name, 'columns' => [], 'foreign_columns' => [],
                'foreign_table' => $key->REFERENCED_TABLE_NAME, 'on_update' => strtolower($key->UPDATE_RULE), 'on_delete' => strtolower($key->DELETE_RULE)];
            $metadata[$table]['foreign_keys'][$name]['columns'][] = $key->COLUMN_NAME;
            $metadata[$table]['foreign_keys'][$name]['foreign_columns'][] = $key->REFERENCED_COLUMN_NAME;
        }
        foreach ($indexes as $index) {
            if ((int) $index->NON_UNIQUE === 0) {
                $metadata[$index->TABLE_NAME]['unique_indexes'][$index->INDEX_NAME][] = $index->COLUMN_NAME;
            }
        }
        foreach ($metadata as &$table) {
            $table['foreign_keys'] = array_values($table['foreign_keys']);
            $table['unique_indexes'] = array_values($table['unique_indexes']);
        }
        unset($table);
        $this->metadata = $metadata;

        return $metadata;
    }

    /** @return array<string, array<string, mixed>> */
    private function frameworkSchema(): array
    {
        $metadata = [];
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            $indexes = Schema::getIndexes($name);
            $primary = collect($indexes)->first(fn (array $index): bool => $index['primary']);
            $metadata[$name] = ['columns' => array_column(Schema::getColumns($name), null, 'name'),
                'foreign_keys' => Schema::getForeignKeys($name), 'primary' => $primary['columns'] ?? [],
                'unique_indexes' => array_column(array_filter($indexes, fn (array $index): bool => $index['unique']), 'columns')];
        }
        $this->metadata = $metadata;

        return $metadata;
    }
}

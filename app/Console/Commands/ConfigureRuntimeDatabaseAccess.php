<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ConfigureRuntimeDatabaseAccess extends Command
{
    protected $signature = 'database:runtime-access {username : A new dedicated runtime account} {--host=localhost} {--connection= : Maintenance connection} {--apply : Apply the displayed grants to an existing empty account}';

    protected $description = 'Preview or apply restricted grants using separate maintenance credentials';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $host = (string) $this->option('host');
        $db = DB::connection($this->option('connection') ?: null);
        $database = $db->getDatabaseName();
        if (! preg_match('/\A[A-Za-z0-9_]{1,32}\z/', $username) || ! preg_match('/\A[A-Za-z0-9_.-]{1,255}\z/', $host)
            || ! preg_match('/\A[A-Za-z0-9_]+\z/', $database) || $username === 'root' || $username === $db->getConfig('username')) {
            $this->error('Use a distinct runtime account, a specific host, and a named application database. No grants were changed.');

            return self::FAILURE;
        }
        $tables = $db->table('information_schema.TABLES')->where('TABLE_SCHEMA', $database)->where('TABLE_TYPE', 'BASE TABLE')->orderBy('TABLE_NAME')->pluck('TABLE_NAME');
        $appendOnly = ['audit_logs', 'payment_records', 'payment_refunds', 'session_ledger_entries'];
        $grants = [];
        foreach ($tables as $table) {
            if (! preg_match('/\A[A-Za-z0-9_]+\z/', $table)) {
                $this->error('Unsupported table identifier. No grants were changed.');

                return self::FAILURE;
            }
            $privileges = in_array($table, $appendOnly, true) ? 'SELECT, INSERT' : 'SELECT, INSERT, UPDATE, DELETE';
            $grants[] = 'GRANT '.$privileges.' ON `'.$database.'`.`'.$table.'` TO '.$db->getPdo()->quote($username).'@'.$db->getPdo()->quote($host);
        }
        foreach ($grants as $grant) {
            $this->line($grant.';');
        }
        $this->warn('Schema changes, native backup/restore, financial privacy redaction, student merges and development data resets require the separate maintenance account. Test these operations before switching runtime credentials.');
        if (! $this->option('apply')) {
            $this->info('Preview only. No accounts, passwords or grants were changed.');

            return self::SUCCESS;
        }
        if (! $this->input->isInteractive()) {
            $this->error('Grant changes require interactive confirmation. No grants were changed.');

            return self::FAILURE;
        }
        $account = $db->getPdo()->quote($username).'@'.$db->getPdo()->quote($host);
        try {
            $existing = $db->select('SHOW GRANTS FOR '.$account);
        } catch (\Throwable) {
            $this->error('Create the dedicated account privately through your database administrator first. No grants were changed.');

            return self::FAILURE;
        }
        foreach ($existing as $row) {
            $grant = array_values((array) $row)[0];
            if (! str_starts_with($grant, 'GRANT USAGE ON *.*') || str_contains($grant, 'WITH GRANT OPTION')) {
                $this->error('The account already has privileges. Refusing to modify an existing access policy.');

                return self::FAILURE;
            }
        }
        if (! $this->confirm('Apply these grants to the dedicated account? This does not switch application credentials.')) {
            return self::FAILURE;
        }
        foreach ($grants as $grant) {
            $db->statement($grant);
        }
        $this->info('Restricted grants applied. Application credentials were not changed. Retain separate maintenance access.');

        return self::SUCCESS;
    }
}

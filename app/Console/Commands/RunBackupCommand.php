<?php

namespace App\Console\Commands;

use App\Domains\System\Services\BackupService;
use Illuminate\Console\Command;
use Throwable;

class RunBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run {--type=full : Type of backup (full, database, assets)} {--clean : Prune expired backups after run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a complete MariaDB database and asset archive backup with retention enforcement';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting backup process...');
        $type = $this->option('type') ?: 'full';

        try {
            $backupPath = $backupService->createBackup($type);
            $this->info("Backup successfully generated at: {$backupPath}");

            if ($this->option('clean')) {
                $this->info('Pruning backups older than retention policy...');
                $deletedCount = $backupService->cleanOldBackups();
                $this->info("Pruned {$deletedCount} expired backup archive(s).");
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Backup process failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

<?php

namespace App\Console\Commands;

use App\Domains\System\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class RestoreBackupCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:restore 
                            {file : The backup filename or full path} 
                            {--no-db : Skip database restoration} 
                            {--no-files : Skip file extraction} 
                            {--target-dir= : Target extraction directory for files (defaults to storage/app)} 
                            {--force : Bypass confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restore database and assets from a backup archive';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $file = $this->argument('file');

        // Resolve file path
        if (File::exists($file)) {
            $zipPath = realpath($file) ?: $file;
        } else {
            $resolvedPath = $backupService->getBackupPath($file);
            if ($resolvedPath && File::exists($resolvedPath)) {
                $zipPath = $resolvedPath;
            } else {
                $this->error("Backup archive not found: {$file}");

                return self::FAILURE;
            }
        }

        $restoreDb = ! $this->option('no-db');
        $restoreFiles = ! $this->option('no-files');
        $targetDir = $this->option('target-dir');

        if (! $this->option('force')) {
            $this->warn('WARNING: Restoring a backup may overwrite existing database tables and uploaded assets.');
            if (! $this->confirm('Do you wish to proceed with restoration?')) {
                $this->info('Restoration cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info("Starting restoration from: {$zipPath}...");

        try {
            $result = $backupService->restoreBackup($zipPath, $targetDir, $restoreDb, $restoreFiles);

            $this->info('Restoration completed successfully:');
            $this->line("- Archive: {$result['file']}");
            $this->line('- Database Restored: '.($result['database_restored'] ? 'Yes' : 'Skipped'));
            $this->line("- Files Restored: {$result['files_restored']}");
            $this->line("- Restored At: {$result['restored_at']}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Restoration failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}

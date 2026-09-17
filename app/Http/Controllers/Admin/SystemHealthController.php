<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Setting;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function index(): View
    {
        // 1. Database Check
        $dbStatus = 'healthy';
        $dbMessage = 'Active database connection';
        $dbDetails = '';
        try {
            $pdo = DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $dbName = DB::connection()->getDatabaseName();
            $dbDetails = "Driver: {$driver} &bull; Database: {$dbName}";
        } catch (\Throwable $e) {
            $dbStatus = 'unhealthy';
            $dbMessage = $e->getMessage();
        }

        // 2. Storage & Disk Usage Check
        $storageStatus = 'healthy';
        $storageMessage = 'Storage disk writable';
        $diskDetails = '';
        try {
            $testFile = 'health-check-'.time().'.tmp';
            Storage::disk('local')->put($testFile, 'ok');
            Storage::disk('local')->delete($testFile);

            $rootPath = storage_path('app');
            if (function_exists('disk_free_space') && function_exists('disk_total_space')) {
                $freeBytes = @disk_free_space($rootPath);
                $totalBytes = @disk_total_space($rootPath);
                if ($freeBytes !== false && $totalBytes !== false && $totalBytes > 0) {
                    $usedBytes = $totalBytes - $freeBytes;
                    $usagePercent = round(($usedBytes / $totalBytes) * 100, 1);
                    $freeGb = round($freeBytes / (1024 * 1024 * 1024), 2);
                    $totalGb = round($totalBytes / (1024 * 1024 * 1024), 2);
                    $diskDetails = "{$freeGb} GB free of {$totalGb} GB ({$usagePercent}% used)";
                    if ($usagePercent > 90) {
                        $storageStatus = 'warning';
                        $storageMessage = 'Low disk space warning';
                    }
                }
            }
        } catch (\Throwable $e) {
            $storageStatus = 'unhealthy';
            $storageMessage = $e->getMessage();
        }

        // 3. Cache Engine Check
        $cacheStatus = 'healthy';
        $cacheMessage = 'Cache store responsive';
        try {
            Cache::put('health_check_token', true, 10);
            if (! Cache::get('health_check_token')) {
                throw new \Exception('Cache get check returned false');
            }
        } catch (\Throwable $e) {
            $cacheStatus = 'unhealthy';
            $cacheMessage = $e->getMessage();
        }

        // 4. Backup Status Check
        $lastBackupAt = Setting::get('last_backup_at');
        $lastBackupStatusSetting = Setting::get('last_backup_status', 'never_run');
        $lastBackupFile = Setting::get('last_backup_file');
        $offsiteStatus = Setting::get('last_offsite_backup_status');
        $offsiteDisk = config('filesystems.backup_disk') ?: Setting::get('backup_offsite_disk');
        $backupStatus = 'healthy';
        $backupMessage = 'Backups operational';

        if ($lastBackupStatusSetting === 'never_run') {
            $backupStatus = 'warning';
            $backupMessage = 'No backup has been executed yet';
        } elseif (str_starts_with($lastBackupStatusSetting, 'failed')) {
            $backupStatus = 'unhealthy';
            $backupMessage = $lastBackupStatusSetting;
        } elseif ($offsiteStatus && str_starts_with($offsiteStatus, 'failed')) {
            $backupStatus = 'unhealthy';
            $backupMessage = 'Off-host backup replication failed: '.substr($offsiteStatus, 8);
        } elseif ($lastBackupAt && CarbonImmutable::parse($lastBackupAt)->isBefore(now()->subHours(36))) {
            $backupStatus = 'warning';
            $backupMessage = 'Last backup is over 36 hours old';
        } elseif (empty($offsiteDisk) || $offsiteDisk === 'local') {
            $backupStatus = 'warning';
            $backupMessage = 'Off-host backup unconfigured or set to local disk (Section 106 requires off-host storage)';
        } elseif ($lastBackupAt) {
            $backupTime = CarbonImmutable::parse($lastBackupAt);
            $backupMessage = 'Last snapshot: '.$backupTime->diffForHumans();
        }

        // 5. Scheduler Heartbeat & Freshness Check
        $lastSchedulerRunAt = Setting::get('last_scheduler_run_at');
        $schedulerStatus = 'healthy';
        $schedulerMessage = 'Scheduler active';
        $schedulerDetails = '';

        if (! $lastSchedulerRunAt) {
            $schedulerStatus = 'warning';
            $schedulerMessage = 'No heartbeat recorded';
            $schedulerDetails = 'Cron job [schedule:run] not detected on host';
        } else {
            $schedulerTime = CarbonImmutable::parse($lastSchedulerRunAt);
            $minutesAgo = $schedulerTime->diffInMinutes(now('UTC'));
            if ($minutesAgo > 10) {
                $schedulerStatus = 'unhealthy';
                $schedulerMessage = 'Scheduler stale';
                $schedulerDetails = "Last heartbeat: {$schedulerTime->diffForHumans()} (expected every minute)";
            } else {
                $schedulerDetails = "Last heartbeat: {$schedulerTime->diffForHumans()}";
            }
        }

        // 6. Queue Worker & Failed Jobs Check
        $failedJobsCount = 0;
        $pendingJobsCount = 0;
        $queueStatus = 'healthy';
        $queueDriver = config('queue.default', 'sync');
        $queueMessage = 'Queue worker operational';

        try {
            $failedJobsCount = DB::table('failed_jobs')->count();
        } catch (\Throwable) {
        }

        try {
            $pendingJobsCount = DB::table('jobs')->count();
        } catch (\Throwable) {
        }

        if ($queueDriver === 'sync') {
            if ($failedJobsCount > 0) {
                $queueStatus = 'warning';
                $queueMessage = "{$failedJobsCount} failed ".($failedJobsCount === 1 ? 'job' : 'jobs').' in queue';
            } elseif (app()->isProduction()) {
                $queueStatus = 'warning';
                $queueMessage = 'Sync queue driver active in production (background jobs run synchronously)';
            } else {
                $queueStatus = 'healthy';
                $queueMessage = 'Sync driver active (synchronous execution)';
            }
        } else {
            $workerHeartbeat = Cache::get('queue_worker_heartbeat_at');
            if (! $workerHeartbeat && $pendingJobsCount > 0) {
                $queueStatus = 'unhealthy';
                $queueMessage = "Queue worker inactive with {$pendingJobsCount} pending jobs";
            } elseif ($workerHeartbeat && CarbonImmutable::parse($workerHeartbeat)->isBefore(now()->subMinutes(5)) && $pendingJobsCount > 0) {
                $queueStatus = 'unhealthy';
                $queueMessage = 'Queue worker heartbeat stale with pending jobs';
            } elseif ($failedJobsCount > 0) {
                $queueStatus = 'warning';
                $queueMessage = "{$failedJobsCount} failed ".($failedJobsCount === 1 ? 'job' : 'jobs').' in queue';
            } elseif (! $workerHeartbeat) {
                $queueStatus = 'warning';
                $queueMessage = 'No active queue worker heartbeat detected';
            } elseif (CarbonImmutable::parse($workerHeartbeat)->isBefore(now()->subMinutes(5))) {
                $queueStatus = 'warning';
                $queueMessage = 'Queue worker heartbeat stale ('.CarbonImmutable::parse($workerHeartbeat)->diffForHumans().')';
            } else {
                $queueStatus = 'healthy';
                $queueMessage = 'Queue worker operational (heartbeat active)';
            }
        }

        // 7. Mail Delivery Configuration
        $mailDriver = config('mail.default', 'log');
        $mailFrom = config('mail.from.address', 'not set');
        $mailStatus = 'healthy';
        $mailMessage = "Driver: [{$mailDriver}]";

        if ($mailDriver === 'log') {
            if (app()->isProduction()) {
                $mailStatus = 'warning';
                $mailMessage = 'Log driver active in production (emails not delivered)';
            } else {
                $mailMessage = 'Log mailer active (development mode)';
            }
        }

        return view('admin.system.health', [
            'title' => 'System Health & Diagnostics',
            'dbStatus' => $dbStatus,
            'dbMessage' => $dbMessage,
            'dbDetails' => $dbDetails,
            'storageStatus' => $storageStatus,
            'storageMessage' => $storageMessage,
            'diskDetails' => $diskDetails,
            'cacheStatus' => $cacheStatus,
            'cacheMessage' => $cacheMessage,
            'backupStatus' => $backupStatus,
            'backupMessage' => $backupMessage,
            'lastBackupFile' => $lastBackupFile,
            'offsiteStatus' => $offsiteStatus,
            'schedulerStatus' => $schedulerStatus,
            'schedulerMessage' => $schedulerMessage,
            'schedulerDetails' => $schedulerDetails,
            'queueStatus' => $queueStatus,
            'queueMessage' => $queueMessage,
            'failedJobsCount' => $failedJobsCount,
            'pendingJobsCount' => $pendingJobsCount,
            'mailStatus' => $mailStatus,
            'mailMessage' => $mailMessage,
            'mailDriver' => $mailDriver,
            'mailFrom' => $mailFrom,
            'phpVersion' => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'serverTimeUtc' => now('UTC')->toDateTimeString(),
            'cairoTime' => now('Africa/Cairo')->toDateTimeString(),
            'environment' => app()->environment(),
            'debugMode' => config('app.debug'),
        ]);
    }

    public function auditLogs(): View
    {
        $logs = AuditLog::query()
            ->with('administrator')
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.system.audit-logs', [
            'title' => 'Security Audit Logs',
            'logs' => $logs,
        ]);
    }
}

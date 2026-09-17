<?php

namespace App\Http\Controllers\Admin;

use App\Domains\CMS\Models\Setting;
use App\Domains\System\Services\BackupService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService
    ) {}

    public function index(): View
    {
        $backups = $this->backupService->getBackups();
        $retentionDays = (int) Setting::get('backup_retention_days', 30);
        $lastBackupAt = Setting::get('last_backup_at');
        $lastBackupStatus = Setting::get('last_backup_status', 'never_run');

        $freeDiskSpace = @disk_free_space(storage_path());
        $freeDiskFormatted = $freeDiskSpace !== false ? $this->formatBytes($freeDiskSpace) : 'Unknown';

        return view('admin.system.backups', [
            'title' => 'Database & Asset Backups',
            'backups' => $backups,
            'retentionDays' => $retentionDays,
            'lastBackupAt' => $lastBackupAt ? CarbonImmutable::parse($lastBackupAt) : null,
            'lastBackupStatus' => $lastBackupStatus,
            'freeDiskSpace' => $freeDiskFormatted,
        ]);
    }

    public function create(Request $request): RedirectResponse
    {
        try {
            $type = $request->input('type', 'full');
            $this->backupService->createBackup($type);

            return back()->with('success', 'Backup archive created successfully.');
        } catch (Throwable $e) {
            return back()->with('error', 'Backup creation failed: '.$e->getMessage());
        }
    }

    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $filePath = $this->backupService->getBackupPath($filename);

        if (! $filePath) {
            return back()->with('error', 'Backup archive not found or invalid filename.');
        }

        return response()->download($filePath, basename($filePath), [
            'Content-Type' => 'application/zip',
        ]);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $deleted = $this->backupService->deleteBackup($filename);

        if ($deleted) {
            return back()->with('success', 'Backup archive deleted successfully.');
        }

        return back()->with('error', 'Failed to delete backup archive.');
    }

    protected function formatBytes(float|int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2).' KB';
        }

        return $bytes.' B';
    }
}

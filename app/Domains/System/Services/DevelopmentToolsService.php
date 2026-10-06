<?php

namespace App\Domains\System\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\System\Models\DevelopmentDataOperation;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DevelopmentToolsService
{
    public function __construct(private DevelopmentToolsAccess $access, private DevelopmentDataGraph $graph,
        private DevelopmentDataFiles $files, private DevelopmentDataArchiveService $archives,
        private DevelopmentDataImportService $imports, private DevelopmentDataResetService $resets,
        private DevelopmentDataSnapshotService $snapshots, private ManagedBackupCatalog $managed,
        private AuditLogService $audit, private AvailabilityService $availability) {}

    /** @param array<string, mixed> $scope
     * @return array{operation: DevelopmentDataOperation, token: string, preview: array<string, mixed>} */
    public function preview(Administrator $actor, string $sessionId, string $type, string $domain, array $scope): array
    {
        $actor = $this->access->authorize($actor);
        if (! in_array($type, ['reset', 'export'], true)) {
            throw ValidationException::withMessages(['type' => 'Choose Reset or Export.']);
        }
        if (($type === 'reset' && array_intersect(array_keys($scope), ['modules', 'filters', 'backup_filenames']) !== [])
            || ($type === 'reset' && $domain !== 'resources' && array_intersect(array_keys($scope), ['resources', 'categories', 'history', 'files']) !== [])
            || ($type === 'export' && $domain !== 'selection')) {
            throw ValidationException::withMessages(['scope' => 'That selection does not belong to this operation. Only Resource reset has category/file checkboxes; other resets target the complete domain.']);
        }
        $plan = $this->plan($actor, $type, $domain, $scope);
        $preview = $this->safePreview($plan);

        return $this->issue($actor, $sessionId, $type, $domain, $scope, $preview);
    }

    /** Uploading only creates a private quarantine copy and an inspection token.
     * @return array{operation: DevelopmentDataOperation, token: string, preview: array<string, mixed>} */
    public function upload(Administrator $actor, string $sessionId, UploadedFile $upload): array
    {
        $actor = $this->access->authorize($actor);
        $identity = (string) Str::uuid();
        $path = $this->files->operationPath($identity, 'imports').'.zip';
        $stored = $upload->storeAs(dirname($path), basename($path), (string) config('development_tools.disk'));
        if (! is_string($stored)) {
            throw ValidationException::withMessages(['archive' => 'The archive could not be quarantined privately.']);
        }
        try {
            $archive = $this->archives->read($actor, $path);
            $modules = $archive['manifest']['included_modules'];
            $preview = $this->safeImportPreview($this->imports->preview($actor, $archive, $modules), $archive);

            return $this->issue($actor, $sessionId, 'import', 'selection', ['modules' => $modules], $preview, $path);
        } catch (\Throwable $exception) {
            $this->files->disk()->delete($path);
            throw $exception;
        }
    }

    /** @param list<string> $modules
     * @return array{operation: DevelopmentDataOperation, token: string, preview: array<string, mixed>} */
    public function selectImport(Administrator $actor, string $sessionId, string $token, array $modules): array
    {
        $actor = $this->access->authorize($actor);
        $previous = $this->pending($actor, $sessionId, $token);
        if ($previous->type !== 'import' || ! is_string($previous->archive_path)) {
            throw ValidationException::withMessages(['operation_token' => 'This token is not an import inspection.']);
        }
        $archive = $this->archives->read($actor, $previous->archive_path);
        $preview = $this->safeImportPreview($this->imports->preview($actor, $archive, $modules), $archive);
        $result = $this->issue($actor, $sessionId, 'import', 'selection', ['modules' => $modules, 'selection_confirmed' => true], $preview, $previous->archive_path);
        $previous->update(['status' => 'superseded']);

        return $result;
    }

    /** @return array<string, mixed> */
    public function confirm(Administrator $actor, string $sessionId, #[\SensitiveParameter] string $token,
        #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $code,
        #[\SensitiveParameter] ?string $recoveryCode, string $phrase): array
    {
        $actor = $this->access->authorize($actor);
        $lock = Cache::lock('development-data-operations', 600);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['operation_token' => 'Another data or backup operation is running. Wait and rebuild the preview.']);
        }
        try {
            return DB::transaction(function () use ($actor, $sessionId, $token, $password, $code, $recoveryCode, $phrase): array {
                $operation = DevelopmentDataOperation::query()->where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
                $this->assertBinding($actor, $sessionId, $operation);
                if ($operation->status === 'completed') {
                    return $operation->summary;
                }
                if ($operation->status !== 'pending' || $operation->expires_at->lte(now('UTC'))) {
                    throw ValidationException::withMessages(['operation_token' => 'The operation token expired or has already been consumed. Build a new preview.']);
                }
                if ($operation->type === 'import' && empty($operation->scope['selection_confirmed'])) {
                    throw ValidationException::withMessages(['modules' => 'Choose modules and build the selected restore preview before confirmation.']);
                }
                $expected = $operation->type === 'reset' ? DevelopmentDataCatalog::PHRASES[$operation->domain]
                    : ($operation->type === 'import' ? 'RESTORE MISSING DEVELOPMENT DATA' : 'EXPORT DEVELOPMENT DATA');
                if ($phrase !== $expected) {
                    throw ValidationException::withMessages(['phrase' => 'Type the exact confirmation phrase shown in the preview.']);
                }
                $this->access->confirmOperation($actor, $operation, $password, $code, $recoveryCode);
                $identity = (string) Str::uuid();
                if ($operation->type === 'import') {
                    $archive = $this->archives->read($actor, $operation->archive_path);
                    $preview = $this->safeImportPreview($this->imports->preview($actor, $archive, $operation->scope['modules']), $archive);
                    $this->assertPreview($operation, $preview);
                    $summary = $this->imports->run($actor, $archive, $operation->scope['modules'], $operation);
                    $summary['archive_sha256'] = $archive['sha256'];
                } else {
                    $initial = $this->plan($actor, $operation->type, $operation->domain, $operation->scope);
                    if ($operation->type === 'reset') {
                        $intervals = array_map(fn (array $booking): array => ['start' => CarbonImmutable::parse($booking['start_at_utc'], 'UTC'), 'end' => CarbonImmutable::parse($booking['end_at_utc'], 'UTC')], array_values($initial['rows']['bookings'] ?? []));
                        $this->availability->acquireCalendarDateLocksForIntervals($intervals);
                        $plan = $this->plan($actor, 'reset', $operation->domain, $operation->scope, true);
                    } else {
                        $plan = $initial;
                    }
                    $this->assertPreview($operation, $this->safePreview($plan));
                    if ($operation->type === 'export') {
                        $archive = $this->archives->create($actor, $plan, $plan['managed_snapshots']);
                        $operation->archive_path = $archive['path'];
                        $summary = ['exported_records' => $plan['counts'], 'exported_files' => count($this->files->inventory($plan)),
                            'exported_managed_snapshots' => count($plan['managed_snapshots']), 'archive_id' => $archive['identity'], 'archive_sha256' => $archive['sha256']];
                    } else {
                        $recovery = null;
                        if (config('development_tools.require_snapshot') || ! empty($operation->scope['snapshot'])) {
                            $recovery = $this->snapshots->create($plan, $identity, $operation->domain === 'backups');
                        }
                        if (! empty($operation->scope['export_before'])) {
                            $archive = $this->archives->create($actor, array_replace($plan, ['files' => true]), $plan['managed_snapshots']);
                            $operation->archive_path = $archive['path'];
                        }
                        $summary = $this->resets->run($actor, $operation->domain, $plan, $identity, $operation);
                        $summary['protected_recovery'] = $recovery;
                        if (isset($archive)) {
                            $summary['archive_id'] = $archive['identity'];
                            $summary['archive_sha256'] = $archive['sha256'];
                        }
                    }
                }
                $summary += ['type' => $operation->type, 'domain' => $operation->domain, 'operation_id' => $operation->id];
                $operation->forceFill(['status' => 'completed', 'completed_at' => now('UTC'), 'summary' => $summary])->save();
                $this->audit->log('development_data_'.$operation->type.'_completed', DevelopmentDataOperation::class, $operation->id,
                    newData: ['type' => $operation->type, 'domain' => $operation->domain, 'counts' => $summary, 'archive_sha256' => $summary['archive_sha256'] ?? null], adminId: $actor->id);

                return $summary;
            });
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $scope
     * @return array<string, mixed> */
    private function plan(Administrator $actor, string $type, string $domain, array $scope, bool $lock = false): array
    {
        $plan = $type === 'reset' ? $this->graph->reset($domain, $scope, $lock) : $this->graph->export($scope['modules'] ?? [], $scope['filters'] ?? []);
        $includeBackups = $type === 'reset' ? $domain === 'backups' : in_array('backups.snapshots', $scope['modules'] ?? [], true);
        $plan['managed_snapshots'] = $includeBackups ? $this->managed->selected($scope['backup_filenames'] ?? []) : [];

        return $plan;
    }

    /** @param array<string, mixed> $plan
     * @return array<string, mixed> */
    private function safePreview(array $plan): array
    {
        $inventory = $this->files->inventory($plan);
        $deletable = $this->files->deletable($inventory, $plan);

        return ['counts' => $plan['counts'], 'file_count' => count($inventory), 'deletable_file_count' => count($deletable),
            'student_session_count' => $plan['student_session_count'] ?? 0,
            'shared_files_preserved' => count($inventory) - count($deletable), 'managed_snapshot_count' => count($plan['managed_snapshots']),
            'detached_references' => array_map('count', $plan['detach']), 'warnings' => $plan['warnings'],
            'fingerprint' => hash('sha256', json_encode([$plan['fingerprint'], $inventory, $deletable, $plan['managed_snapshots']], JSON_THROW_ON_ERROR))];
    }

    /** @param array<string, mixed> $preview
     * @param array<string, mixed> $archive
     * @return array<string, mixed> */
    private function safeImportPreview(array $preview, array $archive): array
    {
        unset($preview['rows'], $preview['files']);
        $preview['included_modules'] = $archive['manifest']['included_modules'];
        $preview['archive_sha256'] = $archive['sha256'];
        $preview['archive_id'] = $archive['manifest']['archive_id'];

        return $preview;
    }

    /** @param array<string, mixed> $scope
     * @param array<string, mixed> $preview
     * @return array{operation: DevelopmentDataOperation, token: string, preview: array<string, mixed>} */
    private function issue(Administrator $actor, string $sessionId, string $type, string $domain, array $scope, array $preview, ?string $path = null): array
    {
        $pending = DevelopmentDataOperation::query()->where('administrator_id', $actor->id)->where('status', 'pending')->where('expires_at', '>', now('UTC'))->count();
        if ($pending >= 20) {
            throw ValidationException::withMessages(['operation' => 'Twenty live previews are already open. Finish or let them expire before creating another.']);
        }
        $token = Str::random(64);
        $operation = DevelopmentDataOperation::query()->create(['administrator_id' => $actor->id, 'token_hash' => hash('sha256', $token),
            'session_binding' => $this->access->binding($actor, $sessionId), 'security_fingerprint' => $this->access->fingerprint($actor),
            'type' => $type, 'domain' => $domain, 'scope' => $scope, 'preview' => $preview, 'archive_path' => $path,
            'expires_at' => now('UTC')->addMinutes((int) config('development_tools.token_minutes'))]);

        return compact('operation', 'token', 'preview');
    }

    private function pending(Administrator $actor, string $sessionId, string $token): DevelopmentDataOperation
    {
        $operation = DevelopmentDataOperation::query()->where('token_hash', hash('sha256', $token))->first();
        $this->assertBinding($actor, $sessionId, $operation);
        if ($operation->status !== 'pending' || $operation->expires_at->lte(now('UTC'))) {
            throw ValidationException::withMessages(['operation_token' => 'The operation preview expired. Upload or preview again.']);
        }

        return $operation;
    }

    private function assertBinding(Administrator $actor, string $sessionId, ?DevelopmentDataOperation $operation): void
    {
        if ($operation === null || $operation->administrator_id !== $actor->id
            || ! hash_equals($operation->session_binding, $this->access->binding($actor, $sessionId))
            || ! hash_equals($operation->security_fingerprint, $this->access->fingerprint($actor))) {
            throw ValidationException::withMessages(['operation_token' => 'The operation token is invalid or belongs to another account/session/security state.']);
        }
    }

    /** @param array<string, mixed> $preview */
    private function assertPreview(DevelopmentDataOperation $operation, array $preview): void
    {
        if (! hash_equals($operation->preview['fingerprint'], $preview['fingerprint'])) {
            throw ValidationException::withMessages(['operation_token' => 'Records or files changed after preview. Nothing was reset or imported; build a fresh preview.']);
        }
    }
}

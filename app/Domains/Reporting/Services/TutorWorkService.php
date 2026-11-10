<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Reporting\Models\TutorWorkEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class TutorWorkService
{
    public function authorize(int $actor, bool $lock = false): Administrator
    {
        $query = Administrator::query()->whereKey($actor)->whereNull('suspended_at')->where('role', 'super_admin');
        $administrator = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($administrator !== null, 403);
        Gate::forUser($administrator)->authorize('viewAny', TutorWorkEntry::class);

        return $administrator;
    }

    public function shared(int $actor, int $id): TutorWorkEntry
    {
        $administrator = $this->authorize($actor);
        $workspace = app(TutorWorkWorkspace::class)->ready();
        $entry = TutorWorkEntry::query()->where('workspace_id', $workspace->id)->findOrFail($id);
        Gate::forUser($administrator)->authorize('view', $entry);

        return $entry;
    }

    public function mutate(int $actor, string $action, ?int $id, #[\SensitiveParameter] array $input, ?string $expectedAuthority = null): array
    {
        $this->authorize($actor);
        $data = app(TutorWorkInput::class)->normalize($input, $action);
        $fingerprint = hash('sha256', json_encode([$action, $id, $data], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));

        return $this->privateTransaction(function () use ($actor, $action, $id, $data, $fingerprint, $expectedAuthority): array {
            $administrator = $this->authorize($actor, true);
            if ($expectedAuthority !== null) {
                abort_unless(hash_equals(app(AdministratorTwoFactorService::class)->fingerprint($administrator), $expectedAuthority), 403);
            }
            $workspace = app(TutorWorkWorkspace::class)->ready(true);
            $receipt = DB::table('tutor_work_operations')->where('workspace_id', $workspace->id)->where('actor_id', $actor)->where('operation_id', $data['operation_id'])->lockForUpdate()->first();
            if ($receipt) {
                if (! hash_equals($receipt->fingerprint, $fingerprint)) {
                    $this->conflict($actor, 'This request identity was already used for a different operation.');
                }

                return ['result' => json_decode($receipt->result, true), 'replayed' => true, 'current' => $this->state($actor)];
            }
            $legacyReceipt = DB::table('tutor_work_operations')->whereNull('workspace_id')->where('administrator_id', $actor)->where('operation_id', $data['operation_id'])->lockForUpdate()->first();
            if ($legacyReceipt) {
                if (! hash_equals($legacyReceipt->fingerprint, $fingerprint)) {
                    $this->conflict($actor, 'This identity belongs to a different committed legacy operation.');
                }
                $legacyResult = json_decode($legacyReceipt->result, true, flags: JSON_THROW_ON_ERROR);
                if (! TutorWorkEntry::where('workspace_id', $workspace->id)->whereKey($legacyResult['entry_id'] ?? 0)->exists()) {
                    $this->conflict($actor, 'This committed legacy request remains outside the shared ledger. It was not repeated. Refresh the interface before making a new declaration.');
                }

                return ['result' => $legacyResult, 'replayed' => true, 'legacy_receipt' => true, 'current' => $this->state($actor)];
            }
            $entry = $id === null ? null : TutorWorkEntry::where('workspace_id', $workspace->id)->lockForUpdate()->findOrFail($id);
            if ($entry) {
                Gate::forUser($administrator)->authorize('update', $entry);
            }
            $now = CarbonImmutable::now('UTC')->startOfSecond();
            if ($entry && $entry->version !== $data['version']) {
                $this->conflict($actor, 'This record changed in another tab or session. Review its current version before retrying.', $entry);
            }
            $before = $entry ? $this->snapshot($entry) : [];
            if (in_array($action, ['start', 'manual'], true)) {
                if ($action === 'start' && TutorWorkEntry::where('active_workspace', $workspace->id)->lockForUpdate()->first() !== null) {
                    $this->conflict($actor, 'A timer is already open. Resume or stop that timer first.');
                }
                $entry = TutorWorkEntry::create(['workspace_id' => $workspace->id, 'created_by' => $actor, 'active_workspace' => $action === 'start' ? $workspace->id : null, 'origin' => $action === 'start' ? 'timer' : 'manual', 'placement' => $action === 'start' ? 'placed' : $data['placement'], 'lifecycle' => $action === 'start' ? 'running' : 'stopped', 'acceptance' => $action === 'start' ? 'provisional' : 'accepted', 'category' => $data['category'], 'description' => $data['description'], 'timezone' => $data['timezone'], 'work_date' => $data['work_date'] ?? $now->setTimezone($data['timezone'])->toDateString(), 'unplaced_seconds' => $data['unplaced_seconds'] ?? null, 'original_declaration' => $data]);
                if ($action === 'start') {
                    $this->openSegment($entry, $now, $actor);
                } else {
                    $this->replaceIntervals($entry, $data['intervals']);
                    $entry->acceptance = $this->requiresReview($entry) ? 'needs_review' : 'accepted';
                }
            } elseif (in_array($action, ['pause', 'resume', 'stop'], true)) {
                $allowed = ['pause' => ['running'], 'resume' => ['paused'], 'stop' => ['running', 'paused']][$action];
                if ($entry->origin !== 'timer' || ! in_array($entry->lifecycle, $allowed, true)) {
                    $this->conflict($actor, 'That timer action is no longer available. Check its current state.', $entry);
                }
                if ($entry->lifecycle === 'running') {
                    $this->closeSegment($entry, $now, $actor);
                }
                if ($action === 'resume') {
                    $this->openSegment($entry, $now, $actor);
                }
                $entry->lifecycle = match ($action) {
                    'pause' => 'paused', 'resume' => 'running', 'stop' => 'stopped'
                };
                if ($action === 'stop') {
                    $this->finalize($entry);
                }
                $entry->version++;
            } else {
                if ($entry->lifecycle !== 'stopped') {
                    $this->conflict($actor, 'Stop the timer before correcting or reviewing this record.', $entry);
                }
                if ($action === 'exclude') {
                    $this->requireReason($data);
                    $entry->acceptance = 'excluded';
                } elseif ($action === 'confirm') {
                    if ($entry->acceptance !== 'needs_review' || $this->seconds($entry) <= 0) {
                        $this->conflict($actor, 'Only a positive needs-review record can be confirmed.', $entry);
                    }
                    $entry->acceptance = 'accepted';
                } else {
                    $timingChanged = $entry->placement !== $data['placement'] || $entry->timezone !== $data['timezone'] || $entry->work_date !== $data['work_date'] || $entry->unplaced_seconds !== $data['unplaced_seconds'] || $this->intervals($entry) !== $data['intervals'];
                    if ($timingChanged) {
                        $this->requireReason($data);
                    }
                    $entry->fill(array_intersect_key($data, array_flip(['category', 'description', 'timezone', 'work_date', 'placement', 'unplaced_seconds'])));
                    $this->replaceIntervals($entry, $data['intervals']);
                    $entry->corrected = true;
                    if ($entry->acceptance !== 'excluded' && $timingChanged) {
                        $entry->acceptance = $this->requiresReview($entry) ? 'needs_review' : 'accepted';
                    }
                }
                $entry->version++;
            }
            $entry->save();
            $after = $this->snapshot($entry);
            $this->revision($entry, $action, $before, $after, $data['reason'], $now, $actor);
            $result = ['entry_id' => $entry->id, 'version' => $entry->version, 'lifecycle' => $entry->lifecycle, 'acceptance' => $entry->acceptance];
            DB::table('tutor_work_operations')->insert(['workspace_id' => $workspace->id, 'actor_id' => $actor, 'operation_id' => $data['operation_id'], 'fingerprint' => $fingerprint, 'result' => json_encode($result, JSON_THROW_ON_ERROR), 'created_at' => $now]);

            return ['result' => $result, 'replayed' => false, 'current' => $this->state($actor)];
        }, 5);
    }

    private function requireReason(array $data): void
    {
        if ($data['reason'] === '') {
            throw ValidationException::withMessages(['reason' => 'Enter a short private reason for changing timing or excluding this record.']);
        }
    }

    private function conflict(int $actor, string $message, ?TutorWorkEntry $entry = null): never
    {
        throw new HttpResponseException(response()->json(['message' => $message, 'current' => $this->state($actor), 'record' => $entry ? $this->snapshot($entry) : null], 409)->header('Cache-Control', 'private, no-store'));
    }

    private function openSegment(TutorWorkEntry $entry, CarbonImmutable $now, int $actor): void
    {
        DB::table('tutor_work_segments')->insert(['entry_id' => $entry->id, 'started_at' => $now, 'open_entry' => $entry->id, 'opened_by' => $actor]);
    }

    private function closeSegment(TutorWorkEntry $entry, CarbonImmutable $now, ?int $actor): void
    {
        DB::table('tutor_work_segments')->where('open_entry', $entry->id)->update(['ended_at' => $now, 'open_entry' => null, 'closed_by' => $actor]);
    }

    private function finalize(TutorWorkEntry $entry, bool $revoked = false): void
    {
        $entry->active_owner = null;
        $entry->active_workspace = null;
        $entry->lifecycle = 'stopped';
        $segments = DB::table('tutor_work_segments')->where('entry_id', $entry->id)->whereNotNull('ended_at')->whereColumn('ended_at', '>', 'started_at')->orderBy('id')->lockForUpdate()->get(['started_at', 'ended_at'])->map(fn (object $row): array => (array) $row)->all();
        $this->replaceIntervals($entry, $segments);
        $entry->acceptance = $revoked ? 'needs_review' : ($this->seconds($entry) === 0 ? 'excluded' : ($this->requiresReview($entry) ? 'needs_review' : 'accepted'));
        if ($entry->acceptance === 'excluded') {
            $entry->system_reason = 'zero_duration';
        }
    }

    public function revoke(int $subject, string $reason): void
    {
        if (! Schema::hasTable('tutor_work_entries')) {
            return;
        }
        $this->privateTransaction(function () use ($subject, $reason): void {
            Administrator::withTrashed()->whereKey($subject)->lockForUpdate()->firstOrFail();
            $workspace = app(TutorWorkWorkspace::class)->find(true);
            if (! $workspace || $workspace->transition_state === 'schema_pending') {
                $this->revokeBeforeUpgrade($subject, $reason);

                return;
            }
            $entry = TutorWorkEntry::where('active_workspace', $workspace->id)->where('lifecycle', 'running')->lockForUpdate()->first();
            if ($entry) {
                $opener = DB::table('tutor_work_segments')->where('open_entry', $entry->id)->lockForUpdate()->value('opened_by');
                if ((int) $opener === $subject) {
                    $this->freeze($entry, CarbonImmutable::now('UTC')->startOfSecond(), 'security_revocation', $reason, $subject);
                }
            }
            $legacy = TutorWorkEntry::whereNull('workspace_id')->where('active_owner', $subject)->lockForUpdate()->first();
            if ($legacy) {
                $this->freeze($legacy, CarbonImmutable::now('UTC')->startOfSecond(), 'security_revocation', $reason, $subject);
            }
        }, 5);
    }

    public function freeze(TutorWorkEntry $entry, CarbonImmutable $now, string $action, string $reason, ?int $subject = null): void
    {
        $before = $this->snapshot($entry);
        if ($entry->lifecycle === 'running') {
            $this->closeSegment($entry, $now, null);
        }
        $this->finalize($entry, true);
        $entry->system_reason = $reason;
        $entry->version++;
        $entry->save();
        $this->revision($entry, $action, $before, $this->snapshot($entry), $reason, $now, null, $subject);
    }

    /** Keep trusted account revocations safe while the additive schema upgrade awaits approval. */
    private function revokeBeforeUpgrade(int $subject, string $reason): void
    {
        $entry = TutorWorkEntry::where('active_owner', $subject)->lockForUpdate()->first();
        if (! $entry) {
            return;
        }
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $before = $this->snapshot($entry);
        DB::table('tutor_work_segments')->where('open_entry', $entry->id)->update(['ended_at' => $now, 'open_entry' => null]);
        $intervals = DB::table('tutor_work_segments')->where('entry_id', $entry->id)->whereNotNull('ended_at')->whereColumn('ended_at', '>', 'started_at')->lockForUpdate()->get(['started_at', 'ended_at']);
        DB::table('tutor_work_intervals')->where('entry_id', $entry->id)->delete();
        foreach ($intervals as $interval) {
            DB::table('tutor_work_intervals')->insert((array) $interval + ['entry_id' => $entry->id, 'administrator_id' => $subject]);
        }
        $entry->fill(['active_owner' => null, 'lifecycle' => 'stopped', 'acceptance' => 'needs_review', 'system_reason' => $reason, 'version' => $entry->version + 1])->save();
        DB::table('tutor_work_revisions')->insert(['entry_id' => $entry->id, 'administrator_id' => $subject, 'from_version' => $entry->version - 1, 'to_version' => $entry->version, 'action' => 'security_revocation', 'reason' => $reason, 'before_value' => json_encode($before, JSON_THROW_ON_ERROR), 'after_value' => json_encode($this->snapshot($entry), JSON_THROW_ON_ERROR), 'created_at' => $now]);
    }

    private function revision(TutorWorkEntry $entry, string $action, array $before, array $after, string $reason, CarbonImmutable $now, ?int $actor, ?int $subject = null): void
    {
        DB::table('tutor_work_revisions')->insert(['entry_id' => $entry->id, 'workspace_id' => $entry->workspace_id, 'administrator_id' => $entry->administrator_id, 'actor_id' => $actor, 'subject_administrator_id' => $subject, 'system_action' => $actor === null, 'from_version' => max(0, $entry->version - 1), 'to_version' => $entry->version, 'action' => $action, 'reason' => $reason ?: null, 'before_value' => json_encode($before, JSON_THROW_ON_ERROR), 'after_value' => json_encode($after, JSON_THROW_ON_ERROR), 'created_at' => $now]);
    }

    /** Database errors can embed private authored values in SQL bindings. Never propagate those payloads to general logs. */
    private function privateTransaction(\Closure $callback, int $attempts = 5): mixed
    {
        try {
            return DB::transaction($callback, $attempts);
        } catch (QueryException) {
            throw new ServiceUnavailableHttpException(null, 'The private work operation could not be committed. Refresh saved state and deliberately retry.');
        }
    }

    public function intervals(TutorWorkEntry $entry): array
    {
        return DB::table('tutor_work_intervals')->where('entry_id', $entry->id)->orderBy('started_at')->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->get(['started_at', 'ended_at'])->map(fn (object $row): array => (array) $row)->all();
    }

    private function replaceIntervals(TutorWorkEntry $entry, array $intervals): void
    {
        DB::table('tutor_work_intervals')->where('entry_id', $entry->id)->delete();
        foreach ($intervals as $interval) {
            DB::table('tutor_work_intervals')->insert($interval + ['entry_id' => $entry->id, 'workspace_id' => $entry->workspace_id, 'administrator_id' => $entry->administrator_id]);
        }
    }

    public function seconds(TutorWorkEntry $entry): int
    {
        return $entry->placement === 'unplaced' ? (int) $entry->unplaced_seconds : (int) DB::table('tutor_work_intervals')->where('entry_id', $entry->id)->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->sum(DB::raw('TIMESTAMPDIFF(SECOND, started_at, ended_at)'));
    }

    private function requiresReview(TutorWorkEntry $entry): bool
    {
        return $this->seconds($entry) >= (int) config('tutor_work.review_seconds') || ($entry->placement === 'placed' && DB::table('tutor_work_intervals')->where('entry_id', $entry->id)->whereRaw('TIMESTAMPDIFF(SECOND, started_at, ended_at) >= ?', [(int) config('tutor_work.review_seconds')])->exists());
    }

    public function snapshot(TutorWorkEntry $entry): array
    {
        return $entry->attributesToArray() + ['description' => $entry->description, 'intervals' => $this->intervals($entry)];
    }

    public function state(int $actor): array
    {
        return DB::transaction(fn (): array => $this->lockedState($actor), 5);
    }

    private function lockedState(int $actor): array
    {
        $this->authorize($actor, true);
        $now = CarbonImmutable::now('UTC')->startOfSecond();
        $workspace = app(TutorWorkWorkspace::class)->ready(true);
        $entry = TutorWorkEntry::where('active_workspace', $workspace->id)->lockForUpdate()->first();
        $active = null;
        if ($entry) {
            $segments = DB::table('tutor_work_segments')->where('entry_id', $entry->id)->selectRaw('SUM(GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, ?)))) AS duration, MAX(GREATEST(0, TIMESTAMPDIFF(SECOND, started_at, COALESCE(ended_at, ?)))) AS longest', [$now->toDateTimeString(), $now->toDateTimeString()])->lockForUpdate()->first();
            $duration = (int) $segments->duration;
            $longest = (int) $segments->longest;
            $active = ['id' => $entry->id, 'version' => $entry->version, 'lifecycle' => $entry->lifecycle, 'category' => $entry->category, 'category_label' => TutorWorkInput::CATEGORIES[$entry->category], 'elapsed_seconds' => $duration, 'warning' => $longest >= (int) config('tutor_work.warning_seconds'), 'review_due' => max($duration, $longest) >= (int) config('tutor_work.review_seconds')];
        }

        return ['actor_id' => $actor, 'workspace_id' => $workspace->id, 'context_version' => TutorWorkWorkspace::PROTOCOL, 'sequence' => (int) DB::table('tutor_work_revisions')->where('workspace_id', $workspace->id)->orderByDesc('id')->lockForUpdate()->value('id'), 'server_at' => $now->toIso8601String(), 'timer' => $active];
    }
}

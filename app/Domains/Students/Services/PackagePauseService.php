<?php

namespace App\Domains\Students\Services;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class PackagePauseService
{
    public function __construct(
        private DatabaseCapability $database,
        private EntitlementService $entitlements,
        private AuditLogService $audit,
        private TimezoneService $timezones,
    ) {}

    public function pause(StudentPackage $package, string $reason, int $administratorId): void
    {
        $this->database->transaction(function () use ($package, $reason, $administratorId): void {
            $locked = $this->lockPackage($package);
            if (! $this->entitlements->eligible($locked)) {
                throw ValidationException::withMessages(['package' => 'Only an active, eligible package can be paused.']);
            }
            $future = Booking::query()->where('student_package_id', $locked->id)
                ->where('start_at_utc', '>=', now('UTC'))->whereIn('status', ['confirmed', 'held', 'pending'])->exists();
            if ($future) {
                throw ValidationException::withMessages(['package' => 'Cancel future scheduled sessions funded by this package before pausing it.']);
            }
            $locked->update(['paused_at' => now('UTC'), 'pause_reason' => trim($reason), 'paused_by' => $administratorId]);
            $this->audit->log('package_paused', StudentPackage::class, $locked->id, null, ['reason' => trim($reason), 'paused_at' => $locked->paused_at->toIso8601String()], $administratorId);
        }, 5);
    }

    public function resume(StudentPackage $package, int $administratorId): void
    {
        $this->database->transaction(function () use ($package, $administratorId): void {
            $locked = $this->lockPackage($package);
            $resumedAt = CarbonImmutable::now('UTC')->startOfSecond();
            if (! $locked->isPaused() || $locked->paused_at->greaterThan($resumedAt)) {
                throw ValidationException::withMessages(['package' => 'This package does not have a valid current pause.']);
            }
            $seconds = $resumedAt->timestamp - $locked->paused_at->timestamp;
            $deadline = $locked->expiryDeadline();
            $updates = ['paused_at' => null, 'pause_reason' => null, 'paused_by' => null];
            if ($deadline !== null) {
                $extended = $deadline->startOfSecond()->addSeconds($seconds);
                $updates['expires_at'] = $extended;
                $updates['expiration_date'] = $extended->setTimezone($this->timezones->getBusinessTimezone())->toDateString();
            }
            $previous = ['paused_at' => $locked->paused_at->toIso8601String(), 'expiry' => $deadline?->toIso8601String()];
            $locked->update($updates);
            $this->audit->log('package_resumed', StudentPackage::class, $locked->id, $previous, [
                'seconds_paused' => $seconds, 'resumed_at' => $resumedAt->toIso8601String(), 'expiry' => $locked->expiryDeadline()?->toIso8601String(),
            ], $administratorId);
        }, 5);
    }

    private function lockPackage(StudentPackage $package): StudentPackage
    {
        $student = Student::withTrashed()->whereKey($package->student_id)->lockForUpdate()->firstOrFail();
        $locked = StudentPackage::query()->whereKey($package->id)->lockForUpdate()->firstOrFail();
        if ($student->trashed() || $student->identity_status === 'merged' || (int) $locked->student_id !== (int) $student->id) {
            throw ValidationException::withMessages(['package' => 'This package does not belong to an active student record.']);
        }

        return $locked;
    }
}

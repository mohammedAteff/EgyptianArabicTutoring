<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Lms\Models\AccessEvent;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\Section;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LmsAccessOperations
{
    public function __construct(private LmsAccessWindow $windows, private TeachingRecordService $students, private AuditLogService $audits) {}

    /** @param array<string, mixed> $data */
    public function grant(Administrator $actor, Student $student, Course|Section|Lesson $target, array $data, string $key): AccessGrant
    {
        return $this->issue($actor, $student, $target, $data, $key, 'grant');
    }

    /** @param array<string, mixed> $data */
    public function enroll(Administrator $actor, Student $student, Course $course, array $data, string $key): AccessGrant
    {
        return $this->issue($actor, $student, $course, $data, $key, 'enroll');
    }

    /** @param array<string, mixed> $data */
    public function assign(Administrator $actor, Student $student, Course|Section|Lesson $target, array $data, string $key): LearningAssignment
    {
        $grant = $this->issue($actor, $student, $target, $data, $key, 'assign');

        return LearningAssignment::query()->where('access_grant_id', $grant->id)->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function regrant(Administrator $actor, AccessGrant $previous, array $data, string $key): AccessGrant
    {
        Gate::forUser($actor)->authorize('manage', $previous);
        $previous = AccessGrant::query()->findOrFail($previous->id);
        abort_unless($previous->source_kind === 'manual', 409, 'Private assignments must be assigned explicitly rather than revived.');
        $target = $previous->lesson ?? $previous->section ?? $previous->course;
        abort_unless($target instanceof Course || $target instanceof Section || $target instanceof Lesson, 404);
        $student = Student::query()->findOrFail($previous->student_id);
        $data['source_key'] = 'regrant:'.$previous->id.':'.$this->operationKey($actor, $key);
        $data['regranted_from_id'] = $previous->id;

        return $this->issue($actor, $student, $target, $data, $key, 'regrant');
    }

    /** @param array<string, mixed> $data */
    private function issue(Administrator $actor, Student $student, Course|Section|Lesson $target, array $data, string $key, string $action): AccessGrant
    {
        Gate::forUser($actor)->authorize('manage', $target);
        $operationKey = $this->operationKey($actor, $key);
        $window = $this->windows->normalize($data);
        $values = Validator::make($data, [
            'source_key' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/D'],
            'reason' => ['nullable', 'string', 'max:500'],
            'metadata' => ['nullable', 'array:reason_code,reference_id'],
            'metadata.reason_code' => ['sometimes', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/D'],
            'metadata.reference_id' => ['sometimes', 'integer', 'min:1'],
            'booking_id' => ['nullable', 'integer', 'min:1'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'regranted_from_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        if ($action !== 'assign' && (! empty($values['booking_id']) || isset($values['instructions']))) {
            throw ValidationException::withMessages(['booking_id' => 'Tutoring context belongs only to a private learning assignment.']);
        }
        $sourceKind = $action === 'assign' ? 'private_assignment' : 'manual';
        $sourceKey = isset($values['source_key']) ? hash('sha256', $values['source_key']) : $operationKey;
        $target = $target->newQuery()->findOrFail($target->id);
        $scope = $this->scope($target);
        $fingerprint = hash('sha256', json_encode([$action, $student->id, $scope, $sourceKind, $sourceKey, $window,
            $values['reason'] ?? null, $values['metadata'] ?? null, $values['booking_id'] ?? null, $values['instructions'] ?? null,
            $values['regranted_from_id'] ?? null], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($actor, $student, $scope, $values, $sourceKind, $sourceKey, $window, $fingerprint, $operationKey, $action): AccessGrant {
                $lockedStudent = $this->students->lockStudent($student->id);
                $booking = $this->students->lockBooking($lockedStudent, isset($values['booking_id']) ? (int) $values['booking_id'] : null);
                $course = Course::query()->lockForUpdate()->findOrFail($scope['course_id']);
                Gate::forUser($actor)->authorize('manage', $course);
                abort_if($course->kind === 'private' && (int) $course->owner_student_id !== (int) $lockedStudent->id, 404);
                if ($event = $this->replay($operationKey, $fingerprint)) {
                    return AccessGrant::query()->findOrFail($event->access_grant_id);
                }
                if (isset($values['regranted_from_id'])) {
                    $prior = AccessGrant::query()->where('student_id', $lockedStudent->id)->where('course_id', $course->id)->lockForUpdate()->findOrFail($values['regranted_from_id']);
                    abort_unless($prior->source_kind === 'manual', 409);
                }
                $scopeKey = $scope['lesson_id'] ? 'lesson:'.$scope['lesson_id'] : ($scope['section_id'] ? 'section:'.$scope['section_id'] : 'course');
                $grant = AccessGrant::query()->where('source_kind', $sourceKind)->where('source_key', $sourceKey)->where('scope_key', $scopeKey)->lockForUpdate()->first();
                if ($grant) {
                    abort_unless((int) $grant->student_id === (int) $lockedStudent->id && hash_equals($grant->issuance_fingerprint, $fingerprint), 409, 'The access source belongs to different original terms.');
                    $this->record($actor, $lockedStudent, $grant, $operationKey, $fingerprint, $action.'_reused');

                    return $grant;
                }
                $captured = $this->windows->capture($window);
                $grant = new AccessGrant;
                $grant->forceFill($scope + $captured + ['student_id' => $lockedStudent->id, 'source_kind' => $sourceKind, 'source_key' => $sourceKey,
                    'issuance_fingerprint' => $fingerprint, 'granted_by' => $actor->id, 'reason' => $values['reason'] ?? null,
                    'metadata' => $values['metadata'] ?? null, 'regranted_from_id' => $values['regranted_from_id'] ?? null, 'status' => 'active', 'lock_version' => 1])->save();
                $enrollment = Enrollment::query()->where('student_id', $lockedStudent->id)->where('course_id', $course->id)->where('status', '!=', 'superseded')->lockForUpdate()->first();
                if (! $enrollment) {
                    $enrollment = new Enrollment;
                    $enrollment->forceFill(['student_id' => $lockedStudent->id, 'course_id' => $course->id, 'enrolled_at' => now('UTC'), 'enrolled_by' => $actor->id])->save();
                }
                $assignment = null;
                if ($action === 'assign') {
                    $assignment = new LearningAssignment;
                    $assignment->forceFill(['access_grant_id' => $grant->id, 'booking_id' => $booking?->id, 'assigned_by' => $actor->id, 'instructions' => $values['instructions'] ?? null])->save();
                }
                $this->record($actor, $lockedStudent, $grant, $operationKey, $fingerprint, $action, $enrollment, $assignment);
                $this->audits->log('lms_access_'.$action, AccessGrant::class, $grant->id, null,
                    ['student_id' => $lockedStudent->id, 'course_id' => $course->id, 'status' => 'active'], $actor->id);

                return $grant;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'An access source or operation key was already used.');
        }
    }

    /** @param array<string, mixed> $data */
    public function change(Administrator $actor, AccessGrant $grant, string $action, array $data, int $expectedVersion, string $key): AccessGrant
    {
        Gate::forUser($actor)->authorize('manage', $grant);
        abort_unless(in_array($action, ['revoke', 'extend', 'set_expiration', 'remove_expiration', 'set_start'], true), 404);
        $operationKey = $this->operationKey($actor, $key);
        $values = [];
        if (in_array($action, ['extend', 'set_expiration', 'set_start'], true)) {
            $field = $action === 'set_start' ? 'starts_at' : 'expires_at';
            $valid = Validator::make($data, [$field => ['required', 'string']])->validate();
            $values[$field] = $this->windows->instant($valid[$field]);
        }
        $fingerprint = hash('sha256', json_encode([$action, $grant->id, $values, $expectedVersion], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($actor, $grant, $action, $values, $expectedVersion, $operationKey, $fingerprint): AccessGrant {
                $current = AccessGrant::query()->findOrFail($grant->id);
                $student = $this->students->lockStudent($current->student_id);
                Course::query()->lockForUpdate()->findOrFail($current->course_id);
                $current = AccessGrant::query()->lockForUpdate()->findOrFail($grant->id);
                Gate::forUser($actor)->authorize('manage', $current);
                if ($event = $this->replay($operationKey, $fingerprint)) {
                    return AccessGrant::query()->findOrFail($event->access_grant_id);
                }
                abort_if($current->lock_version !== $expectedVersion, 409, 'The access grant changed. Reload before updating.');
                if ($action !== 'revoke') {
                    abort_if($current->status === 'revoked', 409, 'Revoked access needs an explicit new grant.');
                }
                $before = $current->only(['status', 'access_mode', 'starts_at', 'expires_at', 'relative_days', 'lock_version']);
                $changes = match ($action) {
                    'revoke' => ['status' => 'revoked', 'revoked_at' => $current->revoked_at ?? now('UTC')],
                    'extend', 'set_expiration' => ['access_mode' => 'fixed', 'expires_at' => $values['expires_at'], 'relative_days' => null],
                    'remove_expiration' => ['access_mode' => 'permanent', 'expires_at' => null, 'relative_days' => null],
                    'set_start' => ['starts_at' => $values['starts_at']],
                };
                if ($action === 'extend') {
                    if (! $current->expires_at || $values['expires_at'] <= $current->expires_at->format('Y-m-d H:i:s')) {
                        throw ValidationException::withMessages(['expires_at' => 'Extend an existing expiration strictly forward.']);
                    }
                }
                if ($action === 'set_start' && $current->access_mode === 'relative') {
                    $changes['expires_at'] = CarbonImmutable::parse($values['starts_at'], 'UTC')->addSeconds($current->relative_days * 86400)->format('Y-m-d H:i:s');
                }
                $current->forceFill($changes);
                if ($current->expires_at && $current->expires_at->lte($current->starts_at)) {
                    throw ValidationException::withMessages(['expires_at' => 'Expiration must follow the start.']);
                }
                $current->forceFill(['lock_version' => $current->lock_version + 1])->save();
                $this->record($actor, $student, $current, $operationKey, $fingerprint, $action);
                $this->audits->log('lms_access_'.$action, AccessGrant::class, $current->id, ['status' => $before['status']],
                    ['student_id' => $student->id, 'course_id' => $current->course_id, 'status' => $current->status, 'lock_version' => $current->lock_version], $actor->id);

                return $current;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'An access operation key was already used.');
        }
    }

    /** @return array{course_id: int, section_id: ?int, lesson_id: ?int} */
    private function scope(Course|Section|Lesson $target): array
    {
        return ['course_id' => (int) ($target instanceof Course ? $target->id : $target->course_id),
            'section_id' => $target instanceof Section ? (int) $target->id : null,
            'lesson_id' => $target instanceof Lesson ? (int) $target->id : null];
    }

    private function operationKey(Administrator $actor, string $key): string
    {
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'max:160', 'regex:/^[A-Za-z0-9._:-]+$/D']])->validate();

        return hash('sha256', $actor->id.':'.$key);
    }

    private function replay(string $key, string $fingerprint): ?AccessEvent
    {
        $event = AccessEvent::query()->where('operation_key', $key)->lockForUpdate()->first();
        if ($event) {
            abort_unless(hash_equals($event->fingerprint, $fingerprint), 409, 'The operation key has different original terms.');
        }

        return $event;
    }

    private function record(Administrator $actor, Student $student, AccessGrant $grant, string $key, string $fingerprint, string $action, ?Enrollment $enrollment = null, ?LearningAssignment $assignment = null): void
    {
        $event = new AccessEvent;
        $event->forceFill(['operation_key' => $key, 'fingerprint' => $fingerprint, 'action' => $action, 'student_id' => $student->id,
            'course_id' => $grant->course_id, 'access_grant_id' => $grant->id, 'enrollment_id' => $enrollment?->id,
            'learning_assignment_id' => $assignment?->id, 'actor_id' => $actor->id, 'created_at' => now('UTC'),
            'changes' => ['actor_type' => 'admin', 'actor_account_id' => $actor->id, 'status' => $grant->status,
                'access_mode' => $grant->access_mode, 'starts_at' => $grant->starts_at->format('Y-m-d H:i:s'),
                'expires_at' => $grant->expires_at?->format('Y-m-d H:i:s'), 'lock_version' => $grant->lock_version]])->save();
    }
}

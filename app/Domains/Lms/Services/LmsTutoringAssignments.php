<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\AccessEvent;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\TeachingRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsTutoringAssignments
{
    public function __construct(private TeachingRecordService $teaching, private LmsAccessOperations $operations,
        private LmsAccessWindow $windows, private CourseStudioService $studio, private LmsVideoProfiles $profiles) {}

    public function authorize(Administrator $actor, Student $student): void
    {
        Gate::forUser($actor)->authorize('manageTeaching', $student);
        Gate::forUser($actor)->authorize('manage', Course::class);
    }

    /** @param array<string,mixed> $data
     * @return array{record:LearningAssignment|ResourceAssignment,reused:bool} */
    public function assign(Administrator $actor, Student $student, array $data): array
    {
        $this->authorize($actor, $student);
        $values = Validator::make($data, [
            'kind' => ['required', Rule::in(['course', 'lesson', 'resource', 'quiz', 'assignment'])],
            'target_id' => ['required', 'integer', 'min:1'], 'request_key' => ['required', 'uuid'],
            'booking_id' => ['nullable', 'integer', 'min:1'], 'instructions' => ['nullable', 'string', 'max:5000'],
        ])->validate();
        $window = $this->windows->normalize($data);
        if ($values['kind'] === 'resource' && ($window['access_mode'] !== 'permanent' || ! empty($window['starts_at']) || ! empty($window['expires_at']))) {
            throw ValidationException::withMessages(['access_mode' => 'Resource assignments use the existing sharing controls. Expiry applies to LMS learning.']);
        }

        return DB::transaction(function () use ($actor, $student, $values, $window): array {
            $student = $this->teaching->lockStudent($student->id);
            $this->authorize($actor, $student);
            abort_unless($student->identity_status === 'verified', 422, 'Verify this Student before assigning LMS learning.');
            $booking = $this->booking($student, $values['booking_id'] ?? null);
            if ($values['kind'] === 'resource') {
                $resource = Resource::query()->published()->lockForUpdate()->findOrFail($values['target_id']);
                $existing = ResourceAssignment::query()->where('student_id', $student->id)->where('resource_id', $resource->id)
                    ->where('booking_id', $booking?->id)->where('student_visible', true)->lockForUpdate()->first();
                if ($existing) {
                    return ['record' => $existing, 'reused' => true];
                }
                $record = $this->teaching->save($student, 'resource', ['resource_id' => $resource->id, 'booking_id' => $booking?->id,
                    'instructions' => $values['instructions'] ?? null, 'student_visible' => true], $actor->id);
                if (! $record instanceof ResourceAssignment) {
                    throw new \LogicException('Resource sharing must use the canonical Resource assignment.');
                }

                return ['record' => $record, 'reused' => false];
            }
            [$course, $target, $block] = $this->target($student, $values['kind'], (int) $values['target_id']);
            $key = 'profile:'.$student->id.':'.$values['request_key'];
            $terms = $this->windowInput($window) + ['booking_id' => $booking?->id, 'instructions' => $values['instructions'] ?? null,
                'lesson_block_id' => $block?->id];
            if (AccessEvent::query()->where('operation_key', hash('sha256', $actor->id.':'.$key))->exists()) {
                return ['record' => $this->operations->assign($actor, $student, $target, $terms, $key), 'reused' => true];
            }
            $enrolled = Enrollment::query()->where('student_id', $student->id)->where('course_id', $course->id)->where('status', 'enrolled')->exists();
            $existing = $enrolled ? LearningAssignment::query()->where('status', 'assigned')->where('booking_id', $booking?->id)->where('lesson_block_id', $block?->id)
                ->whereHas('accessGrant', fn ($query) => $query->where('student_id', $student->id)->where('course_id', $course->id)
                    ->where('lesson_id', $target instanceof Lesson ? $target->id : null)->whereNull('section_id')->where('status', 'active')
                    ->where(fn ($ends) => $ends->whereNull('expires_at')->orWhere('expires_at', '>', now('UTC'))))->lockForUpdate()->first() : null;
            if ($existing) {
                return ['record' => $existing, 'reused' => true];
            }

            return ['record' => $this->operations->assign($actor, $student, $target, $terms, $key), 'reused' => false];
        }, 3);
    }

    /** @return array{Course,Course|Lesson,LessonBlock|null} */
    private function target(Student $student, string $kind, int $id): array
    {
        $block = in_array($kind, ['quiz', 'assignment'], true) ? LessonBlock::query()->with('lesson.course', 'lesson.section')->where('kind', $kind)->where('status', 'ready')->findOrFail($id) : null;
        $target = $block ? $block->lesson : ($kind === 'course' ? Course::query()->findOrFail($id) : Lesson::query()->with(['course', 'section'])->findOrFail($id));
        $course = $target instanceof Course ? $target : $target->course;
        if ($course && DB::transactionLevel() > 0) {
            $course = Course::query()->lockForUpdate()->findOrFail($course->id);
            if ($target instanceof Lesson) {
                $target = Lesson::query()->where('course_id', $course->id)->lockForUpdate()->findOrFail($target->id);
                $target->setRelation('section', Section::query()->where('course_id', $course->id)->lockForUpdate()->findOrFail($target->section_id));
            } else {
                $target = $course;
            }
            if ($block) {
                $block = LessonBlock::query()->where('lesson_id', $target->id)->where('kind', $kind)->where('status', 'ready')->lockForUpdate()->findOrFail($block->id);
            }
        }
        abort_unless($course && $course->status === 'published' && $course->published_at?->lte(now('UTC')), 404);
        abort_if($course->kind === 'private' && (int) $course->owner_student_id !== (int) $student->id, 404);
        if ($target instanceof Lesson) {
            abort_unless($target->status === 'published' && $target->published_at?->lte(now('UTC')) && $target->section?->status === 'published'
                && $target->section->published_at?->lte(now('UTC')), 404);
        }

        return [$course, $target, $block];
    }

    private function booking(Student $student, ?int $id): ?Booking
    {
        $booking = $this->teaching->lockBooking($student, $id);
        abort_if($booking && ! in_array($booking->status, ['confirmed', 'completed'], true), 422, 'Choose a confirmed or delivered session for this Student.');

        return $booking;
    }

    /** @param list<int> $studentIds
     * @param array<string,mixed> $data
     * @return list<array{record:LearningAssignment|ResourceAssignment,reused:bool}> */
    public function bulk(Administrator $actor, array $studentIds, array $data): array
    {
        Validator::make($data, ['kind' => ['required', Rule::in(['course', 'lesson', 'resource', 'quiz', 'assignment'])], 'target_id' => ['required', 'integer', 'min:1']])->validate();
        Validator::make(['students' => $studentIds], ['students' => ['required', 'array', 'min:1', 'max:25'], 'students.*' => ['required', 'integer', 'min:1', 'distinct']])->validate();
        abort_if(! empty($data['booking_id']), 422, 'Bulk assignments cannot share one Student session.');
        $students = Student::query()->whereIn('id', $studentIds)->orderBy('id')->get();
        abort_unless($students->count() === count($studentIds), 404);
        foreach ($students as $student) {
            $this->authorize($actor, $student);
            if (($data['kind'] ?? '') !== 'resource') {
                [$course] = $this->target($student, (string) ($data['kind'] ?? ''), (int) ($data['target_id'] ?? 0));
                abort_unless($course->kind === 'catalog', 422, 'Private learning is assigned only to its owner.');
            }
        }

        return DB::transaction(function () use ($actor, $students, $data): array {
            foreach ($students as $student) {
                $this->teaching->lockStudent($student->id);
            }

            return $students->map(fn (Student $student): array => $this->assign($actor, $student, $data))->all();
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function createPrivate(Administrator $actor, Student $student, array $data): Course
    {
        $this->authorize($actor, $student);
        $values = Validator::make($data, ['title' => ['required', 'string', 'max:200'], 'request_key' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['rich_text', 'video', 'quiz', 'assignment'])], 'instructions' => ['nullable', 'string', 'max:5000'],
            'html' => ['nullable', 'string', 'max:100000'], 'definition' => ['nullable', 'array'], 'booking_id' => ['nullable', 'integer', 'min:1'],
            'share_now' => ['sometimes', 'boolean']])->validate();
        if (in_array($values['kind'], ['quiz', 'assignment'], true) && trim((string) ($values['definition']['title'] ?? '')) === '') {
            $values['definition']['title'] = $values['title'];
        }
        $key = hash('sha256', $actor->id.':'.$student->id.':'.$values['request_key']);
        $window = $this->windows->normalize($data);
        $fingerprint = hash('sha256', json_encode([$student->id, $values, $window], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $student, $values, $key, $fingerprint, $window): Course {
            $student = $this->teaching->lockStudent($student->id);
            $this->authorize($actor, $student);
            abort_unless($student->identity_status === 'verified', 422, 'Verify this Student before creating private learning.');
            $this->booking($student, $values['booking_id'] ?? null);
            $existing = Course::query()->where('private_learning_key', $key)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->owner_student_id === (int) $student->id && hash_equals((string) $existing->private_learning_fingerprint, $fingerprint), 409, 'This creation request has different original content.');

                return $existing;
            }
            $course = $this->studio->create($actor, ['title' => $values['title'], 'slug' => 'personal-'.substr($key, 0, 48), 'kind' => 'private', 'owner_student_id' => $student->id]);
            $course->forceFill(['private_learning_key' => $key, 'private_learning_fingerprint' => $fingerprint])->save();
            $this->requireProtection($course);
            $course = $this->studio->write($actor, $course, 'add_section', ['title' => 'Personal learning', 'status' => 'published'], $course->lock_version);
            $section = $this->studio->draft($actor, $course)['sections'][0];
            $course = $this->studio->write($actor, $course, 'add_lesson', ['title' => $values['title'], 'slug' => 'learning', 'status' => 'published', 'parent_key' => $section['key']], $course->lock_version);
            $lesson = $this->studio->draft($actor, $course)['sections'][0]['lessons'][0];
            if (! empty($values['instructions'])) {
                $course = $this->studio->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => 'rich_text',
                    'html' => '<p>'.nl2br(e($values['instructions'])).'</p>'], $course->lock_version);
            }
            if ($values['kind'] !== 'video') {
                $course = $this->studio->write($actor, $course, 'add_block', ['parent_key' => $lesson['key'], 'kind' => $values['kind'],
                    'html' => $values['html'] ?? null, 'definition' => $values['definition'] ?? []], $course->lock_version);
            }
            $method = match ($values['kind']) {
                'video' => 'video', 'quiz' => 'quiz_pass', 'assignment' => 'assignment_approve', default => 'manual'
            };
            $course = $this->studio->write($actor, $course, 'learning', ['key' => $lesson['key'], 'required' => true, 'methods' => [$method], 'video_threshold' => 95,
                'prerequisite_key' => null, 'drip_mode' => 'immediate'], $course->lock_version);
            $course = $this->studio->write($actor, $course, 'access', $this->windowInput($window) + ['audience' => 'selected_students'], $course->lock_version);
            if (($values['share_now'] ?? false) && $values['kind'] !== 'video') {
                $course = $this->studio->publish($actor, $course, $course->lock_version);
                $this->assign($actor, $student, $this->windowInput($window) + ['kind' => 'course', 'target_id' => $course->id, 'request_key' => $values['request_key'],
                    'instructions' => $values['instructions'] ?? null, 'booking_id' => $values['booking_id'] ?? null]);
            }

            return $course->fresh();
        }, 3);
    }

    public function privateItem(Administrator $actor, Student $student, Course $course): Course
    {
        $this->authorize($actor, $student);
        $current = Course::query()->where('kind', 'private')->where('owner_student_id', $student->id)->whereNotNull('private_learning_key')->findOrFail($course->id);
        Gate::forUser($actor)->authorize('manage', $current);

        return $current;
    }

    /** @param array<string,mixed> $data */
    public function sharePrivate(Administrator $actor, Student $student, Course $course, array $data, int $version): LearningAssignment
    {
        return DB::transaction(function () use ($actor, $student, $course, $data, $version): LearningAssignment {
            $student = $this->teaching->lockStudent($student->id);
            $this->booking($student, isset($data['booking_id']) ? (int) $data['booking_id'] : null);
            $course = $this->privateItem($actor, $student, $course);
            $this->requireProtection($course);
            $course = $this->studio->publish($actor, $course, $version);
            $result = $this->assign($actor, $student, array_replace($data, ['kind' => 'course', 'target_id' => $course->id]));
            if (! $result['record'] instanceof LearningAssignment) {
                throw new \LogicException('Private learning requires the canonical LMS assignment.');
            }

            return $result['record'];
        }, 3);
    }

    private function requireProtection(Course $course): void
    {
        $profile = $this->profiles->effective($course);
        abort_unless($profile->secure_playback && ! $profile->downloads_allowed && $profile->watermark && $profile->device_limit !== null && $profile->stream_limit !== null, 503, 'A protected Private video profile is required.');
    }

    /** @param array<string,mixed> $window
     * @return array<string,mixed> */
    private function windowInput(array $window): array
    {
        foreach (['starts_at', 'expires_at'] as $field) {
            if (! empty($window[$field])) {
                $window[$field] = CarbonImmutable::parse($window[$field], 'UTC')->toIso8601String();
            }
        }

        return $window;
    }

    /** @param array<string,mixed> $data */
    public function change(Administrator $actor, Student $student, AccessGrant $grant, string $action, array $data, int $version, string $key): AccessGrant
    {
        $this->authorize($actor, $student);
        abort_unless((int) $grant->student_id === (int) $student->id, 404);

        return $this->operations->change($actor, $grant, $action, $data, $version, 'profile:'.$student->id.':'.$key);
    }

    public function withdrawResource(Administrator $actor, Student $student, ResourceAssignment $assignment): void
    {
        $this->authorize($actor, $student);
        $this->teaching->withdrawResource($student, $assignment, $actor->id);
    }
}

<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\CMS\Services\RichTextSanitizer;
use App\Domains\Lms\Models\AccessRule;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Services\TeachingRecordService;
use App\Rules\SafeLessonUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsStructureService
{
    public function __construct(private AuditLogService $audits, private LmsAccessWindow $windows, private TeachingRecordService $students, private RichTextSanitizer $sanitizer) {}

    /** @param array<string, mixed> $data */
    public function createCourse(Administrator $actor, array $data): Course
    {
        Gate::forUser($actor)->authorize('manage', Course::class);
        $values = Validator::make($data, [
            'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'kind' => ['required', Rule::in(['catalog', 'private'])],
            'owner_student_id' => ['nullable', 'integer', 'min:1'],
        ])->validate();
        if (($values['kind'] === 'private') !== isset($values['owner_student_id'])) {
            throw ValidationException::withMessages(['owner_student_id' => 'Private courses require one canonical Student; catalog courses have no private owner.']);
        }

        return DB::transaction(function () use ($actor, $values): Course {
            Gate::forUser($actor)->authorize('manage', Course::class);
            if (isset($values['owner_student_id'])) {
                $this->students->lockStudent((int) $values['owner_student_id']);
            }
            $course = new Course;
            $course->forceFill($values + ['created_by' => $actor->id])->save();
            $this->audits->log('lms_course_created', Course::class, $course->id, null, ['status' => 'draft', 'student_id' => $course->owner_student_id], $actor->id);

            return $course;
        }, 3);
    }

    public function addSection(Administrator $actor, Course $course, string $title, int $order = 0): Section
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $this->validateTitle($title, $order);

        return DB::transaction(function () use ($actor, $course, $title, $order): Section {
            Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('manage', $course);
            $section = new Section;
            $section->forceFill(['course_id' => $course->id, 'title' => $title, 'sort_order' => $order])->save();
            $this->audits->log('lms_section_created', Section::class, $section->id, null, ['course_id' => $course->id], $actor->id);

            return $section;
        }, 3);
    }

    public function addLesson(Administrator $actor, Section $section, string $title, string $slug, int $order = 0): Lesson
    {
        Gate::forUser($actor)->authorize('manage', $section);
        $this->validateTitle($title, $order);
        Validator::make(['slug' => $slug], ['slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D']])->validate();

        return DB::transaction(function () use ($actor, $section, $title, $slug, $order): Lesson {
            $current = Section::query()->findOrFail($section->id);
            Course::query()->lockForUpdate()->findOrFail($current->course_id);
            $current = Section::query()->lockForUpdate()->findOrFail($section->id);
            Gate::forUser($actor)->authorize('manage', $current);
            $lesson = new Lesson;
            $lesson->forceFill(['course_id' => $current->course_id, 'section_id' => $current->id, 'title' => $title, 'slug' => $slug, 'sort_order' => $order])->save();
            $this->audits->log('lms_lesson_created', Lesson::class, $lesson->id, null, ['course_id' => $current->course_id], $actor->id);

            return $lesson;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function addBlock(Administrator $actor, Lesson $lesson, array $data): LessonBlock
    {
        Gate::forUser($actor)->authorize('manage', $lesson);
        $values = Validator::make($data, [
            'kind' => ['required', Rule::in(LessonBlock::KINDS)],
            'sort_order' => ['sometimes', 'integer', 'between:0,10000'],
            'resource_id' => ['nullable', 'integer', 'min:1'],
            'payload' => ['nullable', 'array'],
        ])->validate();
        $kind = $values['kind'];
        $payload = $values['payload'] ?? null;
        $ready = in_array($kind, ['resource', 'rich_text', 'external_link', 'youtube_video', 'external_video'], true);
        if ($kind === 'resource') {
            if (! isset($values['resource_id']) || $payload !== null) {
                throw ValidationException::withMessages(['resource_id' => 'Reference one existing Resource without a second file payload.']);
            }
        } elseif ($values['resource_id'] ?? null) {
            throw ValidationException::withMessages(['resource_id' => 'Only a Resource block can reference the library.']);
        } elseif ($ready) {
            $key = $kind === 'rich_text' ? 'html' : 'url';
            if (! is_array($payload) || array_keys($payload) !== [$key] || ! is_string($payload[$key]) || strlen($payload[$key]) > 50000) {
                throw ValidationException::withMessages(['payload' => 'Use only the declared content field.']);
            }
            if ($key === 'html') {
                $payload['html'] = $this->sanitizer->sanitize($payload['html']);
            } elseif (! SafeLessonUrl::isSafe($payload['url'])) {
                throw ValidationException::withMessages(['payload' => 'Use a safe HTTPS link without embedded credentials.']);
            }
        } elseif ($payload !== null) {
            throw ValidationException::withMessages(['payload' => 'This reserved content type has no delivery implementation yet.']);
        }

        return DB::transaction(function () use ($actor, $lesson, $values, $kind, $payload, $ready): LessonBlock {
            $current = Lesson::query()->findOrFail($lesson->id);
            Course::query()->lockForUpdate()->findOrFail($current->course_id);
            Lesson::query()->lockForUpdate()->findOrFail($current->id);
            Gate::forUser($actor)->authorize('manage', $current);
            if ($kind === 'resource') {
                Resource::query()->published()->lockForUpdate()->findOrFail($values['resource_id']);
            }
            $block = new LessonBlock;
            $block->forceFill(['lesson_id' => $current->id, 'kind' => $kind, 'status' => $ready ? 'ready' : 'placeholder',
                'resource_id' => $values['resource_id'] ?? null, 'payload' => $payload, 'sort_order' => $values['sort_order'] ?? 0])->save();
            $this->audits->log('lms_block_created', LessonBlock::class, $block->id, null, ['lesson_id' => $current->id, 'kind' => $kind], $actor->id);

            return $block;
        }, 3);
    }

    public function setStatus(Administrator $actor, Course|Section|Lesson $target, string $status, int $expectedVersion): Course|Section|Lesson
    {
        Gate::forUser($actor)->authorize('manage', $target);
        Validator::make(['status' => $status], ['status' => ['required', Rule::in(['draft', 'published', 'unpublished', 'archived'])]])->validate();

        return DB::transaction(function () use ($actor, $target, $status, $expectedVersion): Course|Section|Lesson {
            $current = $target->newQuery()->findOrFail($target->id);
            Course::query()->lockForUpdate()->findOrFail($current instanceof Course ? $current->id : $current->course_id);
            $current = $target->newQuery()->lockForUpdate()->findOrFail($target->id);
            Gate::forUser($actor)->authorize('manage', $current);
            abort_if($current->lock_version !== $expectedVersion, 409, 'The LMS object changed. Reload before updating.');
            if ($current->status !== $status) {
                $before = $current->status;
                $current->forceFill(['status' => $status, 'published_at' => $status === 'published' ? ($current->published_at ?? now('UTC')) : $current->published_at,
                    'lock_version' => $current->lock_version + 1])->save();
                $this->audits->log('lms_publication_changed', $current::class, $current->id, ['status' => $before], ['status' => $status], $actor->id);
            }

            return $current;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function setRule(Administrator $actor, Course $course, array $data): AccessRule
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $values = Validator::make($data, ['audience' => ['required', Rule::in(['public', 'member', 'all_students', 'selected_students'])]])->validate();
        $window = $this->windows->capture($this->windows->normalize($data, true), true);

        return DB::transaction(function () use ($actor, $course, $values, $window): AccessRule {
            $current = Course::query()->lockForUpdate()->findOrFail($course->id);
            Gate::forUser($actor)->authorize('manage', $current);
            if ($current->kind === 'private' && $values['audience'] !== 'selected_students') {
                throw ValidationException::withMessages(['audience' => 'Private Student courses require selected-Student access.']);
            }
            $rule = AccessRule::query()->where('course_id', $current->id)->lockForUpdate()->first() ?? new AccessRule;
            $before = $rule->exists ? ['audience' => $rule->audience, 'access_mode' => $rule->access_mode] : null;
            $rule->forceFill($values + $window + ['course_id' => $current->id, 'updated_by' => $actor->id])->save();
            $this->audits->log('lms_access_rule_changed', Course::class, $current->id, $before, $values + ['access_mode' => $window['access_mode']], $actor->id);

            return $rule;
        }, 3);
    }

    private function validateTitle(string $title, int $order): void
    {
        Validator::make(['title' => $title, 'sort_order' => $order], ['title' => ['required', 'string', 'max:200'], 'sort_order' => ['integer', 'between:0,10000']])->validate();
    }
}

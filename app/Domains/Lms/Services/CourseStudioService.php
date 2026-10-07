<?php

namespace App\Domains\Lms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\CMS\Models\ContentRevision;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\CourseRelease;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LmsAsset;
use App\Domains\Lms\Models\Section;
use App\Domains\Resources\Models\Resource;
use App\Domains\Students\Services\TeachingRecordService;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CourseStudioService
{
    public function __construct(private LmsStructureService $structure, private LmsContentService $content,
        private LmsAccessWindow $windows, private LessonMaterialService $files,
        private TeachingRecordService $students, private AuditLogService $audits) {}

    /** @param array<string,mixed> $data */
    public function create(Administrator $actor, array $data): Course
    {
        try {
            return DB::transaction(function () use ($actor, $data): Course {
                $course = $this->structure->createCourse($actor, $data);
                $this->saveDraft($actor, $course, $this->liveGraph($course));

                return $course->fresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->invalid('slug', 'This course URL name is already in use. Choose another one.');
        }
    }

    /** @return array<string,mixed> */
    public function draft(Administrator $actor, Course $course): array
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $course = Course::query()->findOrFail($course->id);
        $draft = $course->revisions()->where('status', 'draft')->orderByDesc('revision_number')->first();

        return $draft ? $draft->content : $this->liveGraph($course);
    }

    /** @param array<string,mixed> $data */
    public function write(Administrator $actor, Course $course, string $operation, array $data, int $version): Course
    {
        return DB::transaction(function () use ($actor, $course, $operation, $data, $version): Course {
            $course = $this->locked($actor, $course, $version);
            abort_if($course->status === 'archived', 409, 'Restore this course to Draft before editing.');
            $graph = $this->draft($actor, $course);
            switch ($operation) {
                case 'metadata':
                    $values = Validator::make($data, ['title' => ['required', 'string', 'max:200'], 'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('lms_courses', 'slug')->ignore($course->id)]])->validate();
                    $graph['title'] = $values['title'];
                    $graph['slug'] = $values['slug'];
                    break;
                case 'access':
                    $graph['access'] = $this->access($course, $data);
                    break;
                case 'add_section':
                    $values = $this->nodeValues($data);
                    $graph['sections'][] = ['key' => 'section:'.Str::uuid(), 'id' => null, 'title' => $values['title'], 'status' => $values['status'], 'lessons' => []];
                    break;
                case 'edit_section':
                    $index = $this->sectionIndex($graph, (string) ($data['key'] ?? ''));
                    $graph['sections'][$index] = array_replace($graph['sections'][$index], $this->nodeValues($data));
                    break;
                case 'add_lesson':
                    $index = $this->sectionIndex($graph, (string) ($data['parent_key'] ?? ''));
                    $values = $this->nodeValues($data, true);
                    $this->uniqueSlug($graph, $values['slug']);
                    $graph['sections'][$index]['lessons'][] = ['key' => 'lesson:'.Str::uuid(), 'id' => null, 'blocks' => []] + $values;
                    break;
                case 'edit_lesson':
                    [$section, $lesson] = $this->lessonIndex($graph, (string) ($data['key'] ?? ''));
                    $values = $this->nodeValues($data, true);
                    $this->uniqueSlug($graph, $values['slug'], (string) $graph['sections'][$section]['lessons'][$lesson]['key']);
                    $graph['sections'][$section]['lessons'][$lesson] = array_replace($graph['sections'][$section]['lessons'][$lesson], $values);
                    break;
                case 'move_lesson':
                    [$section, $lesson] = $this->lessonIndex($graph, (string) ($data['key'] ?? ''));
                    $destination = $this->sectionIndex($graph, (string) ($data['destination'] ?? ''));
                    if ($graph['sections'][$destination]['status'] === 'archived') {
                        $this->invalid('destination', 'Choose a section that is not archived.');
                    }
                    $moved = $graph['sections'][$section]['lessons'][$lesson];
                    array_splice($graph['sections'][$section]['lessons'], $lesson, 1);
                    $graph['sections'][$destination]['lessons'][] = $moved;
                    break;
                case 'add_block':
                    [$section, $lesson] = $this->lessonIndex($graph, (string) ($data['parent_key'] ?? ''));
                    $block = $this->content->normalize($course, $data, $this->assetIds($graph));
                    $graph['sections'][$section]['lessons'][$lesson]['blocks'][] = ['key' => 'block:'.Str::uuid(), 'id' => null] + $block;
                    break;
                case 'edit_block':
                    [$section, $lesson, $block] = $this->blockIndex($graph, (string) ($data['key'] ?? ''));
                    $values = $this->content->normalize($course, $data, $this->assetIds($graph));
                    $graph['sections'][$section]['lessons'][$lesson]['blocks'][$block] = array_replace($graph['sections'][$section]['lessons'][$lesson]['blocks'][$block], $values);
                    break;
                case 'remove_block':
                    [$section, $lesson, $block] = $this->blockIndex($graph, (string) ($data['key'] ?? ''));
                    $graph['sections'][$section]['lessons'][$lesson]['blocks'][$block] = array_replace($graph['sections'][$section]['lessons'][$lesson]['blocks'][$block],
                        ['status' => 'withdrawn', 'resource_id' => null, 'asset_id' => null, 'payload' => null]);
                    break;
                case 'reorder_section':
                    $graph['sections'] = $this->reorder($graph['sections'], (string) ($data['key'] ?? ''), (string) ($data['direction'] ?? ''));
                    break;
                case 'reorder_lesson':
                    [$section] = $this->lessonIndex($graph, (string) ($data['key'] ?? ''));
                    $graph['sections'][$section]['lessons'] = $this->reorder($graph['sections'][$section]['lessons'], (string) $data['key'], (string) ($data['direction'] ?? ''));
                    break;
                case 'reorder_block':
                    [$section, $lesson] = $this->blockIndex($graph, (string) ($data['key'] ?? ''));
                    $graph['sections'][$section]['lessons'][$lesson]['blocks'] = $this->reorder($graph['sections'][$section]['lessons'][$lesson]['blocks'], (string) $data['key'], (string) ($data['direction'] ?? ''));
                    break;
                default: abort(404);
            }
            $this->saveDraft($actor, $course, $graph);
            $this->bump($course);
            $this->audits->log('lms_studio_'.$operation, Course::class, $course->id, null, ['lock_version' => $course->lock_version], $actor->id);

            return $course->fresh();
        }, 3);
    }

    public function upload(Administrator $actor, Course $course, UploadedFile $file, string $kind, int $version): LmsAsset
    {
        $asset = null;
        try {
            return DB::transaction(function () use ($actor, $course, $file, $kind, $version, &$asset): LmsAsset {
                $course = $this->locked($actor, $course, $version);
                abort_if($course->status === 'archived', 409, 'Restore this course to Draft before uploading.');
                $asset = $this->files->storeCourseAsset($course, $actor, $file, $kind);
                $this->bump($course);
                $this->audits->log('lms_studio_asset_uploaded', Course::class, $course->id, null, ['asset_id' => $asset->id, 'kind' => $asset->kind], $actor->id);

                return $asset;
            }, 3);
        } catch (Throwable $exception) {
            if ($asset && is_string($asset->path)) {
                $this->files->discardCourseAssetFile($asset->path);
            }
            throw $exception;
        }
    }

    public function lifecycle(Administrator $actor, Course $course, string $status, int $version): Course
    {
        if ($status === 'published') {
            return $this->publish($actor, $course, $version);
        }
        abort_unless(in_array($status, ['draft', 'unpublished', 'archived'], true), 404);

        return DB::transaction(function () use ($actor, $course, $status, $version): Course {
            $course = $this->locked($actor, $course, $version);
            $before = $course->status;
            $course->forceFill(['status' => $status])->save();
            $this->bump($course);
            $this->audits->log('lms_studio_lifecycle', Course::class, $course->id, ['status' => $before], ['status' => $status], $actor->id);

            return $course->fresh();
        }, 3);
    }

    public function publish(Administrator $actor, Course $course, int $version): Course
    {
        try {
            return DB::transaction(function () use ($actor, $course, $version): Course {
                $course = $this->locked($actor, $course, $version);
                abort_if($course->status === 'archived', 409, 'Restore this course to Draft before publishing.');
                $graph = $this->draft($actor, $course);
                $graph = $this->validatePublication($course, $graph);
                $graph = $this->persist($course, $graph);
                $this->structure->setRule($actor, $course, $graph['access']);
                $revision = $this->saveDraft($actor, $course, $graph);
                $revision->update(['status' => 'published']);
                $release = new CourseRelease;
                $release->forceFill(['course_id' => $course->id, 'content_revision_id' => $revision->id, 'revision_number' => $revision->revision_number,
                    'content_hash' => hash('sha256', json_encode($revision->content, JSON_THROW_ON_ERROR)), 'published_at' => now('UTC')])->save();
                $course->forceFill(['title' => $graph['title'], 'slug' => $graph['slug'], 'status' => 'published', 'published_at' => now('UTC'), 'current_release_id' => $release->id])->save();
                $this->bump($course);
                $this->audits->log('lms_course_published', Course::class, $course->id, null, ['release_id' => $release->id, 'revision_number' => $release->revision_number], $actor->id);

                return $course->fresh();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            $this->invalid('slug', 'This course URL or revision changed. Reload and choose a unique URL.');
        }
    }

    public function duplicate(Administrator $actor, Course $source, int $version): Course
    {
        return DB::transaction(function () use ($actor, $source, $version): Course {
            $source = $this->locked($actor, $source, $version);
            $graph = $this->draft($actor, $source);
            $title = mb_substr($graph['title'], 0, 190).' (copy)';
            $slug = substr($source->slug, 0, 130).'-copy-'.Str::lower(Str::random(8));
            $target = $this->structure->createCourse($actor, ['title' => $title, 'slug' => $slug, 'kind' => $source->kind, 'owner_student_id' => $source->owner_student_id]);
            $graph['title'] = $title;
            $graph['slug'] = $slug;
            foreach ($graph['sections'] as &$section) {
                $section['id'] = null;
                $section['key'] = 'section:'.Str::uuid();
                foreach ($section['lessons'] as &$lesson) {
                    $lesson['id'] = null;
                    $lesson['key'] = 'lesson:'.Str::uuid();
                    $lesson['blocks'] = array_values(array_filter($lesson['blocks'], fn (array $block): bool => $block['status'] === 'ready' && in_array($block['kind'], LmsContentService::AUTHORABLE, true)));
                    foreach ($lesson['blocks'] as &$block) {
                        $block['id'] = null;
                        $block['key'] = 'block:'.Str::uuid();
                    }
                    unset($block);
                }
                unset($lesson);
            }
            unset($section);
            $this->saveDraft($actor, $target, $graph);
            $this->bump($source);
            $this->audits->log('lms_course_duplicated', Course::class, $target->id, null, ['source_course_id' => $source->id, 'status' => 'draft'], $actor->id);

            return $target->fresh();
        }, 3);
    }

    public function discard(Administrator $actor, Course $course, int $version): Course
    {
        return DB::transaction(function () use ($actor, $course, $version): Course {
            $course = $this->locked($actor, $course, $version);
            $course->revisions()->where('status', 'draft')->update(['status' => 'discarded']);
            $this->bump($course);
            $this->audits->log('lms_course_draft_discarded', Course::class, $course->id, null, ['lock_version' => $course->lock_version], $actor->id);

            return $course->fresh();
        }, 3);
    }

    /** @return array<string,mixed> */
    private function liveGraph(Course $course): array
    {
        $course->load(['accessRule', 'sections.lessons.blocks']);
        $rule = $course->accessRule;

        return ['schema' => 1, 'title' => $course->title, 'slug' => $course->slug,
            'access' => ['audience' => $rule ? $rule->audience : 'selected_students', 'access_mode' => $rule ? $rule->access_mode : 'permanent',
                'starts_at' => $rule?->starts_at?->toIso8601String(), 'expires_at' => $rule?->expires_at?->toIso8601String(), 'relative_days' => $rule?->relative_days],
            'sections' => $course->sections->map(fn (Section $section): array => ['key' => 'section:'.$section->id, 'id' => $section->id, 'title' => $section->title, 'status' => $section->status,
                'lessons' => $section->lessons->map(fn (Lesson $lesson): array => ['key' => 'lesson:'.$lesson->id, 'id' => $lesson->id, 'title' => $lesson->title, 'slug' => $lesson->slug, 'status' => $lesson->status,
                    'blocks' => $lesson->blocks->map(fn (LessonBlock $block): array => ['key' => 'block:'.$block->id, 'id' => $block->id, 'kind' => $block->kind, 'status' => $block->status,
                        'resource_id' => $block->resource_id, 'asset_id' => $block->asset_id, 'payload' => $block->payload])->all()])->all()])->all()];
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed> */
    private function nodeValues(array $data, bool $lesson = false): array
    {
        $rules = ['title' => ['required', 'string', 'max:200'], 'status' => ['sometimes', Rule::in(['draft', 'published', 'unpublished', 'archived'])]];
        if ($lesson) {
            $rules['slug'] = ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'];
        }
        $values = Validator::make($data, $rules)->validate();
        $values['status'] ??= 'draft';
        if ($lesson) {
            $values['slug'] = $values['slug'] ?? (Str::slug($values['title']) ?: 'lesson-'.Str::lower(Str::random(8)));
        }

        return $values;
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed> */
    private function access(Course $course, array $data): array
    {
        $values = Validator::make($data, ['audience' => ['required', Rule::in(['public', 'member', 'all_students', 'selected_students'])]])->validate();
        if ($course->kind === 'private' && $values['audience'] !== 'selected_students') {
            $this->invalid('audience', 'Private courses require Selected Students access.');
        }
        $window = $this->windows->normalize($data, true);
        foreach (['starts_at', 'expires_at'] as $field) {
            if ($window[$field] !== null) {
                $window[$field] = CarbonImmutable::parse($window[$field], 'UTC')->toIso8601String();
            }
        }

        return $values + $window;
    }

    private function locked(Administrator $actor, Course $course, int $version): Course
    {
        Gate::forUser($actor)->authorize('manage', $course);
        $current = Course::query()->findOrFail($course->id);
        if ($current->kind === 'private') {
            $this->students->lockStudent((int) $current->owner_student_id);
        }
        $current = Course::query()->lockForUpdate()->findOrFail($course->id);
        Gate::forUser($actor)->authorize('manage', $current);
        abort_if($current->lock_version !== $version, 409, 'This course changed. Reload the editor before saving.');

        return $current;
    }

    private function bump(Course $course): void
    {
        $course->forceFill(['lock_version' => $course->lock_version + 1])->save();
    }

    /** @param array<string,mixed> $graph */
    private function saveDraft(Administrator $actor, Course $course, array $graph): ContentRevision
    {
        if (strlen(json_encode($graph, JSON_THROW_ON_ERROR)) > 4194304) {
            $this->invalid('course', 'This draft is too large. Split it into smaller courses.');
        }
        $draft = $course->revisions()->where('status', 'draft')->lockForUpdate()->first();
        $graph['base_fingerprint'] = $draft?->content['base_fingerprint'] ?? hash('sha256', json_encode($this->liveGraph($course), JSON_THROW_ON_ERROR));
        $values = ['title' => $graph['title'], 'content' => $graph, 'created_by_id' => $actor->id, 'status' => 'draft'];
        if ($draft) {
            $draft->update($values);

            return $draft;
        }

        return $course->revisions()->create($values + ['revision_number' => (int) $course->revisions()->max('revision_number') + 1]);
    }

    /** @param array<string,mixed> $graph
     * @return list<int> */
    private function assetIds(array $graph): array
    {
        $ids = [];
        foreach ($graph['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                foreach ($lesson['blocks'] as $block) {
                    if (! empty($block['asset_id'])) {
                        $ids[] = (int) $block['asset_id'];
                    }
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /** @param array<string,mixed> $graph */
    private function uniqueSlug(array $graph, string $slug, ?string $except = null): void
    {
        foreach ($graph['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                if ($lesson['slug'] === $slug && $lesson['key'] !== $except) {
                    $this->invalid('slug', 'Each lesson needs a unique URL within this course.');
                }
            }
        }
    }

    /** @param array<string,mixed> $graph */
    private function sectionIndex(array $graph, string $key): int
    {
        foreach ($graph['sections'] as $index => $section) {
            if ($section['key'] === $key) {
                return $index;
            }
        }
        abort(404);
    }

    /** @param array<string,mixed> $graph
     * @return array{int,int} */
    private function lessonIndex(array $graph, string $key): array
    {
        foreach ($graph['sections'] as $sectionIndex => $section) {
            foreach ($section['lessons'] as $lessonIndex => $lesson) {
                if ($lesson['key'] === $key) {
                    return [$sectionIndex, $lessonIndex];
                }
            }
        }
        abort(404);
    }

    /** @param array<string,mixed> $graph
     * @return array{int,int,int} */
    private function blockIndex(array $graph, string $key): array
    {
        foreach ($graph['sections'] as $sectionIndex => $section) {
            foreach ($section['lessons'] as $lessonIndex => $lesson) {
                foreach ($lesson['blocks'] as $blockIndex => $block) {
                    if ($block['key'] === $key) {
                        return [$sectionIndex, $lessonIndex, $blockIndex];
                    }
                }
            }
        }
        abort(404);
    }

    /** @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>> */
    private function reorder(array $items, string $key, string $direction): array
    {
        Validator::make(['direction' => $direction], ['direction' => ['required', 'in:up,down']])->validate();
        $index = array_search($key, array_column($items, 'key'), true);
        abort_if($index === false, 404);
        $destination = $index + ($direction === 'up' ? -1 : 1);
        if (isset($items[$destination])) {
            [$items[$index],$items[$destination]] = [$items[$destination], $items[$index]];
        }

        return $items;
    }

    /** @param array<string,mixed> $graph
     * @return array<string,mixed> */
    private function validatePublication(Course $course, array $graph): array
    {
        if (isset($graph['base_fingerprint']) && ! hash_equals($graph['base_fingerprint'], hash('sha256', json_encode($this->liveGraph($course), JSON_THROW_ON_ERROR)))) {
            abort(409, 'The published structure changed outside this draft. Review it and rebuild the draft before publishing.');
        }
        Validator::make($graph, ['schema' => ['required', 'in:1'], 'title' => ['required', 'string', 'max:200'],
            'slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', Rule::unique('lms_courses', 'slug')->ignore($course->id)],
            'sections' => ['required', 'array', 'max:50'], 'sections.*.title' => ['required', 'string', 'max:200'],
            'sections.*.status' => ['required', Rule::in(['draft', 'published', 'unpublished', 'archived'])],
            'sections.*.lessons' => ['present', 'array', 'max:200'], 'sections.*.lessons.*.title' => ['required', 'string', 'max:200'],
            'sections.*.lessons.*.slug' => ['required', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/D'],
            'sections.*.lessons.*.status' => ['required', Rule::in(['draft', 'published', 'unpublished', 'archived'])],
            'sections.*.lessons.*.blocks' => ['present', 'array', 'max:100']])->validate();
        $graph['access'] = $this->access($course, $graph['access']);
        $available = 0;
        $slugs = [];
        $assets = $this->assetIds($graph);
        foreach ($graph['sections'] as $sectionIndex => &$section) {
            foreach ($section['lessons'] as $lessonIndex => &$lesson) {
                if (in_array($lesson['slug'], $slugs, true)) {
                    $this->invalid('slug', 'Each lesson needs a unique URL within this course.');
                }
                $slugs[] = $lesson['slug'];
                if ($section['status'] !== 'published' || $lesson['status'] !== 'published') {
                    continue;
                }
                $active = 0;
                foreach ($lesson['blocks'] as &$block) {
                    if ($block['status'] === 'withdrawn') {
                        continue;
                    }
                    if ($block['status'] !== 'ready' || ! in_array($block['kind'], LmsContentService::AUTHORABLE, true)) {
                        $this->invalid('publication', 'Remove unavailable content from lesson “'.$lesson['title'].'” before publishing.');
                    }
                    $normalized = $this->content->normalize($course, $this->content->input($block), $assets);
                    if ($normalized['resource_id'] !== null) {
                        $resource = Resource::query()->findOrFail($normalized['resource_id']);
                        if (! $this->files->resourceAvailable($resource)) {
                            $this->invalid('publication', 'Resource “'.$resource->title.'” in lesson “'.$lesson['title'].'” has no available file or safe link.');
                        }
                    }
                    if ($normalized['asset_id'] !== null) {
                        $asset = LmsAsset::query()->findOrFail($normalized['asset_id']);
                        if (! $this->files->courseAssetAvailable($asset)) {
                            $this->invalid('publication', 'An attachment in lesson “'.$lesson['title'].'” is unavailable. Upload it again.');
                        }
                    }
                    $block = array_replace($block, $normalized);
                    $active++;
                }
                unset($block);
                if ($active === 0) {
                    $this->invalid('publication', 'Add supported content to published lesson “'.$lesson['title'].'”.');
                }
                $available++;
            }
            unset($lesson);
        }
        unset($section);
        if ($available === 0) {
            $this->invalid('publication', 'Publish at least one section and lesson with supported content before publishing the course.');
        }

        return $graph;
    }

    /** @param array<string,mixed> $graph
     * @return array<string,mixed> */
    private function persist(Course $course, array $graph): array
    {
        $sections = Section::query()->where('course_id', $course->id)->lockForUpdate()->get()->keyBy('id');
        $lessons = Lesson::query()->where('course_id', $course->id)->lockForUpdate()->get()->keyBy('id');
        $blocks = LessonBlock::query()->whereIn('lesson_id', $lessons->modelKeys())->lockForUpdate()->get()->keyBy('id');
        $seenSections = [];
        $seenLessons = [];
        $seenBlocks = [];
        foreach ($graph['sections'] as $section) {
            foreach ($section['lessons'] as $lesson) {
                $existing = ! empty($lesson['id']) ? $lessons->get($lesson['id']) : null;
                if ($existing && $existing->slug !== $lesson['slug']) {
                    $existing->forceFill(['slug' => 'studio-temporary-'.$existing->id.'-'.bin2hex(random_bytes(8))])->save();
                }
            }
        }
        foreach ($graph['sections'] as $sectionOrder => &$section) {
            $model = ! empty($section['id']) ? $sections->get($section['id']) : new Section;
            abort_unless($model instanceof Section, 404);
            $model->forceFill(['course_id' => $course->id, 'title' => $section['title'], 'status' => $section['status'], 'sort_order' => $sectionOrder,
                'published_at' => $section['status'] === 'published' ? ($model->published_at ?? now('UTC')) : $model->published_at, 'lock_version' => ($model->lock_version ?? 0) + 1])->save();
            $section['id'] = $model->id;
            $section['key'] = 'section:'.$model->id;
            $seenSections[] = $model->id;
            foreach ($section['lessons'] as $lessonOrder => &$lesson) {
                $node = ! empty($lesson['id']) ? $lessons->get($lesson['id']) : new Lesson;
                abort_unless($node instanceof Lesson, 404);
                $node->forceFill(['course_id' => $course->id, 'section_id' => $model->id, 'title' => $lesson['title'], 'slug' => $lesson['slug'], 'status' => $lesson['status'], 'sort_order' => $lessonOrder,
                    'published_at' => $lesson['status'] === 'published' ? ($node->published_at ?? now('UTC')) : $node->published_at, 'lock_version' => ($node->lock_version ?? 0) + 1])->save();
                $lesson['id'] = $node->id;
                $lesson['key'] = 'lesson:'.$node->id;
                $seenLessons[] = $node->id;
                foreach ($lesson['blocks'] as $blockOrder => &$block) {
                    $part = ! empty($block['id']) ? $blocks->get($block['id']) : new LessonBlock;
                    abort_unless($part instanceof LessonBlock, 404);
                    $part->forceFill(['lesson_id' => $node->id, 'kind' => $block['kind'], 'status' => $block['status'], 'resource_id' => $block['resource_id'] ?? null,
                        'asset_id' => $block['asset_id'] ?? null, 'payload' => $block['payload'] ?? null, 'sort_order' => $blockOrder, 'lock_version' => ($part->lock_version ?? 0) + 1])->save();
                    $block['id'] = $part->id;
                    $block['key'] = 'block:'.$part->id;
                    $seenBlocks[] = $part->id;
                } unset($block);
            } unset($lesson);
        } unset($section);
        Section::query()->where('course_id', $course->id)->whereNotIn('id', $seenSections)->update(['status' => 'archived']);
        Lesson::query()->where('course_id', $course->id)->whereNotIn('id', $seenLessons)->update(['status' => 'archived']);
        LessonBlock::query()->whereIn('lesson_id', $lessons->modelKeys())->whereNotIn('id', $seenBlocks)->update(['status' => 'withdrawn', 'resource_id' => null, 'asset_id' => null, 'payload' => null]);

        return $graph;
    }

    private function invalid(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}

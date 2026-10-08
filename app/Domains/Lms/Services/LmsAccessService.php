<?php

namespace App\Domains\Lms\Services;

use App\Domains\Booking\Services\LessonMaterialService;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\LearningAssignment;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LmsAccessService
{
    public function __construct(private LmsAccessWindow $windows, private LessonMaterialService $files) {}

    public function canAccess(?Student $student, Model $target): bool
    {
        return $this->resolve($student, $target)['allowed'];
    }

    /** One fresh graph and the same decision function keep outline reads bounded.
     * @return array{course: Course, sections: Collection<int, Section>}|null
     */
    public function outline(?Student $student, Course $course): ?array
    {
        $student = $student ? Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->find($student->id) : null;
        $studentId = $student ? $student->id : 0;
        $course = Course::query()->with(['sections.lessons', 'accessRule', 'grants' => fn ($query) => $query->where('student_id', $studentId)->with(['learningAssignment.booking', 'lesson:id,section_id'])])->find($course->id);
        if (! $course || ! $this->published($course)) {
            return null;
        }
        $anchor = $student ? $this->anchors(Enrollment::query()->where('student_id', $student->id)->where('course_id', $course->id)->get(), 'course_id')->get($course->id) : null;
        if (! $this->decision($student, $course, $course, $anchor)['allowed']) {
            return null;
        }
        $sections = $course->sections->filter(fn (Section $section): bool => $this->published($section) && $this->decision($student, $course, $section, $anchor)['allowed'])->values();
        foreach ($sections as $section) {
            $section->setRelation('lessons', $section->lessons->filter(fn (Lesson $lesson): bool => $this->published($lesson) && $this->decision($student, $course, $lesson, $anchor)['allowed'])->values());
        }

        return ['course' => $course, 'sections' => $sections];
    }

    /** @return Collection<int, LessonBlock> */
    public function lessonBlocks(?Student $student, Lesson $lesson): Collection
    {
        if (! $this->canAccess($student, $lesson) || ! app(LmsLearningGate::class)->decision($student, $lesson)['allowed']) {
            return collect();
        }

        return $lesson->blocks()->with(['resource', 'asset.course', 'videoAsset.course', 'lesson.course'])->get()->filter(fn (LessonBlock $block): bool => $this->readyBlock($block))->values();
    }

    /** @return array{allowed: bool, sources: list<string>} */
    public function resolve(?Student $student, Model $target): array
    {
        $student = $student ? Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->find($student->id) : null;
        $target = $target->exists ? $target->newQuery()->find($target->getKey()) : null;
        if (! $target) {
            return ['allowed' => false, 'sources' => []];
        }
        if ($target instanceof LearningAssignment) {
            $grant = $target->accessGrant;
            if (! $student || ! $grant || (int) $grant->student_id !== (int) $student->id || $target->status !== 'assigned' || ! $this->windows->active($grant)
                || ($target->booking_id !== null && (int) $target->booking?->student_id !== (int) $student->id)) {
                return ['allowed' => false, 'sources' => []];
            }
            if ($target->lesson_block_id !== null) {
                $block = $target->lessonBlock;
                if (! $block || (int) $block->lesson_id !== (int) $grant->lesson_id || $block->kind !== $target->block_kind) {
                    return ['allowed' => false, 'sources' => []];
                }
                $target = $block;
            } else {
                $target = $grant->lesson ?? $grant->section ?? $grant->course;
            }
        }
        if ($target instanceof LessonBlock) {
            if (! $this->readyBlock($target) || ! app(LmsLearningGate::class)->decision($student, $target->lesson)['allowed']) {
                return ['allowed' => false, 'sources' => []];
            }
            $target = $target->lesson;
        }
        if (! ($target instanceof Course || $target instanceof Section || $target instanceof Lesson)) {
            return ['allowed' => false, 'sources' => []];
        }
        $course = $target instanceof Course ? $target : $target->course;
        if (! $course || ! $this->published($course)
            || ($target instanceof Section && ! $this->published($target))
            || ($target instanceof Lesson && (! $this->published($target) || ! $target->section || ! $this->published($target->section)))) {
            return ['allowed' => false, 'sources' => []];
        }
        $studentId = $student ? $student->id : 0;
        $course->load(['accessRule', 'grants' => fn ($query) => $query->where('student_id', $studentId)->with(['learningAssignment.booking', 'lesson:id,section_id'])]);
        $enrollment = $student ? $this->anchors(Enrollment::query()->where('student_id', $student->id)->where('course_id', $course->id)->get(), 'course_id')->get($course->id) : null;

        return $this->decision($student, $course, $target, $enrollment);
    }

    /** @return Collection<int, Course> */
    public function activeForStudent(Student $student): Collection
    {
        $student = Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->find($student->id);
        if (! $student) {
            return collect();
        }
        $courses = Course::query()->where('status', 'published')->where('published_at', '<=', now('UTC'))
            ->where(fn ($query) => $query->where('kind', 'catalog')->orWhere('owner_student_id', $student->id))
            ->with(['accessRule', 'grants' => fn ($query) => $query->where('student_id', $student->id)->with(['learningAssignment.booking', 'lesson:id,section_id'])])
            ->orderBy('id')->get();
        $enrollments = $this->anchors(Enrollment::query()->where('student_id', $student->id)->get(), 'course_id');

        return $courses->filter(fn (Course $course): bool => $this->decision($student, $course, $course, $enrollments->get($course->id))['allowed'])->values();
    }

    /** Fresh read projection for one page, with one graph for all courses. Delivery and writes still resolve independently.
     * @return array{outlines:array<int,array{course:Course,sections:Collection<int,Section>}>,canonical:Collection<int,Lesson>,anchors:array<int,mixed>}
     */
    public function studentOutlines(Student $student): array
    {
        $courses = $this->activeForStudent($student);
        (new \Illuminate\Database\Eloquent\Collection($courses->all()))->loadMissing('sections.lessons.blocks.videoAsset.course');
        $anchors = $this->anchors(Enrollment::query()->where('student_id', $student->id)->whereIn('course_id', $courses->pluck('id'))->get(), 'course_id');
        $outlines = [];
        $canonical = collect();
        foreach ($courses as $course) {
            $sections = collect();
            foreach ($course->sections as $section) {
                if (! $this->published($section)) {
                    continue;
                }
                $published = $section->lessons->filter(fn (Lesson $lesson): bool => $this->published($lesson));
                foreach ($published as $lesson) {
                    $lesson->setRelation('section', $section);
                    $lesson->setRelation('course', $course);
                    foreach ($lesson->blocks as $block) {
                        $block->setRelation('lesson', $lesson);
                    }
                    $canonical->push($lesson);
                }
                $anchor = $anchors->get($course->id);
                if ($this->decision($student, $course, $section, $anchor)['allowed']) {
                    $visible = clone $section;
                    $visible->setRelation('lessons', $published->filter(fn (Lesson $lesson): bool => $this->decision($student, $course, $lesson, $anchor)['allowed'])->values());
                    $sections->push($visible);
                }
            }
            $outlines[$course->id] = ['course' => $course, 'sections' => $sections];
        }

        return ['outlines' => $outlines, 'canonical' => $canonical, 'anchors' => $anchors->map(fn (Enrollment $row) => $row->enrolled_at)->all()];
    }

    /** Same assignment ownership/window/content rules on an already fresh page projection.
     * @param  array<int,array{course:Course,sections:Collection<int,Section>}>  $outlines
     * @param  array<int,array<string,mixed>>  $states
     */
    public function assignmentInOutline(Student $student, LearningAssignment $assignment, array $outlines, array $states): bool
    {
        $grant = $assignment->accessGrant;
        if (! $grant || (int) $grant->student_id !== (int) $student->id || $assignment->status !== 'assigned' || ! $this->windows->active($grant)
            || ($assignment->booking_id !== null && (int) $assignment->booking?->student_id !== (int) $student->id)) {
            return false;
        }
        $outline = $outlines[$grant->course_id] ?? null;
        if (! $outline) {
            return false;
        }
        if ($grant->lesson_id !== null) {
            $lesson = $outline['sections']->flatMap(fn (Section $section) => $section->lessons)->firstWhere('id', $grant->lesson_id);
            if (! $lesson) {
                return false;
            }
            if ($assignment->lesson_block_id !== null) {
                $block = $lesson->blocks->firstWhere('id', $assignment->lesson_block_id);

                return $block && $block->kind === $assignment->block_kind && ($states[$lesson->id]['allowed'] ?? false) && $this->readyBlock($block);
            }

            return true;
        }

        return $grant->section_id === null || $outline['sections']->contains('id', $grant->section_id);
    }

    /** @return Collection<int, Student> */
    public function studentsWithCourseAccess(Course $course): Collection
    {
        $course = Course::query()->with(['accessRule', 'grants.learningAssignment.booking', 'grants.lesson:id,section_id'])->findOrFail($course->id);
        if (! $this->published($course)) {
            return collect();
        }
        $students = Student::verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')->when($course->kind === 'private', fn ($query) => $query->whereKey($course->owner_student_id))->orderBy('id')->get();
        $enrollments = $this->anchors(Enrollment::query()->where('course_id', $course->id)->get(), 'student_id');

        return $students->filter(fn (Student $student): bool => $this->decision($student, $course, $course, $enrollments->get($student->id))['allowed'])->values();
    }

    /** Reporting projection of a fresh cohort; direct delivery still resolves fresh targets and locks.
     * @param Collection<int,Student> $students
     * @param Collection<int,Enrollment> $enrollments
     * @param Collection<int,AccessGrant> $grants
     * @return array<int,array{allowed:bool,ends_at:?CarbonImmutable}> */
    public function cohortDecisions(Course $course, Collection $students, Collection $enrollments, Collection $grants): array
    {
        $anchors = $this->anchors($enrollments, 'student_id');
        $result = [];
        foreach ($students as $student) {
            $graph = clone $course;
            $graph->setRelation('grants', $grants->where('student_id', $student->id));
            $anchor = $anchors->get($student->id);
            $decision = $this->decision($student, $graph, $graph, $anchor);
            $ends = [];
            if ($decision['allowed'] && $graph->accessRule && in_array('rule:'.$graph->accessRule->id, $decision['sources'], true)) {
                $ends[] = $this->windows->bounds($graph->accessRule, $anchor ? CarbonImmutable::instance($anchor->enrolled_at)->utc() : null)['end'];
            }
            foreach ($graph->grants as $grant) {
                if (in_array('grant:'.$grant->id, $decision['sources'], true)) {
                    $ends[] = $this->windows->bounds($grant)['end'];
                }
            }
            $result[$student->id] = ['allowed' => $decision['allowed'], 'ends_at' => in_array(null, $ends, true) ? null : collect($ends)->sort()->last()];
        }

        return $result;
    }

    /** Display the union of effective sources, using the same decision and elapsed-day bounds.
     * @param  Collection<int, Course>  $courses  Already filtered by activeForStudent or outline.
     * @return array<int, CarbonImmutable|null>
     */
    public function accessEndings(Student $student, Collection $courses, ?Lesson $lesson = null): array
    {
        $anchors = $this->anchors(Enrollment::query()->where('student_id', $student->id)->whereIn('course_id', $courses->pluck('id')->all())->get(), 'course_id');
        $endings = [];
        foreach ($courses as $course) {
            $anchor = $anchors->get($course->id);
            $sources = $this->decision($student, $course, $lesson ?? $course, $anchor)['sources'];
            $ends = [];
            if ($course->accessRule && in_array('rule:'.$course->accessRule->id, $sources, true)) {
                $ends[] = $this->windows->bounds($course->accessRule, $anchor ? CarbonImmutable::instance($anchor->enrolled_at)->utc() : null)['end'];
            }
            foreach ($course->grants as $grant) {
                if (in_array('grant:'.$grant->id, $sources, true)) {
                    $ends[] = $this->windows->bounds($grant)['end'];
                }
            }
            $endings[$course->id] = in_array(null, $ends, true) ? null : collect($ends)->sort()->last();
        }

        return $endings;
    }

    /** Denial copy contains no titles, private instructions, source identifiers or foreign ownership.
     * @return array{message:string,at:?CarbonImmutable}
     */
    public function unavailable(Student $student, Course $course, ?Lesson $lesson = null): array
    {
        $generic = ['message' => 'This learning item is unavailable.', 'at' => null];
        if ($course->kind === 'private' && (int) $course->owner_student_id !== (int) $student->id) {
            return $generic;
        }
        $grants = $course->grants()->where('student_id', $student->id)->with(['learningAssignment.booking', 'lesson:id,section_id'])->get();
        $enrollments = Enrollment::query()->where('student_id', $student->id)->where('course_id', $course->id)->get();
        $known = $grants->isNotEmpty() || $enrollments->isNotEmpty() || (int) $course->owner_student_id === (int) $student->id
            || LearningVisit::query()->where('student_id', $student->id)->where('course_id', $course->id)->exists();
        if (! $known) {
            return $generic;
        }
        $targets = [$course];
        if ($lesson) {
            if ((int) $lesson->course_id !== (int) $course->id) {
                return $generic;
            }
            $targets = [$course, $lesson->section, $lesson];
        }
        foreach ($targets as $target) {
            if (! $target || $target->status === 'archived') {
                return ['message' => 'This learning item is no longer available.', 'at' => null];
            }
            if (! $this->published($target)) {
                return ['message' => 'This learning item is not currently published.', 'at' => null];
            }
        }
        $anchor = $this->anchors($enrollments, 'course_id')->get($course->id);
        $facts = $grants->filter(fn (AccessGrant $grant): bool => $this->covers($grant, $lesson ?? $course) && $this->validAssignment($grant));
        $rule = $course->accessRule;
        if ($rule && in_array($rule->audience, ['public', 'member', 'all_students'], true)) {
            $facts = $facts->concat([$rule]);
        }
        $future = [];
        $expired = [];
        foreach ($facts as $fact) {
            if ($fact instanceof AccessGrant && $fact->status !== 'active') {
                continue;
            }
            $bounds = $this->windows->bounds($fact, $anchor ? CarbonImmutable::instance($anchor->enrolled_at)->utc() : null);
            if ($bounds['anchored'] && $bounds['start']?->isFuture()) {
                $future[] = $bounds['start'];
            } elseif ($bounds['end'] && $bounds['end']->lte(now('UTC'))) {
                $expired[] = $bounds['end'];
            }
        }
        if ($future !== []) {
            return ['message' => 'Your access starts', 'at' => collect($future)->sort()->first()];
        }
        if ($expired !== []) {
            return ['message' => 'Your access expired', 'at' => collect($expired)->sort()->last()];
        }
        if ($grants->isNotEmpty() && $grants->every(fn (AccessGrant $grant): bool => $grant->status === 'revoked')) {
            return ['message' => 'Your access has been withdrawn.', 'at' => null];
        }

        return $generic;
    }

    /** @return array{allowed: bool, sources: list<string>} */
    private function decision(?Student $student, Course $course, Course|Section|Lesson $target, ?Enrollment $enrollment): array
    {
        if ($course->kind === 'private' && (! $student || (int) $course->owner_student_id !== (int) $student->id)) {
            return ['allowed' => false, 'sources' => []];
        }
        $sources = [];
        $rule = $course->accessRule;
        if ($rule && $this->windows->active($rule, $enrollment ? CarbonImmutable::instance($enrollment->enrolled_at)->utc() : null)
            && ($rule->audience === 'public' || ($student && in_array($rule->audience, ['member', 'all_students'], true)))) {
            $sources[] = 'rule:'.$rule->id;
        }
        if ($student) {
            foreach ($course->grants as $grant) {
                if ((int) $grant->student_id === (int) $student->id && $this->windows->active($grant)
                    && $this->validAssignment($grant)
                    && $this->covers($grant, $target)) {
                    $sources[] = 'grant:'.$grant->id;
                }
            }
        }

        return ['allowed' => $sources !== [], 'sources' => $sources];
    }

    private function covers(AccessGrant $grant, Course|Section|Lesson $target): bool
    {
        if ($target instanceof Course || ($grant->section_id === null && $grant->lesson_id === null)) {
            return true;
        }
        if ($target instanceof Section) {
            return (int) $grant->section_id === (int) $target->id || ($grant->lesson && (int) $grant->lesson->section_id === (int) $target->id);
        }

        return (int) $grant->lesson_id === (int) $target->id || (int) $grant->section_id === (int) $target->section_id;
    }

    private function validAssignment(AccessGrant $grant): bool
    {
        if ($grant->source_kind !== 'private_assignment') {
            return true;
        }
        $assignment = $grant->learningAssignment;

        return $assignment !== null && $assignment->status === 'assigned'
            && ($assignment->booking_id === null || (int) $assignment->booking?->student_id === (int) $grant->student_id);
    }

    /** Merge history retains the earliest relative-rule anchor without restarting access.
     * @param  Collection<int, Enrollment>  $rows
     * @return Collection<int, Enrollment>
     */
    private function anchors(Collection $rows, string $key): Collection
    {
        return $rows->groupBy($key)->filter(fn (Collection $group): bool => $group->contains('status', 'enrolled'))
            ->map(fn (Collection $group): Enrollment => $group->sortBy('enrolled_at')->first());
    }

    private function published(Course|Section|Lesson $target): bool
    {
        return $target->status === 'published' && $target->published_at !== null && $target->published_at->lte(now('UTC'));
    }

    private function readyBlock(LessonBlock $block): bool
    {
        if ($block->status !== 'ready') {
            return false;
        }
        if ($block->resource_id !== null) {
            return $block->resource?->isPublished() ?? false;
        }
        if (in_array($block->kind, ['image', 'file'], true)) {
            $asset = $block->asset;
            $course = $block->lesson?->course;

            return $asset !== null && $course !== null && $asset->kind === $block->kind && $asset->usableFor($course) && $this->files->courseAssetAvailable($asset);
        }
        if ($block->kind === 'video') {
            return $block->videoAsset !== null && $block->lesson?->course !== null && $block->videoAsset->usableFor($block->lesson->course);
        }

        return true;
    }
}

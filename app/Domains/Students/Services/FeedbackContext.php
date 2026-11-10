<?php

namespace App\Domains\Students\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsLearningGate;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Builder;

class FeedbackContext
{
    /** @return array{id:int,type:string,label:string,url:string}|null */
    public function resolve(Student $student, string $type, int $id): ?array
    {
        $model = match ($type) {
            'course' => Course::query()->find($id),
            'lesson' => Lesson::query()->find($id),
            'homework' => app(StudentTeachingReadModel::class)->shared(Homework::query(), $student)->find($id),
            'booking' => Booking::query()->where('student_id', $student->id)->find($id),
            default => null,
        };
        if (! $model) {
            return null;
        }
        if (in_array($type, ['course', 'lesson'], true) && (! app(LmsAccessService::class)->canAccess($student, $model)
            || ($model instanceof Lesson && ! app(LmsLearningGate::class)->decision($student, $model)['allowed']))) {
            return null;
        }
        $label = match ($type) {
            'booking' => __('feedback.session_reference', ['id' => $model->id]),
            default => $model->title,
        };
        $url = match ($type) {
            'course' => route('student.learning.courses.show', $model->id, false),
            'lesson' => route('student.learning.lessons.show', [$model->course_id, $model->id], false),
            'homework' => route('student.teaching.index', [], false).'#homework-'.$model->id,
            'booking' => route('student.bookings.show', $model->id, false),
            default => throw new \UnhandledMatchError('Unsupported feedback context.'),
        };

        return ['id' => (int) $model->id, 'type' => $type, 'label' => $label, 'url' => $url];
    }

    /** Bounded candidate reads; only authorized labels leave this service.
     * @return list<array{id:int,type:string,label:string,url:string}>
     */
    public function search(Student $student, string $type, string $term): array
    {
        $query = match ($type) {
            'course' => Course::query()->where('status', 'published')->where(fn (Builder $q) => $q->where('kind', 'catalog')->orWhere('owner_student_id', $student->id)),
            'lesson' => Lesson::query()->where('status', 'published')->whereHas('course', fn (Builder $q) => $q->where('kind', 'catalog')->orWhere('owner_student_id', $student->id)),
            'homework' => app(StudentTeachingReadModel::class)->shared(Homework::query(), $student),
            'booking' => Booking::query()->where('student_id', $student->id),
            default => null,
        };
        if (! $query) {
            return [];
        }
        if ($term !== '') {
            $type === 'booking' ? $query->whereKey(ctype_digit($term) ? (int) $term : 0) : $query->where('title', 'like', '%'.addcslashes($term, '%_\\').'%');
        }
        $results = [];
        foreach ($query->orderByDesc('id')->limit(20)->get(['id']) as $candidate) {
            if ($resolved = $this->resolve($student, $type, $candidate->id)) {
                $results[] = $resolved;
            }
        }

        return $results;
    }
}

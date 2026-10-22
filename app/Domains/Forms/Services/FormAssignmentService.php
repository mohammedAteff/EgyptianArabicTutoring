<?php

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\Form;
use App\Domains\Students\Models\Student;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FormAssignmentService
{
    /** @return Collection<int, Form> */
    public function assignedTo(Student $student): Collection
    {
        return $this->assignedFormQuery($student)
            ->with('publishedVersion:id,form_id,version_number')
            ->orderBy('title')
            ->get();
    }

    public function isAssignedTo(Form $form, Student $student): bool
    {
        return $this->assignedFormQuery($student)->whereKey($form->getKey())->exists();
    }

    /** @param list<int> $studentIds
     * @return Collection<int, Form>
     */
    public function assignedToStudents(array $studentIds, bool $includeIntake = false): Collection
    {
        if ($studentIds === []) {
            return new Collection;
        }
        $query = Form::query()->where(function (Builder $query) use ($includeIntake): void {
            $query->whereIn('forms.id', $this->assignedFormQuery(DB::raw('students.id'))->select('forms.id'));
            if ($includeIntake) {
                $query->orWhere(fn (Builder $intake) => $intake->where('forms.slug', 'short-form')->where('forms.status', 'published')->whereNotNull('forms.published_version_id'));
            }
        });

        return $query->crossJoin('students')->whereIn('students.id', $studentIds)
            ->select('forms.*', 'students.id as assigned_student_id')->orderBy('forms.title')->get();
    }

    /** @return Builder<Form> */
    private function assignedFormQuery(Student|Expression $student): Builder
    {
        $studentId = $student instanceof Student ? $student->getKey() : $student;
        $nowUtc = now('UTC')->toDateTimeString();

        return Form::query()
            ->where('status', 'published')
            ->whereNotNull('published_version_id')
            ->where(function (Builder $query) use ($studentId, $nowUtc): void {
                $query->whereDoesntHave('triggers', fn ($q) => $q->whereIn('trigger_name', ['after_booking', 'after_reschedule', 'next_session_check', 'pre_booking']))
                    ->orWhere(function (Builder $query) use ($studentId): void {
                        $query->whereHas('triggers', fn ($q) => $q->where('trigger_name', 'after_booking'))
                            ->whereExists(fn ($bookings) => $bookings
                                ->selectRaw('1')
                                ->from('bookings')
                                ->where('student_id', $studentId)
                                ->whereNull('deleted_at'));
                    })
                    ->orWhere(function (Builder $query) use ($studentId): void {
                        $query->whereHas('triggers', fn ($q) => $q->where('trigger_name', 'after_reschedule'))
                            ->whereExists(fn ($reschedules) => $reschedules
                                ->selectRaw('1')
                                ->from('session_reschedules')
                                ->join('bookings', 'bookings.id', '=', 'session_reschedules.booking_id')
                                ->where('bookings.student_id', $studentId)
                                ->whereNull('bookings.deleted_at'));
                    })
                    ->orWhere(function (Builder $query) use ($studentId, $nowUtc): void {
                        $query->whereHas('triggers', fn ($q) => $q->where('trigger_name', 'next_session_check'))
                            ->whereExists(fn ($bookings) => $bookings
                                ->selectRaw('1')
                                ->from('bookings')
                                ->where('student_id', $studentId)
                                ->where('status', 'confirmed')
                                ->where('start_at_utc', '>', $nowUtc)
                                ->whereNull('deleted_at'));
                    })
                    ->orWhereExists(fn ($submissions) => $submissions
                        ->selectRaw('1')
                        ->from('form_versions')
                        ->join('form_submissions', 'form_submissions.form_version_id', '=', 'form_versions.id')
                        ->whereColumn('form_versions.form_id', 'forms.id')
                        ->where('form_submissions.student_id', $studentId));
            });
    }
}

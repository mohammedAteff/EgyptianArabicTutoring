<?php

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\Form;
use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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

    /** @return Builder<Form> */
    private function assignedFormQuery(Student $student): Builder
    {
        $studentId = $student->getKey();
        $nowUtc = now('UTC')->toDateTimeString();

        return Form::query()
            ->where('status', 'published')
            ->whereNotNull('published_version_id')
            ->where(function (Builder $query) use ($studentId, $nowUtc): void {
                $query->where('prompt_trigger', 'none')
                    ->orWhere(function (Builder $query) use ($studentId): void {
                        $query->where('prompt_trigger', 'after_booking')
                            ->whereExists(fn ($bookings) => $bookings
                                ->selectRaw('1')
                                ->from('bookings')
                                ->where('student_id', $studentId)
                                ->whereNull('deleted_at'));
                    })
                    ->orWhere(function (Builder $query) use ($studentId): void {
                        $query->where('prompt_trigger', 'after_reschedule')
                            ->whereExists(fn ($reschedules) => $reschedules
                                ->selectRaw('1')
                                ->from('session_reschedules')
                                ->join('bookings', 'bookings.id', '=', 'session_reschedules.booking_id')
                                ->where('bookings.student_id', $studentId)
                                ->whereNull('bookings.deleted_at'));
                    })
                    ->orWhere(function (Builder $query) use ($studentId, $nowUtc): void {
                        $query->where('prompt_trigger', 'next_session_check')
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

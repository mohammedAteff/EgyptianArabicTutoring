<?php

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Collection;

class FormCompletionReadModel
{
    public function __construct(private FormAssignmentService $assignments) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forStudent(Student $student): Collection
    {
        $forms = $this->assignments->assignedTo($student);
        $forms->load(['publishedVersion' => fn ($query) => $query->withCount(['questions' => fn ($questions) => $questions->where('question_type', '!=', 'info_block')])]);
        $submissions = FormSubmission::query()->where('student_id', $student->id)->whereIn('form_version_id', $forms->pluck('published_version_id'))
            ->withCount(['answers' => fn ($query) => $query->whereNotNull('value_text')->whereRaw("TRIM(value_text) <> ''")->where('value_text', '!=', '[]')])->orderByDesc('id')->get();

        return $forms->map(function ($form) use ($submissions): array {
            $submission = $submissions->firstWhere('form_version_id', $form->published_version_id);
            $total = (int) $form->publishedVersion->questions_count;
            $answered = min($total, (int) ($submission?->answers_count ?? 0));

            return ['form_id' => $form->id, 'slug' => $form->slug, 'title' => $form->title, 'mandatory' => $form->is_mandatory,
                'status' => $submission?->status ?? 'missing', 'answered' => $answered, 'total' => $total,
                'saved_at' => $submission?->updated_at, 'submitted_at' => $submission?->submitted_at];
        });
    }
}

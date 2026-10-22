<?php

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Students\Models\Student;
use Illuminate\Support\Collection;

class FormCompletionReadModel
{
    public function __construct(private FormAssignmentService $assignments) {}

    /** @return Collection<int, array<string, mixed>> */
    public function forStudent(Student $student, bool $includeIntake = false): Collection
    {
        $forms = $this->assignments->assignedTo($student);
        if ($includeIntake) {
            $intake = Form::query()->where('slug', 'short-form')->where('status', 'published')->whereNotNull('published_version_id')->first();
            if ($intake && ! $forms->contains('id', $intake->id)) {
                $forms->push($intake);
            }
        }
        $forms->load(['publishedVersion' => fn ($query) => $query->withCount(['questions' => fn ($questions) => $questions->where('question_type', '!=', 'info_block')])]);

        return $this->metadata($forms, $this->submissions([$student->id], $forms->pluck('published_version_id')->all()));
    }

    /** @param list<int> $studentIds
     * @return Collection<int, Collection<int, array<string, mixed>>>
     */
    public function forStudents(array $studentIds, bool $includeIntake = false): Collection
    {
        $forms = $this->assignments->assignedToStudents($studentIds, $includeIntake);
        $forms->load(['publishedVersion' => fn ($query) => $query->withCount(['questions' => fn ($questions) => $questions->where('question_type', '!=', 'info_block')])]);
        $submissions = $this->submissions($studentIds, $forms->pluck('published_version_id')->unique()->all())->groupBy('student_id');

        return collect($studentIds)->mapWithKeys(fn (int $id): array => [$id => $this->metadata($forms->where('assigned_student_id', $id)->values(), $submissions->get($id, collect()))]);
    }

    /** @param list<int> $studentIds
     * @param  list<int>  $versionIds
     * @return Collection<int, FormSubmission>
     */
    private function submissions(array $studentIds, array $versionIds): Collection
    {
        return FormSubmission::query()->whereIn('student_id', $studentIds)->whereIn('form_version_id', $versionIds)
            ->withCount(['answers' => fn ($query) => $query->whereNotNull('value_text')->whereRaw("TRIM(value_text) <> ''")->where('value_text', '!=', '[]')])->orderByDesc('id')->get();
    }

    /** @param \Illuminate\Database\Eloquent\Collection<int, Form> $forms
     * @param  Collection<int, FormSubmission>  $submissions
     * @return Collection<int, array<string, mixed>>
     */
    private function metadata(\Illuminate\Database\Eloquent\Collection $forms, Collection $submissions): Collection
    {

        return $forms->map(fn (Form $form): array => $this->formMetadata($form, $submissions));
    }

    /** @param Collection<int, FormSubmission> $submissions
     * @return array<string, mixed>
     */
    private function formMetadata(Form $form, Collection $submissions): array
    {

        $submission = $submissions->firstWhere('form_version_id', $form->published_version_id);
        $total = (int) $form->publishedVersion->getAttribute('questions_count');
        $answered = min($total, (int) ($submission?->getAttribute('answers_count') ?? 0));

        return ['form_id' => $form->id, 'form_version_id' => $form->published_version_id, 'slug' => $form->slug, 'title' => $form->title, 'mandatory' => $form->is_mandatory,
            'status' => $submission->status ?? 'missing', 'has_submitted' => $submissions->contains(fn (FormSubmission $entry): bool => (int) $entry->form_version_id === (int) $form->published_version_id && $entry->status === 'submitted'), 'answered' => $answered, 'total' => $total,
            'saved_at' => $submission?->updated_at, 'submitted_at' => $submission?->submitted_at];
    }
}

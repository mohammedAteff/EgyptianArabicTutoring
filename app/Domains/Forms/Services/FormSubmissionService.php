<?php

namespace App\Domains\Forms\Services;

use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormAnswer;
use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormSubmissionRevision;
use App\Domains\Students\Models\Student;
use Illuminate\Validation\ValidationException;

class FormSubmissionService
{
    public function __construct(
        private FormValidationService $validation,
        private DatabaseCapability $database,
        private FormAssignmentService $formAssignments,
    ) {}

    /** @param array<string, mixed> $answers */
    public function save(Student $student, Form $form, array $answers, bool $submit, ?int $submissionId = null): FormSubmission
    {
        return $this->database->transaction(function () use ($student, $form, $answers, $submit, $submissionId): FormSubmission {
            $lockedForm = Form::query()->whereKey($form->id)->lockForUpdate()->firstOrFail();
            $student = Student::verified()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->formAssignments->isAssignedTo($lockedForm, $student), 404);
            $submission = null;
            $isNewSubmission = false;
            if ($submissionId !== null) {
                if ($lockedForm->status !== 'published') {
                    abort(404);
                }
                $submission = FormSubmission::query()
                    ->whereKey($submissionId)
                    ->where('student_id', $student->id)
                    ->whereHas('version', fn ($query) => $query->where('form_id', $lockedForm->id))
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($submission->status === 'submitted' && ! $lockedForm->can_edit_after_submission) {
                    throw ValidationException::withMessages(['form' => 'This submitted form can no longer be edited.']);
                }
                $version = $submission->version()->with('questions.options')->firstOrFail();
            } else {
                if ($lockedForm->status !== 'published' || ! $lockedForm->published_version_id) {
                    abort(404);
                }
                $version = $lockedForm->publishedVersion()->with('questions.options')->firstOrFail();
                $submission = FormSubmission::query()
                    ->where('form_version_id', $version->id)
                    ->where('student_id', $student->id)
                    ->orderByDesc('submission_revision')
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->lockForUpdate()
                    ->first();

                if ($submission?->status === 'submitted' && ! $lockedForm->can_edit_after_submission) {
                    throw ValidationException::withMessages(['form' => 'This form has already been submitted.']);
                }

                if (! $submission) {
                    $submission = FormSubmission::create([
                        'form_version_id' => $version->id,
                        'student_id' => $student->id,
                        'status' => 'draft',
                        'submission_revision' => 1,
                    ]);
                    $isNewSubmission = true;
                }
            }

            $normalizedAnswers = $this->validation->validateAnswers($version->questions->all(), $answers, $submit);
            $questionIds = $version->questions->keyBy('question_key');
            $answerQuestionIds = collect(array_keys($normalizedAnswers))->map(fn (string $key): int => (int) $questionIds->get($key)->id)->all();
            $submission->answers()->when(
                $answerQuestionIds === [],
                fn ($query) => $query->delete(),
                fn ($query) => $query->whereNotIn('form_question_id', $answerQuestionIds)->delete(),
            );

            foreach ($normalizedAnswers as $key => $value) {
                $question = $questionIds->get($key);
                $storedValue = is_array($value) ? json_encode($value, JSON_THROW_ON_ERROR) : (is_bool($value) ? ($value ? '1' : '0') : (string) $value);
                FormAnswer::query()->updateOrCreate(
                    ['form_submission_id' => $submission->id, 'form_question_id' => $question->id],
                    ['value_text' => $storedValue],
                );
            }

            if (! $isNewSubmission) {
                $submission->submission_revision++;
            }
            $submission->status = $submit ? 'submitted' : 'draft';
            if ($submit) {
                $submission->submitted_at = now('UTC');
            }
            $submission->save();

            $snapshot = [];
            foreach ($normalizedAnswers as $key => $value) {
                $snapshot[$key] = $value;
            }
            FormSubmissionRevision::create([
                'form_submission_id' => $submission->id,
                'revision_number' => $submission->submission_revision,
                'snapshot_answers' => $snapshot,
                'submitted_at' => $submission->submitted_at,
                'created_at' => now('UTC'),
            ]);

            return $submission->fresh(['version.form', 'version.questions.options', 'answers.question']);
        }, 3);
    }
}

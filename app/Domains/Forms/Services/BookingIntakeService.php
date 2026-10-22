<?php

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\Form;
use Illuminate\Validation\ValidationException;

class BookingIntakeService
{
    public function publishedIntakeForm(): ?Form
    {
        return Form::query()->where('status', 'published')
            ->whereNotNull('published_version_id')
            ->whereHas('triggers', fn ($query) => $query->where('trigger_name', 'pre_booking'))
            ->with('publishedVersion.questions.options')->first();
    }

    /** @param array<string|int, mixed> $answers @return array<string, mixed> */
    public function validatePublicIntake(?int $versionId, array $answers): array
    {
        $form = $this->publishedIntakeForm();
        if ($versionId !== $form?->published_version_id) {
            throw ValidationException::withMessages([
                'intakeForm' => 'The intake form was updated while you were booking. Please review the updated questions.',
            ]);
        }
        if (! $form) {
            if ($answers !== []) {
                throw ValidationException::withMessages(['intakeForm' => 'No published intake form is available.']);
            }

            return [];
        }

        $questions = $form->publishedVersion->questions;
        $byId = $questions->keyBy(fn ($question): string => (string) $question->id);
        $byKey = $questions->keyBy('question_key');
        $normalized = [];
        foreach ($answers as $key => $value) {
            $question = $byId->get((string) $key) ?? $byKey->get((string) $key);
            if (! $question) {
                throw ValidationException::withMessages(["intakeAnswers.{$key}" => 'Submitted question ID does not belong to the active form version.']);
            }
            if (array_key_exists($question->question_key, $normalized)) {
                throw ValidationException::withMessages(["intakeAnswers.{$question->id}" => 'Submit only one answer per question.']);
            }
            $normalized[$question->question_key] = $value;
        }

        try {
            return app(FormValidationService::class)->validateAnswers($questions->all(), $normalized);
        } catch (ValidationException $exception) {
            $errors = [];
            foreach ($exception->errors() as $key => $messages) {
                $parts = explode('.', $key);
                $question = $byKey->get($parts[1] ?? '');
                $errors['intakeAnswers.'.($question->id ?? ($parts[1] ?? $key))] = $messages;
            }
            throw ValidationException::withMessages($errors);
        }
    }
}

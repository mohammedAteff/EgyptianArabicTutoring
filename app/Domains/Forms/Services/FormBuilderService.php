<?php

namespace App\Domains\Forms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormQuestionOption;
use App\Domains\Forms\Models\FormVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormBuilderService
{
    public function __construct(private FormValidationService $validation) {}

    /** @param array<string, mixed> $metadata @param array<int, array<string, mixed>> $questions */
    public function create(array $metadata, array $questions, Administrator $author): Form
    {
        $this->validation->validateStructure($questions);

        return DB::transaction(function () use ($metadata, $questions, $author): Form {
            $form = Form::create([
                ...$metadata,
                'status' => 'draft',
                'created_by' => $author->id,
                'lock_version' => 1,
            ]);
            $version = $form->versions()->create(['version_number' => 1]);
            $form->update(['active_version_id' => $version->id]);
            $this->replaceQuestions($version, $questions);

            return $form->fresh(['activeVersion.questions.options']);
        }, 3);
    }

    /** @param array<string, mixed> $metadata @param array<int, array<string, mixed>> $questions */
    public function update(int $formId, array $metadata, array $questions, int $baseVersionId, int $lockVersion): Form
    {
        $this->validation->validateStructure($questions);

        return DB::transaction(function () use ($formId, $metadata, $questions, $baseVersionId, $lockVersion): Form {
            $form = Form::query()->whereKey($formId)->lockForUpdate()->firstOrFail();
            if ((int) $form->active_version_id !== $baseVersionId || (int) $form->lock_version !== $lockVersion) {
                abort(409, 'The form changed while you were editing. Reload the current version.');
            }

            $version = FormVersion::query()->whereKey($baseVersionId)->where('form_id', $form->id)->lockForUpdate()->firstOrFail();
            if ($version->isFrozen()) {
                $version = $form->versions()->create([
                    'version_number' => ((int) $form->versions()->max('version_number')) + 1,
                    'changelog' => $metadata['changelog'] ?? null,
                ]);
                $form->active_version_id = $version->id;
            }

            $this->replaceQuestions($version, $questions);
            $form->fill(collect($metadata)->except('changelog')->all());
            $form->lock_version++;
            $form->save();

            return $form->fresh(['activeVersion.questions.options']);
        }, 3);
    }

    public function publish(int $formId, int $baseVersionId, int $lockVersion): Form
    {
        return DB::transaction(function () use ($formId, $baseVersionId, $lockVersion): Form {
            $form = Form::query()->whereKey($formId)->lockForUpdate()->firstOrFail();
            if ((int) $form->active_version_id !== $baseVersionId || (int) $form->lock_version !== $lockVersion) {
                abort(409, 'The form changed while you were editing. Reload the current version.');
            }
            $version = FormVersion::query()->whereKey($baseVersionId)->where('form_id', $form->id)->lockForUpdate()->firstOrFail();
            if ($version->isFrozen() && $form->status !== 'published') {
                abort(409, 'A form version with student submissions cannot be published or reactivated.');
            }
            if (! $version->questions()->exists()) {
                throw ValidationException::withMessages(['questions' => 'Add at least one question before publishing.']);
            }

            $form->update([
                'status' => 'published',
                'published_version_id' => $version->id,
                'lock_version' => $form->lock_version + 1,
            ]);

            return $form->fresh(['activeVersion.questions.options']);
        }, 3);
    }

    public function archive(int $formId, int $lockVersion): Form
    {
        return DB::transaction(function () use ($formId, $lockVersion): Form {
            $form = Form::query()->whereKey($formId)->lockForUpdate()->firstOrFail();
            if ((int) $form->lock_version !== $lockVersion) {
                abort(409, 'The form changed while you were editing. Reload the current version.');
            }
            $form->update(['status' => 'archived', 'lock_version' => $form->lock_version + 1]);

            return $form;
        }, 3);
    }

    /** @param array<int, array<string, mixed>> $questions */
    private function replaceQuestions(FormVersion $version, array $questions): void
    {
        $version->questions()->delete();
        foreach (array_values($questions) as $index => $attributes) {
            $options = $attributes['options'] ?? [];
            $question = $version->questions()->create([
                'question_key' => $attributes['question_key'],
                'label' => $attributes['label'],
                'description' => $attributes['description'] ?? null,
                'question_type' => $attributes['question_type'],
                'is_required' => (bool) ($attributes['is_required'] ?? false),
                'assistant_visible' => (bool) ($attributes['assistant_visible'] ?? true),
                'sort_order' => (int) ($attributes['sort_order'] ?? $index),
                'validation_rules' => $attributes['validation_rules'] ?? null,
                'presentation_config' => $attributes['presentation_config'] ?? null,
                'conditional_logic' => $attributes['conditional_logic'] ?? null,
            ]);
            foreach (array_values($options) as $optionIndex => $option) {
                FormQuestionOption::create([
                    'form_question_id' => $question->id,
                    'label' => $option['label'],
                    'value' => $option['value'],
                    'sort_order' => (int) ($option['sort_order'] ?? $optionIndex),
                ]);
            }
        }
    }
}

<?php

namespace App\Domains\Forms\Services;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Models\FormQuestionOption;
use App\Domains\Forms\Models\FormTrigger;
use App\Domains\Forms\Models\FormVersion;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FormBuilderService
{
    public function __construct(private FormValidationService $validation) {}

    /** @param array<string, mixed> $metadata @param array<int, array<string, mixed>> $questions */
    public function create(array $metadata, array $questions, Administrator $author): Form
    {
        $this->validation->validateStructure($questions);

        return $this->withPublicationLock(fn (): Form => DB::transaction(function () use ($metadata, $questions, $author): Form {
            $triggers = $metadata['triggers'] ?? array_filter([$metadata['trigger'] ?? $metadata['trigger_name'] ?? null]);
            $formMetadata = collect($metadata)->except(['trigger', 'trigger_name', 'triggers'])->all();

            $form = Form::create([
                ...$formMetadata,
                'status' => 'draft',
                'created_by' => $author->id,
                'lock_version' => 1,
            ]);
            $version = $form->versions()->create(['version_number' => 1]);
            $form->update(['active_version_id' => $version->id]);
            $this->replaceQuestions($version, $questions);

            foreach (array_unique($triggers) as $trigger) {
                if (in_array($trigger, FormTrigger::ALLOWED_TRIGGERS, true)) {
                    $form->triggers()->create(['trigger_name' => $trigger]);
                }
            }

            return $form->fresh(['activeVersion.questions.options', 'triggers']);
        }, 3));
    }

    /** @param array<string, mixed> $metadata @param array<int, array<string, mixed>> $questions */
    public function update(int $formId, array $metadata, array $questions, int $baseVersionId, int $lockVersion): Form
    {
        $this->validation->validateStructure($questions);

        return $this->withPublicationLock(fn (): Form => DB::transaction(function () use ($formId, $metadata, $questions, $baseVersionId, $lockVersion): Form {
            $form = Form::query()->whereKey($formId)->lockForUpdate()->firstOrFail();
            if ((int) $form->active_version_id !== $baseVersionId || (int) $form->lock_version !== $lockVersion) {
                abort(409, 'The form changed while you were editing. Reload the current version.');
            }

            $originalCreatedBy = $form->created_by;

            $version = FormVersion::query()->whereKey($baseVersionId)->where('form_id', $form->id)->lockForUpdate()->firstOrFail();
            if ($version->isFrozen() || (int) $form->published_version_id === (int) $version->id) {
                $version = $form->versions()->create([
                    'version_number' => ((int) $form->versions()->max('version_number')) + 1,
                    'changelog' => $metadata['changelog'] ?? null,
                ]);
                $form->active_version_id = $version->id;
            }

            $this->replaceQuestions($version, $questions);

            $triggers = $metadata['triggers'] ?? array_filter([$metadata['trigger'] ?? $metadata['trigger_name'] ?? null]);
            $formMetadata = collect($metadata)->except(['changelog', 'trigger', 'trigger_name', 'triggers', 'created_by'])->all();

            $form->fill($formMetadata);
            $form->created_by = $originalCreatedBy;
            $form->lock_version++;
            $form->save();

            if (array_key_exists('trigger', $metadata) || array_key_exists('trigger_name', $metadata) || array_key_exists('triggers', $metadata)) {
                if (in_array('pre_booking', $triggers, true) && $form->status === 'published') {
                    FormTrigger::where('trigger_name', 'pre_booking')
                        ->where('form_id', '!=', $form->id)
                        ->whereHas('form', fn ($query) => $query->where('status', 'published'))
                        ->delete();
                }
                $form->triggers()->delete();
                foreach (array_unique($triggers) as $trigger) {
                    if (in_array($trigger, FormTrigger::ALLOWED_TRIGGERS, true)) {
                        $form->triggers()->create(['trigger_name' => $trigger]);
                    }
                }
            }

            return $form->fresh(['activeVersion.questions.options', 'triggers']);
        }, 3));
    }

    public function publish(int $formId, int $baseVersionId, int $lockVersion): Form
    {
        return $this->withPublicationLock(fn (): Form => app(DatabaseCapability::class)->transaction(function () use ($formId, $baseVersionId, $lockVersion): Form {
            $form = Form::query()->whereKey($formId)->lockForUpdate()->firstOrFail();
            if ((int) $form->active_version_id !== $baseVersionId || (int) $form->lock_version !== $lockVersion) {
                abort(409, 'The form changed while you were editing. Reload the current version.');
            }
            if (! $form->active_version_id) {
                throw new \DomainException('Cannot publish a form without an active draft version.');
            }
            $version = $form->versions()->whereKey($form->active_version_id)->lockForUpdate()->first();
            if (! $version) {
                throw new \DomainException('The active version does not belong to this form.');
            }
            if ($version->isFrozen() && $form->status !== 'published') {
                abort(409, 'A form version with student submissions cannot be published or reactivated.');
            }
            if (! $version->questions()->exists()) {
                throw ValidationException::withMessages(['questions' => 'Add at least one question before publishing.']);
            }

            if ($form->triggers()->where('trigger_name', 'pre_booking')->exists()) {
                FormTrigger::where('trigger_name', 'pre_booking')
                    ->where('form_id', '!=', $form->id)
                    ->whereHas('form', fn ($query) => $query->where('status', 'published'))
                    ->delete();
            }
            $form->update([
                'status' => 'published',
                'published_version_id' => $version->id,
                'lock_version' => $form->lock_version + 1,
            ]);

            return $form->fresh(['activeVersion.questions.options', 'triggers']);
        }));
    }

    private function withPublicationLock(Closure $callback): Form
    {
        $connection = DB::connection();
        $lockKey = substr($connection->getDatabaseName(), 0, 20).':pub:pre_booking';
        if (strlen($lockKey) > 64) {
            throw new \LogicException('Publication lock key exceeds 64 characters.');
        }
        $lock = $connection->selectOne('SELECT GET_LOCK(?, 10) AS acquired', [$lockKey], useReadPdo: false);
        if ((int) ($lock?->acquired ?? 0) !== 1) {
            throw new \DomainException('Another form publication is currently in progress. Please retry.');
        }
        try {
            return $callback();
        } finally {
            $released = $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockKey], useReadPdo: false);
            if ((int) ($released?->released ?? 0) !== 1) {
                throw new \LogicException('Failed to release publication lock.');
            }
        }
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
            $questionKey = ! empty($attributes['question_key'])
                ? (string) $attributes['question_key']
                : 'q_'.((int) ($attributes['sort_order'] ?? $index));

            $question = $version->questions()->create([
                'question_key' => $questionKey,
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

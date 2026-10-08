<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LmsLearningDefinition
{
    /** @param array<string,mixed> $data
     * @return array<string,mixed> */
    public function rules(array $data): array
    {
        $values = Validator::make($data, [
            'required' => ['required', 'boolean'],
            'methods' => ['required', 'array', 'min:1', 'max:6'],
            'methods.*' => ['required', Rule::in(['manual', 'video', 'quiz_complete', 'quiz_pass', 'assignment_submit', 'assignment_approve'])],
            'video_threshold' => ['required', 'integer', 'between:1,100'],
            'prerequisite_key' => ['nullable', 'string', 'max:100'],
            'drip_mode' => ['required', Rule::in(['immediate', 'relative', 'fixed'])],
            'drip_days' => ['nullable', 'integer', 'between:0,3650'],
            'drip_at' => ['nullable', 'date_format:Y-m-d\TH:i:sP'],
        ])->validate();
        if (($values['drip_mode'] === 'relative' && ! isset($values['drip_days'])) || ($values['drip_mode'] === 'fixed' && ! isset($values['drip_at']))) {
            $this->invalid('Specify days or a date with its timezone offset for the selected drip mode.');
        }

        return ['required' => (bool) $values['required'], 'methods' => array_values(array_unique($values['methods'])),
            'video_threshold' => (int) $values['video_threshold'], 'prerequisite_key' => $values['prerequisite_key'] ?? null,
            'drip_mode' => $values['drip_mode'], 'drip_days' => $values['drip_mode'] === 'relative' ? (int) $values['drip_days'] : null,
            'drip_at' => $values['drip_mode'] === 'fixed' ? CarbonImmutable::parse($values['drip_at'])->utc()->toIso8601String() : null];
    }

    /** @return array<string,mixed> */
    public function lessonRules(Lesson $lesson): array
    {
        return $lesson->learning_rules ?? ['required' => true, 'methods' => ['manual'], 'video_threshold' => 95,
            'prerequisite_key' => null, 'drip_mode' => 'immediate', 'drip_days' => null, 'drip_at' => null];
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed> */
    public function assessment(string $kind, array $data): array
    {
        // Native author forms use readable lists; the retained definition has strict typed values.
        if ($kind === 'quiz' && is_array($data['questions'] ?? null)) {
            foreach ($data['questions'] as &$question) {
                if (! is_array($question)) {
                    $this->invalid('Use valid question fields.');
                }
                if (! array_key_exists('answer_text', $question)) {
                    continue;
                }
                $lines = fn (string $text): array => array_values(array_filter(array_map('trim', preg_split('/\r?\n/u', $text) ?: []), fn (string $line): bool => $line !== ''));
                $question['options'] = $lines((string) ($question['options_text'] ?? ''));
                $text = trim((string) $question['answer_text']);
                $question['answer'] = match ($question['type'] ?? '') {
                    'choice' => ctype_digit($text) ? (int) $text - 1 : null,
                    'multiple' => array_map(fn (string $index): mixed => ctype_digit(trim($index)) ? (int) trim($index) - 1 : null, explode(',', $text)),
                    'boolean' => match ($text) {
                        'true' => true, 'false' => false, default => null
                    },
                    'matching' => $lines($text),
                    default => null,
                };
                $question['accepted'] = $lines($text);
                $question['points'] = (int) ($question['points'] ?? 0);
                if (in_array($question['type'] ?? '', ['blank', 'written', 'boolean'], true)) {
                    unset($question['options']);
                }
                if (($question['type'] ?? '') !== 'blank') {
                    unset($question['accepted']);
                }
            }
            unset($question);
            $data['questions'] = array_values($data['questions']);
        }
        if ($kind === 'assignment') {
            $v = Validator::make($data, ['title' => ['required', 'string', 'max:200'], 'instructions' => ['required', 'string', 'max:10000'],
                'types' => ['required', 'array', 'min:1', 'max:5'], 'types.*' => ['required', Rule::in(['text', 'file', 'audio', 'video', 'external_link'])]])->validate();

            return ['title' => $v['title'], 'instructions' => $v['instructions'], 'types' => array_values(array_unique($v['types']))];
        }
        $v = Validator::make($data, ['title' => ['required', 'string', 'max:200'], 'passing_score' => ['required', 'integer', 'between:0,100'],
            'attempt_limit' => ['required', 'integer', 'between:1,100'], 'review_policy' => ['required', Rule::in(['after_grading', 'never'])],
            'questions' => ['required', 'array', 'min:1', 'max:100'], 'questions.*.type' => ['required', Rule::in(['choice', 'multiple', 'boolean', 'blank', 'matching', 'written'])],
            'questions.*.prompt' => ['required', 'string', 'max:3000'], 'questions.*.points' => ['required', 'integer', 'between:1,100'],
            'questions.*.options' => ['sometimes', 'array', 'min:2', 'max:20'], 'questions.*.options.*' => ['string', 'max:500'],
            'questions.*.answer' => ['sometimes'], 'questions.*.accepted' => ['sometimes', 'array', 'min:1', 'max:20'],
            'questions.*.accepted.*' => ['string', 'max:500']])->validate();
        $questions = [];
        foreach ($v['questions'] as $q) {
            $clean = ['type' => $q['type'], 'prompt' => $q['prompt'], 'points' => (int) $q['points']];
            $type = $q['type'];
            if (in_array($type, ['choice', 'multiple', 'matching'], true)) {
                if (! isset($q['options']) || ! array_is_list($q['options'])) {
                    $this->invalid('These questions need an ordered list of options.');
                }
                if (array_any($q['options'], fn (string $option): bool => trim($option) === '')) {
                    $this->invalid('Option labels cannot be blank.');
                }
                $clean['options'] = $q['options'];
            }
            $answer = $q['answer'] ?? null;
            if ($type === 'choice' && (! is_int($answer) || ! isset($q['options'][$answer]))) {
                $this->invalid('Choose one valid option index.');
            }
            if ($type === 'multiple' && (! is_array($answer) || ! array_is_list($answer) || $answer === [] || count($answer) !== count(array_unique($answer)))) {
                $this->invalid('Choose distinct answer indices.');
            }
            if ($type === 'multiple') {
                foreach ($answer as $index) {
                    if (! is_int($index) || ! isset($q['options'][$index])) {
                        $this->invalid('Invalid option index.');
                    }
                }
                sort($answer);
            }
            if ($type === 'boolean' && ! is_bool($answer)) {
                $this->invalid('A true/false answer must be a JSON boolean.');
            }
            if ($type === 'matching' && (! is_array($answer) || ! array_is_list($answer) || count($answer) !== count($q['options']))) {
                $this->invalid('Matching needs one target label for each option.');
            }
            if ($type === 'matching') {
                foreach ($answer as $label) {
                    if (! is_string($label) || trim($label) === '' || mb_strlen($label) > 500) {
                        $this->invalid('Use bounded matching labels.');
                    }
                }
                if (count($answer) !== count(array_unique($answer))) {
                    $this->invalid('Matching target labels must be distinct.');
                }
            }
            if ($type === 'blank' && empty($q['accepted'])) {
                $this->invalid('Add at least one accepted answer.');
            }
            if ($type === 'blank') {
                if (array_any($q['accepted'], fn (string $answer): bool => $this->normalizeBlank($answer) === '')) {
                    $this->invalid('Accepted answers cannot be blank.');
                }
                $clean['accepted'] = array_values($q['accepted']);
            } elseif ($type !== 'written') {
                $clean['answer'] = $answer;
            }
            $questions[] = $clean;
        }

        return ['title' => $v['title'], 'passing_score' => (int) $v['passing_score'], 'attempt_limit' => (int) $v['attempt_limit'],
            'review_policy' => $v['review_policy'], 'questions' => $questions];
    }

    /** Conservative whitespace/case only: Arabic letters and diacritics remain distinct. */
    public function normalizeBlank(string $value): string
    {
        return mb_strtolower(trim(preg_replace('/\\s+/u', ' ', $value) ?? $value), 'UTF-8');
    }

    /** @param array<string,mixed> $data */
    public function hash(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function mediaHash(LessonBlock $block): string
    {
        $asset = $block->videoAsset;

        return $this->hash(['asset' => $block->video_asset_id, 'library' => $asset?->library_id, 'video' => $asset?->provider_video_id, 'duration' => $asset?->duration_seconds]);
    }

    public function requirementHash(Lesson $lesson): string
    {
        $parts = $lesson->relationLoaded('blocks') ? $lesson->blocks->where('status', 'ready') : $lesson->blocks()->where('status', 'ready')->with('videoAsset')->get();
        $blocks = $parts->map(fn (LessonBlock $block): array => ['id' => $block->id, 'kind' => $block->kind, 'payload' => $block->payload, 'asset' => $block->asset_id, 'resource' => $block->resource_id,
            'media' => $block->kind === 'video' ? $this->mediaHash($block) : null])->all();

        return $this->hash(['rules' => $this->lessonRules($lesson), 'blocks' => $blocks]);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['assessment' => $message]);
    }
}

<?php

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\FormQuestion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FormValidationService
{
    private const TYPES = ['short_text', 'long_text', 'email', 'phone', 'number', 'date', 'single_choice', 'multiple_choice', 'dropdown', 'yes_no', 'rating_scale', 'info_block'];

    private const OPERATORS = ['equals', 'not_equals', 'contains', 'not_contains', 'in', 'not_in', 'greater_than', 'less_than', 'is_answered', 'is_not_answered'];

    /** @param array<int, array<string, mixed>> $questions */
    public function validateStructure(array $questions): void
    {
        $keys = [];
        $graph = [];
        foreach ($questions as $index => $question) {
            if (! is_array($question)) {
                throw ValidationException::withMessages(["questions.{$index}" => 'Each question must be an object.']);
            }
            $key = (string) ($question['question_key'] ?? '');
            if (! preg_match('/^[a-z][a-z0-9_]{0,79}$/', $key) || isset($keys[$key])) {
                throw ValidationException::withMessages(["questions.{$index}.question_key" => 'Question keys must be unique lowercase identifiers.']);
            }
            if (! is_string($question['label'] ?? null) || trim($question['label']) === '' || mb_strlen($question['label']) > 255) {
                throw ValidationException::withMessages(["questions.{$index}.label" => 'Every question needs a label of 255 characters or fewer.']);
            }
            if (isset($question['description']) && (! is_string($question['description']) || mb_strlen($question['description']) > 10000)) {
                throw ValidationException::withMessages(["questions.{$index}.description" => 'Question descriptions must be text no longer than 10,000 characters.']);
            }
            foreach (['is_required', 'assistant_visible'] as $booleanKey) {
                if (isset($question[$booleanKey]) && ! is_bool($question[$booleanKey])) {
                    throw ValidationException::withMessages(["questions.{$index}.{$booleanKey}" => 'Question flags must be true or false.']);
                }
            }
            $keys[$key] = true;
            if (! in_array($question['question_type'] ?? null, self::TYPES, true)) {
                throw ValidationException::withMessages(["questions.{$index}.question_type" => 'Choose a supported question type.']);
            }
            if (isset($question['validation_rules']) && ! is_array($question['validation_rules'])) {
                throw ValidationException::withMessages(["questions.{$index}.validation_rules" => 'Validation settings must be an object.']);
            }
            if (isset($question['presentation_config']) && ! is_array($question['presentation_config'])) {
                throw ValidationException::withMessages(["questions.{$index}.presentation_config" => 'Presentation settings must be an object.']);
            }
            $questionRules = $question['validation_rules'] ?? [];
            foreach (['min_length', 'max_length', 'min', 'max'] as $numericKey) {
                if (isset($questionRules[$numericKey]) && (! is_numeric($questionRules[$numericKey]) || (float) $questionRules[$numericKey] < 0 || (float) $questionRules[$numericKey] > 1000000)) {
                    throw ValidationException::withMessages(["questions.{$index}.validation_rules.{$numericKey}" => 'Numeric limits must be between 0 and 1,000,000.']);
                }
            }
            if (isset($questionRules['regex']) && (! is_string($questionRules['regex']) || strlen($questionRules['regex']) > 200 || @preg_match('~(*LIMIT_MATCH=10000)(*LIMIT_DEPTH=1000)(?:'.str_replace('~', '\\~', $questionRules['regex']).')~u', '') === false)) {
                throw ValidationException::withMessages(["questions.{$index}.validation_rules.regex" => 'The regular expression is invalid or too long.']);
            }
            if (isset($questionRules['min_length'], $questionRules['max_length']) && $questionRules['min_length'] > $questionRules['max_length']) {
                throw ValidationException::withMessages(["questions.{$index}.validation_rules" => 'Minimum text length cannot exceed maximum text length.']);
            }
            if (isset($questionRules['min'], $questionRules['max']) && $questionRules['min'] > $questionRules['max']) {
                throw ValidationException::withMessages(["questions.{$index}.validation_rules" => 'Minimum value cannot exceed maximum value.']);
            }
            if (($question['question_type'] ?? null) === 'rating_scale') {
                $presentation = $question['presentation_config'] ?? [];
                $min = (int) ($presentation['min'] ?? 1);
                $max = (int) ($presentation['max'] ?? 5);
                if ($min < 1 || $max > 10 || $min >= $max) {
                    throw ValidationException::withMessages(["questions.{$index}.presentation_config" => 'Rating scales must be within 1–10 and have a larger maximum than minimum.']);
                }
            }
            if (in_array($question['question_type'], ['single_choice', 'multiple_choice', 'dropdown'], true)) {
                $options = $question['options'] ?? [];
                if (! is_array($options) || ! array_is_list($options) || count($options) < 1 || count($options) > 100) {
                    throw ValidationException::withMessages(["questions.{$index}.options" => 'Choice questions require between 1 and 100 options.']);
                }
                $values = [];
                foreach ($options as $optionIndex => $option) {
                    if (! is_array($option) || ! is_string($option['label'] ?? null) || trim($option['label']) === '' || mb_strlen($option['label']) > 255 || ! is_string($option['value'] ?? null)) {
                        throw ValidationException::withMessages(["questions.{$index}.options.{$optionIndex}" => 'Each option needs a label and string value.']);
                    }
                    $value = $option['value'];
                    if ($value === '' || mb_strlen($value) > 255 || isset($values[$value])) {
                        throw ValidationException::withMessages(["questions.{$index}.options" => 'Choice values must be present and unique.']);
                    }
                    $values[$value] = true;
                }
            }

            $logic = $question['conditional_logic'] ?? null;
            if ($logic !== null) {
                if (! is_array($logic) || ($logic['logic_version'] ?? null) !== 1 || ! in_array($logic['mode'] ?? null, ['all', 'any'], true) || ! is_array($logic['conditions'] ?? null) || count($logic['conditions']) > 20) {
                    throw ValidationException::withMessages(["questions.{$index}.conditional_logic" => 'Conditional logic must use supported version 1 rules.']);
                }
                foreach ($logic['conditions'] as $conditionIndex => $condition) {
                    if (! is_array($condition)) {
                        throw ValidationException::withMessages(["questions.{$index}.conditional_logic.conditions.{$conditionIndex}" => 'Each condition must be an object.']);
                    }
                    $dependency = (string) ($condition['question_key'] ?? '');
                    if ($dependency === $key || ! preg_match('/^[a-z][a-z0-9_]{0,79}$/', $dependency) || ! in_array($condition['operator'] ?? null, self::OPERATORS, true)) {
                        throw ValidationException::withMessages(["questions.{$index}.conditional_logic" => 'Conditions must reference another question and a supported operator.']);
                    }
                    $graph[$key][] = $dependency;
                }
            }
        }

        foreach ($graph as $key => $dependencies) {
            foreach ($dependencies as $dependency) {
                if (! isset($keys[$dependency])) {
                    throw ValidationException::withMessages(['questions' => "Conditional rule for {$key} references a missing question."]);
                }
            }
        }

        $visiting = [];
        $memo = [];
        $depth = function (string $key) use (&$depth, &$visiting, &$memo, $graph): int {
            if (isset($visiting[$key])) {
                throw ValidationException::withMessages(['questions' => 'Conditional question dependencies cannot contain cycles.']);
            }
            if (isset($memo[$key])) {
                return $memo[$key];
            }
            $visiting[$key] = true;
            $currentDepth = 1;
            foreach ($graph[$key] ?? [] as $dependency) {
                $currentDepth = max($currentDepth, 1 + $depth($dependency));
            }
            unset($visiting[$key]);
            if ($currentDepth > 3) {
                throw ValidationException::withMessages(['questions' => 'Conditional logic depth cannot exceed 3 questions.']);
            }

            return $memo[$key] = $currentDepth;
        };
        foreach (array_keys($keys) as $key) {
            $depth($key);
        }
    }

    /** @param array<int, FormQuestion> $questions @param array<string, mixed> $input @return array<string, mixed> */
    public function validateAnswers(array $questions, array $input, bool $enforceRequired = true): array
    {
        $byKey = [];
        foreach ($questions as $question) {
            $byKey[$question->question_key] = $question;
        }
        foreach (array_keys($input) as $key) {
            if (! isset($byKey[$key])) {
                throw ValidationException::withMessages(["answers.{$key}" => 'This answer does not belong to the form.']);
            }
        }

        $visibleAnswers = [];
        foreach ($questions as $question) {
            if ($question->question_type === 'info_block' || ! $this->isVisible($question, $input, $byKey)) {
                continue;
            }

            $key = $question->question_key;
            $value = $input[$key] ?? null;
            if ($question->question_type === 'yes_no' && $value !== null && $value !== '') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            }
            if ($value === '' || $value === []) {
                $value = null;
            }
            if ($enforceRequired && $question->is_required && ($value === null || $value === false && $question->question_type !== 'yes_no')) {
                throw ValidationException::withMessages(["answers.{$key}" => 'This answer is required.']);
            }
            if ($value === null) {
                continue;
            }

            $this->validateValue($question, $value);
            $visibleAnswers[$key] = $value;
        }

        return $visibleAnswers;
    }

    private function validateValue(FormQuestion $question, mixed $value): void
    {
        $key = $question->question_key;
        $type = $question->question_type;
        $rules = $question->validation_rules ?? [];
        $options = $question->options->pluck('value')->all();
        $baseRules = match ($type) {
            'short_text' => ['string', 'max:'.(int) ($rules['max_length'] ?? 255)],
            'long_text' => ['string', 'max:'.(int) ($rules['max_length'] ?? 10000)],
            'email' => ['string', 'email', 'max:254'],
            'phone' => ['string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'number' => ['numeric'],
            'date' => ['date_format:Y-m-d'],
            'single_choice', 'dropdown' => ['string'],
            'multiple_choice' => ['array', 'max:'.count($options)],
            'yes_no' => ['boolean'],
            'rating_scale' => ['integer', 'min:'.(int) ($question->presentation_config['min'] ?? 1), 'max:'.(int) ($question->presentation_config['max'] ?? 5)],
            default => [],
        };

        if ($type === 'multiple_choice') {
            $baseRules[] = 'distinct';
            foreach ((array) $value as $index => $choice) {
                if (! is_string($choice) || ! in_array($choice, $options, true)) {
                    throw ValidationException::withMessages(["answers.{$key}.{$index}" => 'Choose only available options.']);
                }
            }
        }
        if (in_array($type, ['single_choice', 'dropdown'], true) && ! in_array($value, $options, true)) {
            throw ValidationException::withMessages(["answers.{$key}" => 'Choose an available option.']);
        }
        if ($type === 'date') {
            if (! empty($rules['min_date'])) {
                $baseRules[] = 'after_or_equal:'.$rules['min_date'];
            }
            if (! empty($rules['max_date'])) {
                $baseRules[] = 'before_or_equal:'.$rules['max_date'];
            }
        }
        if ($type === 'number') {
            if (isset($rules['min'])) {
                $baseRules[] = 'min:'.$rules['min'];
            }
            if (isset($rules['max'])) {
                $baseRules[] = 'max:'.$rules['max'];
            }
        }
        if (isset($rules['min_length']) && in_array($type, ['short_text', 'long_text'], true)) {
            $baseRules[] = 'min:'.(int) $rules['min_length'];
        }

        $validator = Validator::make(['answer' => $value], ['answer' => $baseRules]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(["answers.{$key}" => 'The answer is invalid.']);
        }
        if (isset($rules['regex']) && is_string($rules['regex']) && is_string($value)) {
            $pattern = $rules['regex'];
            if (strlen($pattern) > 200 || @preg_match('~(*LIMIT_MATCH=10000)(*LIMIT_DEPTH=1000)(?:'.str_replace('~', '\\~', $pattern).')~u', $value) !== 1) {
                throw ValidationException::withMessages(["answers.{$key}" => 'The answer does not match the required format.']);
            }
        }
    }

    /** @param array<string, mixed> $answers */
    /** @param array<string, mixed> $answers @param array<string, FormQuestion> $questions */
    private function isVisible(FormQuestion $question, array $answers, array $questions): bool
    {
        $visible = [];
        $visiting = [];
        $resolve = function (string $key) use (&$resolve, &$visible, &$visiting, $answers, $questions): bool {
            if (array_key_exists($key, $visible)) {
                return $visible[$key];
            }
            if (isset($visiting[$key]) || ! isset($questions[$key])) {
                return false;
            }
            $visiting[$key] = true;
            $candidate = $questions[$key];
            $logic = $candidate->conditional_logic;
            if (! is_array($logic) || empty($logic['conditions'])) {
                unset($visiting[$key]);

                return $visible[$key] = true;
            }

            $results = [];
            foreach ($logic['conditions'] as $condition) {
                $dependencyKey = (string) ($condition['question_key'] ?? '');
                $dependencyVisible = $resolve($dependencyKey);
                $dependency = $questions[$dependencyKey] ?? null;
                $value = $dependencyVisible ? ($answers[$dependencyKey] ?? null) : null;
                if ($dependency?->question_type === 'yes_no' && $value !== null && $value !== '') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                } elseif (in_array($dependency?->question_type, ['number', 'rating_scale'], true) && is_numeric($value)) {
                    $value = (float) $value;
                }
                $target = $condition['value'] ?? null;
                $isAnswered = $value !== null && $value !== '' && $value !== [];
                $results[] = match ($condition['operator'] ?? '') {
                    'equals' => is_numeric($value) && is_numeric($target) ? (float) $value === (float) $target : $value === $target,
                    'not_equals' => is_numeric($value) && is_numeric($target) ? (float) $value !== (float) $target : $value !== $target,
                    'contains' => is_array($value) ? in_array($target, $value, true) : (is_string($value) && is_string($target) && str_contains(mb_strtolower($value), mb_strtolower($target))),
                    'not_contains' => is_array($value) ? ! in_array($target, $value, true) : (! is_string($value) || ! is_string($target) || ! str_contains(mb_strtolower($value), mb_strtolower($target))),
                    'in' => in_array($value, (array) $target, true),
                    'not_in' => ! in_array($value, (array) $target, true),
                    'greater_than' => is_numeric($value) && is_numeric($target) && (float) $value > (float) $target,
                    'less_than' => is_numeric($value) && is_numeric($target) && (float) $value < (float) $target,
                    'is_answered' => $isAnswered,
                    'is_not_answered' => ! $isAnswered,
                    default => false,
                };
            }
            unset($visiting[$key]);

            return $visible[$key] = (($logic['mode'] ?? 'all') === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true));
        };

        return $resolve($question->question_key);
    }
}

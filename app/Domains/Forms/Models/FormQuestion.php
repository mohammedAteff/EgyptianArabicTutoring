<?php

namespace App\Domains\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormQuestion extends Model
{
    protected $fillable = [
        'form_version_id', 'question_key', 'label', 'description', 'question_type', 'is_required',
        'assistant_visible', 'sort_order', 'validation_rules', 'presentation_config', 'conditional_logic',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean', 'assistant_visible' => 'boolean', 'sort_order' => 'integer',
            'validation_rules' => 'array', 'presentation_config' => 'array', 'conditional_logic' => 'array',
        ];
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    /** @return HasMany<FormQuestionOption, $this> */
    public function options(): HasMany
    {
        return $this->hasMany(FormQuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function displayAnswer(?string $value): string
    {
        if ($value === null || ! in_array($this->question_type, ['single_choice', 'dropdown', 'multiple_choice'], true)) {
            return $value ?? '';
        }
        $labels = $this->options->pluck('label', 'value');
        if ($this->question_type === 'multiple_choice') {
            $choices = json_decode($value, true);
            if (is_array($choices) && array_is_list($choices) && collect($choices)->every(fn ($choice): bool => is_string($choice))) {
                return implode(', ', array_map(fn (string $choice): string => $labels->get($choice, $choice), $choices));
            }

            return $value;
        }

        return $labels->get($value, $value);
    }
}

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

    public function version(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'form_version_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(FormQuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }
}

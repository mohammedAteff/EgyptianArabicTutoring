<?php

namespace App\Domains\Forms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FormVersion extends Model
{
    protected $fillable = ['form_id', 'version_number', 'changelog'];

    protected function casts(): array
    {
        return ['version_number' => 'integer'];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(FormQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    public function isFrozen(): bool
    {
        return $this->submissions()->exists();
    }
}

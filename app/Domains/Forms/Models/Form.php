<?php

namespace App\Domains\Forms\Models;

use App\Domains\Administration\Models\Administrator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Form extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'description', 'status', 'is_mandatory',
        'can_edit_after_submission', 'lock_version', 'created_by', 'active_version_id', 'published_version_id',
    ];

    protected function casts(): array
    {
        return ['is_mandatory' => 'boolean', 'can_edit_after_submission' => 'boolean', 'lock_version' => 'integer'];
    }

    /** @return BelongsTo<Administrator, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'created_by');
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'active_version_id');
    }

    /** @return BelongsTo<FormVersion, $this> */
    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(FormVersion::class, 'published_version_id');
    }

    /** @return HasMany<FormVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(FormVersion::class);
    }

    /** @return HasMany<FormTrigger, $this> */
    public function triggers(): HasMany
    {
        return $this->hasMany(FormTrigger::class);
    }
}

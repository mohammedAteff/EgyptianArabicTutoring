<?php

namespace App\Domains\Resources\Models;

use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use HasFactory, HasTranslations, SoftDeletes;

    public function getTranslationModelClass(): string
    {
        return ResourceTranslation::class;
    }

    public function getEntityType(): string
    {
        return 'resource';
    }

    protected $table = 'resources';

    protected $fillable = [
        'category_id',
        'title',
        'slug',
        'short_description',
        'full_description',
        'file_path',
        'file_type',
        'file_size',
        'cover_image_path',
        'status', // 'draft', 'published', 'archived'
        'is_gated',
        'featured',
        'sort_order',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_gated' => 'boolean',
            'featured' => 'boolean',
            'sort_order' => 'integer',
            'file_size' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'category_id');
    }

    public function requests(): HasMany
    {
        return $this->hasMany(ResourceRequest::class, 'resource_id');
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class, 'resource_id');
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->isPast();
    }
}

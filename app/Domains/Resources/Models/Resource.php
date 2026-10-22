<?php

namespace App\Domains\Resources\Models;

use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    /** @use HasTranslations<ResourceTranslation> */
    use HasFactory, HasTranslations, SoftDeletes;

    /** @return class-string<ResourceTranslation> */
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
        'external_url',
        'file_type',
        'file_size',
        'cover_image_path',
        'status', // 'draft', 'published', 'archived'
        'is_gated',
        'featured',
        'public_catalogue',
        'student_discoverable',
        'sort_order',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_gated' => 'boolean',
            'featured' => 'boolean',
            'public_catalogue' => 'boolean',
            'student_discoverable' => 'boolean',
            'sort_order' => 'integer',
            'file_size' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ResourceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ResourceCategory::class, 'category_id');
    }

    /** @return HasMany<ResourceRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ResourceRequest::class, 'resource_id');
    }

    /** @return HasMany<ResourceDownload, $this> */
    public function downloads(): HasMany
    {
        return $this->hasMany(ResourceDownload::class, 'resource_id');
    }

    /** @return MorphMany<ContentRevision, $this> */
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

    public function requiresPublicAccessRequest(): bool
    {
        return $this->is_gated || $this->public_catalogue;
    }

    /**
     * @param  Builder<\App\Domains\Resources\Models\Resource>  $query
     * @return Builder<\App\Domains\Resources\Models\Resource>
     */
    public function scopePublicCatalogue(Builder $query): Builder
    {
        return $query->published()->where('public_catalogue', true);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at !== null && $this->published_at->isPast();
    }

    /**
     * @return BelongsToMany<ResourceCollection, $this>
     */
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ResourceCollection::class, 'resource_collection_items', 'resource_id', 'collection_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }
}

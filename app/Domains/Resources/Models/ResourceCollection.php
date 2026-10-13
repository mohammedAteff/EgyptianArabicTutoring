<?php

namespace App\Domains\Resources\Models;

use Database\Factories\Domains\Resources\ResourceCollectionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ResourceCollection extends Model
{
    /** @use HasFactory<ResourceCollectionFactory> */
    use HasFactory;

    protected static function newFactory(): ResourceCollectionFactory
    {
        return ResourceCollectionFactory::new();
    }

    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_published',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<resource, $this>
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'resource_collection_items', 'collection_id', 'resource_id')
            ->withPivot('sort_order')
            ->orderByPivot('sort_order')
            ->withTimestamps();
    }

    /**
     * @param  Builder<ResourceCollection>  $query
     * @return Builder<ResourceCollection>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('title');
    }
}

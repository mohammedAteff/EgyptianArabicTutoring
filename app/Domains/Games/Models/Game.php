<?php

namespace App\Domains\Games\Models;

use App\Domains\CMS\Models\ContentRevision;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Game extends Model
{
    use HasFactory;

    protected $table = 'games';

    protected $fillable = [
        'title',
        'slug',
        'description',
        'badge',
        'thumbnail_path',
        'target_url',
        'status', // 'coming_soon', 'available', 'archived'
        'featured',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', 'available');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('featured', true);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisable');
    }
}

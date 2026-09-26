<?php

namespace App\Domains\CMS\Models;

use App\Domains\Administration\Models\Administrator;
use Database\Factories\BlogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blog extends Model
{
    use HasFactory;

    protected $table = 'blogs';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_image_path', 'seo_title',
        'seo_description', 'canonical_url', 'status', 'published_at',
        'author_id', 'locale', 'translation_group_id', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function newFactory(): BlogFactory
    {
        return BlogFactory::new();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now('UTC'));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Administrator::class, 'author_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(BlogRevision::class, 'blog_id');
    }

    public function redirects(): HasMany
    {
        return $this->hasMany(BlogSlugRedirect::class, 'blog_id');
    }
}

<?php

namespace App\Domains\CMS\Models;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use Database\Factories\BlogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Blog extends Model
{
    use HasFactory;

    protected $table = 'blogs';

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_image_path', 'seo_title',
        'seo_description', 'canonical_url', 'status', 'published_at',
        'author_id', 'locale', 'translation_group_id', 'lock_version',
        'category_id', 'featured_image_alt', 'editorial_updated_at',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'editorial_updated_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function newFactory(): BlogFactory
    {
        return BlogFactory::new();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now('UTC'));
    }

    /** @return BelongsTo<BlogCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(BlogCategory::class);
    }

    /** @return BelongsToMany<Blog, $this> */
    public function relatedArticles(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'blog_related_articles', 'blog_id', 'related_blog_id');
    }

    /** @return BelongsToMany<resource, $this> */
    public function relatedResources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'blog_related_resources', 'blog_id', 'resource_id');
    }

    /** @return BelongsToMany<Game, $this> */
    public function relatedGames(): BelongsToMany
    {
        return $this->belongsToMany(Game::class, 'blog_related_games', 'blog_id', 'game_id');
    }

    public function readingMinutes(): int
    {
        $text = html_entity_decode(strip_tags($this->body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_match_all('/[\\p{L}\\p{N}]+/u', $text);

        return max(1, (int) ceil(($words ?: 0) / 200));
    }

    public function publicUrl(): string
    {
        return app(LocalizedUrlService::class)->getLocalizedUrl('blog.detail', $this->locale, $this->slug);
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

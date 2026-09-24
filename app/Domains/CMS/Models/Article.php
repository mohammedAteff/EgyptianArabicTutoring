<?php

namespace App\Domains\CMS\Models;

use Database\Factories\ArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'excerpt', 'body', 'featured_image_path', 'seo_title',
        'seo_description', 'canonical_url', 'status', 'published_at',
        'author_id', 'locale', 'translation_group_id', 'lock_version',
    ];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'lock_version' => 'integer'];
    }

    protected static function newFactory(): ArticleFactory
    {
        return ArticleFactory::new();
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->where('published_at', '<=', now('UTC'));
    }
}

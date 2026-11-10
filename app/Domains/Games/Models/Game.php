<?php

namespace App\Domains\Games\Models;

use App\Domains\CMS\Models\ContentRevision;
use App\Domains\CMS\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Game extends Model
{
    /** @use HasTranslations<GameTranslation> */
    use HasFactory, HasTranslations;

    /** @return class-string<GameTranslation> */
    public function getTranslationModelClass(): string
    {
        return GameTranslation::class;
    }

    public function getEntityType(): string
    {
        return 'game';
    }

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

    public function localView(): ?string
    {
        return config('games.local.'.$this->slug.'.view');
    }

    public function isExternal(): bool
    {
        return $this->localView() === null && filled($this->target_url);
    }

    public function launchUrl(): string
    {
        return $this->isExternal() ? $this->target_url : localized_url('game.detail', $this->slug);
    }

    public function coverPath(): string
    {
        if ($this->localView() && in_array($this->thumbnail_path, [null, '', 'images/games/6-word-story.webp', 'images/games/6-word-story.png'], true)) {
            return (string) config('games.local.'.$this->slug.'.cover', $this->thumbnail_path);
        }

        return (string) $this->thumbnail_path;
    }

    /** @return array{title: string, description: string, badge: string, locale: string, title_locale: string, description_locale: string, badge_locale: string} */
    public function localizedContent(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $published = $this->liveTranslation($locale);
        $english = $this->liveTranslation('en');
        $content = [];
        $complete = true;
        foreach (['title', 'description', 'badge'] as $field) {
            $source = (string) ($english?->getAttribute($field) ?: $this->getAttribute($field));
            $translated = $published?->getAttribute($field);
            if (filled($translated)) {
                $content[$field] = $translated;
                $content[$field.'_locale'] = $locale;
            } else {
                $content[$field] = __($source, locale: $locale);
                $available = $source === '' || $locale === 'en' || app('translator')->hasForLocale($source, $locale);
                $content[$field.'_locale'] = $available ? $locale : 'en';
                $complete = $complete && $available;
            }
        }

        return $content + ['locale' => $complete ? $locale : 'en'];
    }

    /** @return MorphMany<ContentRevision, $this> */
    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisable');
    }
}

<?php

namespace App\Domains\CMS\Models;

use App\Domains\CMS\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Faq extends Model
{
    /** @use HasTranslations<FaqTranslation> */
    use HasFactory, HasTranslations;

    /** @return class-string<FaqTranslation> */
    public function getTranslationModelClass(): string
    {
        return FaqTranslation::class;
    }

    public function getEntityType(): string
    {
        return 'faq';
    }

    protected $table = 'faqs';

    protected $fillable = [
        'question',
        'answer',
        'sort_order',
        'active',
        'topic',
        'featured',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'active' => 'boolean',
            'featured' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true)->orderBy('sort_order');
    }

    public function readableTranslation(?string $locale = null): ?FaqTranslation
    {
        foreach (array_unique([$locale ?? app()->getLocale(), 'en']) as $language) {
            $translation = $this->liveTranslation($language);
            if ($translation instanceof FaqTranslation && filled($translation->question) && filled($translation->answer)) {
                return $translation;
            }
        }

        return null;
    }

    /** @return array{question: string, answer: string, locale: string} */
    public function localizedContent(?string $locale = null): array
    {
        $locale ??= app()->getLocale();
        $published = $this->readableTranslation($locale);
        if ($published?->locale === $locale) {
            return ['question' => $published->question, 'answer' => $published->answer, 'locale' => $locale];
        }
        $question = $published->question ?? $this->question;
        $answer = $published->answer ?? $this->answer;
        if ($locale !== 'en' && app('translator')->hasForLocale($question, $locale) && app('translator')->hasForLocale($answer, $locale)) {
            return ['question' => __($question, locale: $locale), 'answer' => __($answer, locale: $locale), 'locale' => $locale];
        }

        return ['question' => $question, 'answer' => $answer, 'locale' => 'en'];
    }

    /** @return MorphMany<ContentRevision, $this> */
    public function revisions(): MorphMany
    {
        return $this->morphMany(ContentRevision::class, 'revisable')->orderByDesc('revision_number');
    }
}

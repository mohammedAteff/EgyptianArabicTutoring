<?php

namespace App\Domains\CMS\Traits;

use App\Domains\CMS\Models\EntityTranslationRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasTranslations
{
    abstract public function getTranslationModelClass(): string;

    abstract public function getEntityType(): string;

    public function translations(): HasMany
    {
        return $this->hasMany($this->getTranslationModelClass(), $this->getForeignKey());
    }

    public function liveTranslation(?string $locale = null): ?Model
    {
        $targetLocale = $locale ?? app()->getLocale();

        if ($this->relationLoaded('translations')) {
            return $this->translations
                ->where('locale', $targetLocale)
                ->whereIn('status', ['published', 'stale'])
                ->first();
        }

        return $this->translations()
            ->where('locale', $targetLocale)
            ->whereIn('status', ['published', 'stale'])
            ->first();
    }

    public function draftTranslation(?string $locale = null): ?Model
    {
        $targetLocale = $locale ?? app()->getLocale();

        if ($this->relationLoaded('translations')) {
            return $this->translations
                ->where('locale', $targetLocale)
                ->where('status', 'draft')
                ->first();
        }

        return $this->translations()
            ->where('locale', $targetLocale)
            ->where('status', 'draft')
            ->first();
    }

    public function sourceRevisions(): HasMany
    {
        return $this->hasMany(EntityTranslationRevision::class, 'entity_id')
            ->where('entity_type', $this->getEntityType())
            ->orderByDesc('revision_number');
    }

    public function currentSourceRevision(): ?EntityTranslationRevision
    {
        if ($this->relationLoaded('sourceRevisions')) {
            return $this->sourceRevisions->first();
        }

        return $this->sourceRevisions()->first();
    }

    /**
     * Resolve localized translation or English fallback according to Section 27 contract.
     *
     * @return array{translation: ?Model, is_fallback: bool, is_stale: bool, effective_locale: string}
     */
    public function resolveTranslation(?string $locale = null): array
    {
        $reqLocale = $locale ?? app()->getLocale();

        if ($reqLocale === 'en') {
            $enTrans = $this->liveTranslation('en');

            return [
                'translation' => $enTrans,
                'is_fallback' => false,
                'is_stale' => false,
                'effective_locale' => 'en',
            ];
        }

        $locTrans = $this->liveTranslation($reqLocale);

        if ($locTrans) {
            return [
                'translation' => $locTrans,
                'is_fallback' => false,
                'is_stale' => $locTrans->status === 'stale',
                'effective_locale' => $reqLocale,
            ];
        }

        // Fallback to published English translation
        $enFallback = $this->liveTranslation('en');

        return [
            'translation' => $enFallback,
            'is_fallback' => true,
            'is_stale' => false,
            'effective_locale' => 'en',
        ];
    }

    /**
     * Stale Translation Policy (EDITS V1 §27.8):
     * A translation with status 'stale' remains publicly visible and serves its translated content.
     * Stale translations are considered eligible published translations and participate in
     * hreflang clusters because they represent previously vetted human translations that remain
     * valid until administrators explicitly re-publish an updated version.
     *
     * @return array<string>
     */
    public function getAvailableLocales(): array
    {
        $locales = ['en'];

        foreach (['fr', 'de'] as $locale) {
            // liveTranslation returns models with status 'published' OR 'stale'
            if ($this->liveTranslation($locale) !== null) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }
}

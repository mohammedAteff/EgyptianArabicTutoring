<?php

namespace App\Domains\Resources\Services;

use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ResourceLibraryQuery
{
    public function hasCompleteTranslation(string $locale): bool
    {
        $hasText = fn (Builder $query, string $field): Builder => $query->where('locale', $locale)->whereIn('status', ['published', 'stale'])->whereNotNull($field)->whereRaw("TRIM({$field}) <> ''");
        $missingResource = Resource::query()->publicCatalogue()->where(function (Builder $query) use ($hasText): void {
            $query->whereDoesntHave('translations', fn (Builder $translation): Builder => $hasText($translation, 'title'))
                ->orWhere(function (Builder $query) use ($hasText): void {
                    $query->whereNotNull('short_description')->whereRaw("TRIM(short_description) <> ''")
                        ->whereDoesntHave('translations', fn (Builder $translation): Builder => $hasText($translation, 'short_description'));
                });
        })->exists();
        $missingCategory = ResourceCategory::query()->where('active', true)
            ->whereHas('resources', fn (Builder $query): Builder => $query->publicCatalogue())
            ->whereDoesntHave('translations', fn (Builder $translation): Builder => $hasText($translation, 'name'))->exists();

        return ! $missingResource && ! $missingCategory;
    }

    /** @return Builder<\App\Domains\Resources\Models\Resource> */
    public function resources(): Builder
    {
        $catalogue = Resource::query()->publicCatalogue()->select('resources.*')
            ->leftJoin('resource_categories', 'resource_categories.id', '=', 'resources.category_id');
        foreach (['title', 'short_description'] as $field) {
            $this->addText($catalogue, 'resource_translations', 'resource_id', 'resources', $field, 'library_'.$field);
        }
        $this->addText($catalogue, 'category_translations', 'category_id', 'resource_categories', 'name', 'library_category');
        $locale = $this->translation('resource_translations', 'resource_id', 'resources.id', 'title')->select('locale');
        $catalogue->selectRaw("COALESCE(({$locale->toSql()}), 'en') AS library_locale", $locale->getBindings());

        return Resource::query()->fromSub($catalogue, 'resources')->select('resources.*');
    }

    /** @return Builder<ResourceCategory> */
    public function categories(): Builder
    {
        $categories = ResourceCategory::query()->where('active', true)
            ->whereHas('resources', fn (Builder $query) => $query->publicCatalogue())
            ->select('resource_categories.*')->orderBy('sort_order')->orderBy('id');
        $this->addText($categories, 'category_translations', 'category_id', 'resource_categories', 'name', 'library_name');

        return $categories;
    }

    private function addText(Builder $query, string $translations, string $foreignKey, string $parent, string $field, string $alias): void
    {
        $translation = $this->translation($translations, $foreignKey, $parent.'.id', $field);
        $query->selectRaw("COALESCE(({$translation->toSql()}), {$parent}.{$field}, '') AS {$alias}", $translation->getBindings());
    }

    private function translation(string $table, string $foreignKey, string $parentKey, string $field): QueryBuilder
    {
        $locale = app()->getLocale();

        return DB::table($table)->select($field)->whereColumn($foreignKey, $parentKey)
            ->whereIn('locale', array_unique([$locale, 'en']))->whereIn('status', ['published', 'stale'])
            ->whereNotNull($field)->whereRaw("TRIM({$field}) <> ''")
            ->orderByRaw('CASE WHEN locale = ? THEN 0 ELSE 1 END', [$locale])->limit(1);
    }
}

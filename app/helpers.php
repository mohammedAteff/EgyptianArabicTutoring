<?php

use App\Domains\CMS\Services\LocalizedUrlService;

if (! function_exists('localized_url')) {
    /**
     * Get the localized URL for a canonical route key.
     */
    function localized_url(string $key, ?string $slug = null, ?string $locale = null): string
    {
        return app(LocalizedUrlService::class)->getLocalizedUrl($key, $locale ?? app()->getLocale(), $slug);
    }
}

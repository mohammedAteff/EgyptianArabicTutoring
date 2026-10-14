<?php

use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\CMS\Services\WebsiteCopyService;

function website_text(string $page, string $text): string
{
    return (string) app(WebsiteCopyService::class)->value($page, 'ui.'.sha1($text), $text);
}

function website_label(string $text): string
{
    $copies = app(WebsiteCopyService::class);
    $locale = app()->getLocale();
    if ($locale !== 'en' && __($text) !== $text && $copies->document('shared', $locale)?->published_content === null
        && ! request()->attributes->get('website_copy_preview')) {
        return __($text);
    }

    return (string) $copies->value('shared', 'labels.'.sha1($text), __($text));
}

function website_link(string $page, string $key, string $fallback): string
{
    $value = (string) app(WebsiteCopyService::class)->value($page, 'links.'.$key);

    return $value === '' ? $fallback : (str_starts_with($value, '/') ? url($value) : $value);
}

if (! function_exists('localized_url')) {
    /**
     * Get the localized URL for a canonical route key.
     */
    function localized_url(string $key, ?string $slug = null, ?string $locale = null): string
    {
        return app(LocalizedUrlService::class)->getLocalizedUrl($key, $locale ?? app()->getLocale(), $slug);
    }
}

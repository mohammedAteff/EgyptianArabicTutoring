<?php

use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\CMS\Services\WebsiteCopyService;
use Carbon\CarbonImmutable;

function localized_number(int|float|null $number, int $decimals = 0): string
{
    $number ??= 0;

    return match (app()->getLocale()) {
        'fr' => number_format($number, $decimals, ',', "\u{202F}"),
        'de' => number_format($number, $decimals, ',', '.'),
        default => number_format($number, $decimals),
    };
}

function localized_date_format(string $format): string
{
    $locale = app()->getLocale();
    if ($locale !== 'en') {
        $format = str_replace(['g:i:s A', 'g:i:s a', 'h:i:s A'], 'H:i:s', $format);
        $format = str_replace(['g:i A', 'g:i a', 'h:i A'], 'H:i', $format);
        $format = str_replace(
            ['F j, Y', 'F d, Y', 'M j, Y', 'M d, Y', 'M j'],
            $locale === 'de' ? ['j. F Y', 'j. F Y', 'j. M Y', 'j. M Y', 'j. M'] : ['j F Y', 'j F Y', 'j M Y', 'j M Y', 'j M'],
            $format,
        );
        if ($locale === 'de') {
            $format = str_replace(['j M Y', 'j F Y', 'j M,'], ['j. M Y', 'j. F Y', 'j. M,'], $format);
        }
    }

    return $format;
}

function localized_date(DateTimeInterface $date, string $format): string
{
    return CarbonImmutable::instance($date)->locale(app()->getLocale())->translatedFormat(localized_date_format($format));
}

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

<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Blog;
use Illuminate\Http\Request;

class LocalizedUrlService
{
    /**
     * Get the corresponding localized URL for a target locale given the current request.
     */
    public function getUrlForLocale(string $targetLocale, ?Request $request = null): string
    {
        $req = $request ?? request();
        $path = trim($req->getPathInfo(), '/');
        $query = $req->getQueryString();
        $querySuffix = $query ? '?'.$query : '';

        // Match known routes by path structure
        $routeInfo = $this->resolveRouteInfo($path, $req);
        if ($routeInfo) {
            return $this->getLocalizedUrl($routeInfo['key'], $targetLocale, $routeInfo['slug'] ?? null).$querySuffix;
        }

        // Generic fallback for homepage or unrecognized routes
        return match ($targetLocale) {
            'fr' => url('/fr').$querySuffix,
            'de' => url('/de').$querySuffix,
            default => url('/').$querySuffix,
        };
    }

    /**
     * Get the canonical URL for the current request.
     * In Section 27, when a page is in English fallback mode, it must canonicalize
     * to the application-generated English URL.
     */
    public function getCanonicalUrl(string $currentLocale, bool $isFallback = false, ?Request $request = null): string
    {
        if ($isFallback) {
            return $this->getUrlForLocale('en', $request);
        }

        return $this->getUrlForLocale($currentLocale, $request);
    }

    /**
     * Get hreflang alternates.
     * In Section 27, when a page is in fallback mode, zero hreflang tags must be emitted.
     * When genuinely localized, only locales with published/stale translations may participate.
     *
     * @param  array<string>|null  $availableLocales  Specific locales eligible for hreflang
     * @return array<string, string> Map of locale code => URL
     */
    public function getHreflangAlternates(bool $isFallback = false, ?array $availableLocales = null, ?Request $request = null): array
    {
        if ($isFallback) {
            return [];
        }

        $locales = $availableLocales ?? ['en', 'fr', 'de'];

        $enUrl = $this->getUrlForLocale('en', $request);
        $alternates = [];

        if (in_array('en', $locales, true)) {
            $alternates['en'] = $enUrl;
        }

        if (in_array('fr', $locales, true)) {
            $alternates['fr'] = $this->getUrlForLocale('fr', $request);
        }

        if (in_array('de', $locales, true)) {
            $alternates['de'] = $this->getUrlForLocale('de', $request);
        }

        if (! empty($alternates) && in_array('en', $locales, true)) {
            $alternates['x-default'] = $enUrl;
        }

        return $alternates;
    }

    /**
     * Resolve the route key and parameters from the path.
     *
     * @return array{key: string, slug?: string}|null
     */
    public function resolveRouteInfo(string $path, Request $request): ?array
    {
        $segments = array_values(array_filter(explode('/', $path)));
        $first = $segments[0] ?? '';

        // Normalize prefix
        if (in_array($first, ['fr', 'de'], true)) {
            array_shift($segments);
        }

        $section = $segments[0] ?? '';
        $slug = $segments[1] ?? null;

        if (empty($section)) {
            return ['key' => 'home'];
        }

        if (in_array($section, ['booking', 'book', 'reservation', 'buchen'], true)) {
            if (isset($segments[1]) && in_array($segments[1], ['confirmation', 'bestaetigung'], true) && isset($segments[2])) {
                return ['key' => 'booking.confirmation', 'slug' => $segments[2]];
            }

            return ['key' => 'booking'];
        }

        return match ($section) {
            'resources', 'ressources', 'ressourcen' => $slug
                ? ['key' => 'resource.detail', 'slug' => $slug]
                : ['key' => 'resources'],
            'games', 'jeux', 'spiele' => $slug
                ? ['key' => 'game.detail', 'slug' => $slug]
                : ['key' => 'games'],
            'blog' => $slug ? ['key' => 'blog.detail', 'slug' => $slug] : ['key' => 'blogs'],
            'booking-policy' => ['key' => 'policy'],
            'about', 'a-propos', 'ueber-uns' => ['key' => 'about'],
            'faq' => ['key' => 'faq'],
            'pricing', 'tarifs', 'preise' => ['key' => 'pricing'],
            'privacy', 'confidentialite', 'datenschutz' => ['key' => 'privacy'],
            'terms', 'conditions', 'agb' => ['key' => 'terms'],
            'p' => ['key' => 'page.show', 'slug' => $slug],
            default => null,
        };
    }

    private function articleUrl(string $locale, ?string $slug): string
    {
        $article = Blog::query()->where('slug', $slug)->first(['translation_group_id']);
        if ($article?->translation_group_id) {
            $slug = Blog::published()->where('translation_group_id', $article->translation_group_id)->where('locale', $locale)->value('slug') ?? $slug;
        }

        return $locale === 'en' ? route('blog.show', $slug) : route('blog.show.'.$locale, $slug);
    }

    /**
     * Get the localized URL for a canonical route key.
     */
    public function getLocalizedUrl(string $key, string $locale, ?string $slug = null): string
    {
        $key = match ($key) {
            'booking.index' => 'booking',
            'blog.index' => 'blogs',
            'blog.show' => 'blog.detail',
            'resources.index' => 'resources',
            'games.index' => 'games',
            'resource.show' => 'resource.detail',
            'game.show' => 'game.detail',
            default => $key,
        };

        return match ($key) {
            'policy' => route('policy'),
            'blogs' => $locale === 'en' ? route('blog.index') : url('/'.$locale.'/blog'),
            'blog.detail' => $this->articleUrl($locale, $slug),
            'home' => match ($locale) {
                'fr' => url('/fr'),
                'de' => url('/de'),
                default => url('/'),
            },
            'pricing' => match ($locale) {
                'fr' => url('/fr/tarifs'),
                'de' => url('/de/preise'),
                default => url('/pricing'),
            },
            'booking' => match ($locale) {
                'fr' => url('/fr/reservation'),
                'de' => url('/de/buchen'),
                default => url('/booking'),
            },
            'booking.confirmation' => match ($locale) {
                'fr' => url('/fr/reservation/confirmation/'.$slug),
                'de' => url('/de/buchen/bestaetigung/'.$slug),
                default => url('/booking/confirmation/'.$slug),
            },
            'resources' => match ($locale) {
                'fr' => url('/fr/ressources'),
                'de' => url('/de/ressourcen'),
                default => url('/resources'),
            },
            'resource.detail' => match ($locale) {
                'fr' => url('/fr/ressources/'.$slug),
                'de' => url('/de/ressourcen/'.$slug),
                default => url('/resources/'.$slug),
            },
            'games' => match ($locale) {
                'fr' => url('/fr/jeux'),
                'de' => url('/de/spiele'),
                default => url('/games'),
            },
            'game.detail' => match ($locale) {
                'fr' => url('/fr/jeux/'.$slug),
                'de' => url('/de/spiele/'.$slug),
                default => url('/games/'.$slug),
            },
            'about' => match ($locale) {
                'fr' => url('/fr/a-propos'),
                'de' => url('/de/ueber-uns'),
                default => url('/about'),
            },
            'faq' => match ($locale) {
                'fr' => url('/fr/faq'),
                'de' => url('/de/faq'),
                default => url('/faq'),
            },
            'privacy' => match ($locale) {
                'fr' => url('/fr/confidentialite'),
                'de' => url('/de/datenschutz'),
                default => url('/privacy'),
            },
            'terms' => match ($locale) {
                'fr' => url('/fr/conditions'),
                'de' => url('/de/agb'),
                default => url('/terms'),
            },
            'page.show', 'p' => match ($locale) {
                'fr' => url('/fr/p/'.$slug),
                'de' => url('/de/p/'.$slug),
                default => url('/p/'.$slug),
            },
            default => match ($locale) {
                'fr' => url('/fr'),
                'de' => url('/de'),
                default => url('/'),
            },
        };
    }
}

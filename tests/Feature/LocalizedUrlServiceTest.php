<?php

namespace Tests\Feature;

use App\Domains\CMS\Services\LocalizedUrlService;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LocalizedUrlServiceTest extends TestCase
{
    #[TestWith(['/fr?lang=en&lang=fr&category=grammar&page=2', 'de', '/de'])]
    #[TestWith(['/de/ressourcen/alphabet?lang=de&tag%5B0%5D=reading&tag%5B1%5D=writing', 'fr', '/fr/ressources/alphabet'])]
    #[TestWith(['/fr/reservation?lang=fr&timezone=Africa%2FCairo', 'en', '/booking'])]
    #[TestWith(['/de/buchen/bestaetigung/reference?lang=de&source=email', 'fr', '/fr/reservation/confirmation/reference'])]
    #[TestWith(['/unrecognized?lang=fr&source=newsletter', 'de', '/de'])]
    public function test_locale_urls_replace_the_selected_language_and_preserve_other_query_parameters(string $uri, string $locale, string $expectedPath): void
    {
        $request = Request::create($uri);

        $url = app(LocalizedUrlService::class)->getUrlForLocale($locale, $request);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($expectedPath, parse_url($url, PHP_URL_PATH));
        $this->assertSame($locale, $query['lang']);
        $this->assertSame(1, substr_count($url, 'lang='));
        unset($query['lang']);
        $this->assertSame($request->except('lang'), $query);
    }

    public function test_locale_urls_do_not_add_a_language_parameter_to_canonical_links(): void
    {
        $request = Request::create('/fr/tarifs?source=newsletter');

        $url = app(LocalizedUrlService::class)->getCanonicalUrl('fr', request: $request);

        $this->assertSame(url('/fr/tarifs?source=newsletter'), $url);
    }

    public function test_english_fallback_replaces_an_existing_language_selection(): void
    {
        $request = Request::create('/de/ressourcen/alphabet?lang=de&source=newsletter');

        $url = app(LocalizedUrlService::class)->getCanonicalUrl('de', isFallback: true, request: $request);

        $this->assertSame(url('/resources/alphabet?lang=en&source=newsletter'), $url);
    }
}

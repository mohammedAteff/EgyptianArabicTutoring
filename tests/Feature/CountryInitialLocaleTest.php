<?php

namespace Tests\Feature;

use App\Domains\Analytics\Services\GeoIpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CountryInitialLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_france_germany_and_austria_choose_existing_localized_routes(): void
    {
        foreach (['FR' => 'fr', 'DE' => 'de', 'AT' => 'de'] as $country => $locale) {
            $this->flushSession();
            $this->mock(GeoIpService::class)->shouldReceive('detectCountryFromRequest')->andReturn($country);
            $this->get('/resources')->assertRedirect('/'.$locale.'/'.($locale === 'fr' ? 'ressources' : 'ressourcen'));
        }
    }

    public function test_explicit_language_choice_wins_and_persists_across_country_changes(): void
    {
        $this->mock(GeoIpService::class)->shouldReceive('detectCountryFromRequest')->andReturn('DE');
        $this->get('/?lang=en')->assertOk()->assertCookie('public_locale', 'en')->assertSessionHas('public_locale', 'en');
        $this->get('/resources')->assertOk();
        $this->get('/?lang=fr')->assertRedirect('/fr?lang=fr');
        $this->get('/resources')->assertRedirect('/fr/ressources');
    }

    public function test_localized_deep_links_bots_and_private_routes_are_not_redirected_by_country(): void
    {
        $this->mock(GeoIpService::class)->shouldReceive('detectCountryFromRequest')->andReturn('DE');
        $this->get('/fr/ressources')->assertOk();
        $this->withHeaders(['User-Agent' => 'Googlebot'])->get('/resources')->assertOk();
        $this->get('/admin/login')->assertOk();
        $this->get('/student/login')->assertOk();
    }

    public function test_untrusted_country_header_cannot_choose_language(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->withHeaders(['CF-IPCountry' => 'FR'])->get('/resources')->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\SocialLink;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FloatingSocialTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['/', 'en', 'Follow us', 'Social channels', 'Close social links'])]
    #[TestWith(['/fr', 'fr', 'Suivez-nous', 'Réseaux sociaux', 'Fermer les liens sociaux'])]
    #[TestWith(['/de', 'de', 'Folgen Sie uns', 'Soziale Kanäle', 'Soziale Links schließen'])]
    public function test_public_social_menu_is_localized_and_preserves_configured_labels(string $path, string $locale, string $launcher, string $heading, string $close): void
    {
        $this->channel(['label' => 'My original public label', 'show_in_floating' => true]);

        $response = $this->withHeader('X-Analytics-Synthetic', '1')->get($path)->assertOk();

        $response->assertSee('lang="'.$locale.'"', false)->assertSee($launcher)->assertSee($heading)->assertSee('aria-label="'.$close.'"', false)->assertSee('My original public label');
    }

    private function channel(array $data = []): SocialLink
    {
        return SocialLink::create($data + ['platform' => 'custom', 'url_or_phone' => 'https://example.test/channel', 'label' => 'Custom channel', 'enabled' => true, 'sort_order' => 0]);
    }

    public function test_placement_defaults_off_in_model_database_and_upgraded_records(): void
    {
        $channel = $this->channel();
        $this->assertFalse((new SocialLink)->show_in_floating);
        $this->assertFalse($channel->fresh()->show_in_floating);
        $this->get('/')->assertDontSee('data-floating-social', false);
        $migration = require database_path('migrations/2026_11_01_105440_add_floating_placement_to_social_links_table.php');
        $migration->down();
        $migration->up();
        $this->assertFalse((bool) DB::table('social_links')->where('id', $channel->id)->value('show_in_floating'));
    }

    public function test_floating_setting_is_independent_preserves_omission_and_accepts_explicit_false(): void
    {
        $channel = $this->channel();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));
        $input = ['url_or_phone' => $channel->url_or_phone, 'label' => $channel->label, 'enabled' => true, 'sort_order' => 0, 'show_in_banner' => false, 'show_in_footer' => false, 'show_in_floating' => true];
        $this->post(route('admin.content.social.update'), ['socials' => [$channel->id => $input]])->assertSessionHasNoErrors();
        $this->get('/')->assertSee('data-floating-social', false)->assertDontSee('data-social-banner', false)->assertDontSee('data-social-placement="footer_social"', false);
        Setting::set('social.banner_enabled', false);
        unset($input['show_in_floating']);
        $this->post(route('admin.content.social.update'), ['socials' => [$channel->id => $input]])->assertSessionHasNoErrors();
        $this->assertTrue($channel->fresh()->show_in_floating);
        $input['show_in_floating'] = false;
        $this->post(route('admin.content.social.update'), ['socials' => [$channel->id => $input]])->assertSessionHasNoErrors();
        $this->assertFalse($channel->fresh()->show_in_floating);
        $this->assertDatabaseHas('audit_logs', ['action' => 'social_links_updated']);
        $this->assertFalse((bool) Setting::get('social.banner_enabled'));
    }

    public function test_filters_ineligible_links_and_preserves_custom_records_order_and_escaped_labels(): void
    {
        $this->channel(['label' => 'Later', 'sort_order' => 5, 'show_in_floating' => true]);
        $first = $this->channel(['label' => '<b>First custom</b>', 'show_in_floating' => true]);
        $second = $this->channel(['label' => 'Second custom', 'show_in_floating' => true]);
        $this->channel(['label' => 'Disabled record', 'enabled' => false, 'show_in_floating' => true]);
        $this->channel(['label' => 'Invalid record', 'url_or_phone' => 'javascript:alert(1)', 'show_in_floating' => true]);
        $this->channel(['label' => 'Deleted record', 'show_in_floating' => true])->delete();
        $response = $this->get('/')->assertSee('&lt;b&gt;First custom&lt;/b&gt;', false)->assertDontSee('<b>First custom</b>', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//nav[contains(@class,"floating-social-rail")]/a');
        $this->assertSame(3, $links->length);
        $this->assertSame('<b>First custom</b>', $links->item(0)->getAttribute('aria-label'));
        $this->assertSame('Second custom', $links->item(1)->getAttribute('aria-label'));
        $this->assertSame('noopener noreferrer', $links->item(0)->getAttribute('rel'));
        $this->assertLessThan($second->id, $first->id);
    }

    public function test_social_mutation_is_forbidden_for_unauthorized_actors_and_validation_keeps_checkbox(): void
    {
        $channel = $this->channel();
        $payload = ['socials' => [$channel->id => ['url_or_phone' => $channel->url_or_phone, 'sort_order' => -1, 'show_in_floating' => 1]]];
        $this->post(route('admin.content.social.update'), $payload)->assertRedirect(route('admin.login'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']))->post(route('admin.content.social.update'), $payload)->assertForbidden();
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']))->from(route('admin.content.index'))->post(route('admin.content.social.update'), $payload)->assertSessionHasErrors();
        $this->get(route('admin.content.index'))->assertSee('Show in floating social menu');
        $this->assertFalse($channel->fresh()->show_in_floating);
    }
}

<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\SocialLink;
use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\CMS\Services\TranslationService;
use App\Domains\CMS\Services\WebsiteCopyCatalog;
use App\Domains\CMS\Services\WebsiteCopyService;
use Database\Factories\AdministratorFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PublicContactReadinessTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([false, 'https://wa.me/12025550123'])]
    #[TestWith([true, ''])]
    #[TestWith([true, 'https://example.test/contact'])]
    #[TestWith([true, 'javascript:alert(1)'])]
    public function test_finished_help_and_pricing_remain_when_contact_is_unconfigured(bool $enabled, string $destination): void
    {
        Setting::set('whatsapp_public', $enabled);
        Setting::set('whatsapp_portal', true);
        Setting::set('whatsapp_url', $destination);

        $response = $this->get('/faq');

        $xpath = $this->xpath($response->getContent());
        $this->assertSame("Whether you're unsure where to begin, have a question about your booking, or want to discuss your learning goals, you're welcome to get in touch.", $xpath->evaluate('string(//section[@class="faq-help"]/div[1]/p[1])'));
        $this->assertSame("I'll be happy to help you understand your options.", $xpath->evaluate('string(//section[@class="faq-help"]/div[1]/p[2])'));
        $this->assertSame(2, $xpath->query('//section[@class="faq-help"]/div[1]/p')->length);
        $this->assertSame(0, $xpath->query('//a[@data-whatsapp-cta]')->length);
        $this->assertSame(url('/pricing'), $xpath->evaluate('string(//section[@class="faq-help"]//a/@href)'));
        $response->assertDontSeeText('awaiting confirmation')->assertDontSeeText('once confirmed');
        $this->get('/pricing')->assertSeeText('Individual sessions are arranged directly with Abdallah.')->assertDontSeeText('awaiting confirmation');
    }

    #[TestWith(['en', 'Hello & welcome', 'Chat with Abdallah'])]
    #[TestWith(['fr', 'Bonjour à vous', 'Discuter ensemble'])]
    #[TestWith(['de', 'Hallo für Sie', 'Abdallah kontaktieren'])]
    public function test_faq_and_floating_contact_share_the_localized_destination(string $locale, string $message, string $floatingLabel): void
    {
        Setting::set('whatsapp_public', true);
        Setting::set('whatsapp_portal', false);
        Setting::set('whatsapp_url', 'https://wa.me/12025550123?source=faq&text=old');
        Setting::set('whatsapp_message', [$locale => $message]);
        Setting::set('whatsapp_label', [$locale => $floatingLabel]);
        $expected = 'https://wa.me/12025550123?source=faq&text='.rawurlencode($message);

        $response = $this->get(($locale === 'en' ? '' : '/'.$locale).'/faq');

        $xpath = $this->xpath($response->getContent());
        $this->assertSame(2, $xpath->query('//a[@data-whatsapp-cta]')->length);
        $this->assertSame($expected, $xpath->evaluate('string(//section[@class="faq-help"]//a[@data-whatsapp-cta]/@href)'));
        $contactLabel = match ($locale) {
            'fr' => 'Contacter Abdallah',
            'de' => 'Abdallah kontaktieren',
            default => 'Contact Abdallah',
        };
        $this->assertSame($contactLabel, trim($xpath->evaluate('string(//section[@class="faq-help"]//a[@data-whatsapp-cta])')));
        $this->assertSame('faq_help', $xpath->evaluate('string(//section[@class="faq-help"]//a[@data-whatsapp-cta]/@data-whatsapp-placement)'));
        $floating = '//a[@data-whatsapp-cta and not(@data-whatsapp-placement)]';
        $this->assertSame($expected, $xpath->evaluate('string('.$floating.'/@href)'));
        $this->assertSame($floatingLabel, $xpath->evaluate('string('.$floating.'/@aria-label)'));
        foreach ($xpath->query('//a[@data-whatsapp-cta]') as $link) {
            $this->assertSame('_blank', $link->getAttribute('target'));
            $this->assertSame('noopener noreferrer', $link->getAttribute('rel'));
            $this->assertSame('public', $link->getAttribute('data-context'));
            $this->assertSame($locale, $link->getAttribute('data-language'));
        }
        $pricingPaths = ['en' => '/pricing', 'fr' => '/fr/tarifs', 'de' => '/de/preise'];
        $this->assertSame(url($pricingPaths[$locale]), $xpath->evaluate('string(//section[@class="faq-help"]//a[@class="studio-link"]/@href)'));
    }

    public function test_api_destination_without_localized_message_preserves_existing_query(): void
    {
        Setting::set('whatsapp_public', true);
        Setting::set('whatsapp_url', 'https://api.whatsapp.com/send?phone=12025550123&text=Existing');

        $response = $this->get('/fr/faq');

        $this->assertSame('https://api.whatsapp.com/send?phone=12025550123&text=Existing', $this->xpath($response->getContent())->evaluate('string(//section[@class="faq-help"]//a[@data-whatsapp-cta]/@href)'));
    }

    public function test_footer_omits_empty_contact_group_and_preserves_eligible_links(): void
    {
        SocialLink::create(['platform' => 'youtube', 'label' => 'Invalid target', 'url_or_phone' => 'javascript:alert(1)', 'enabled' => true, 'show_in_footer' => true]);
        SocialLink::create(['platform' => 'instagram', 'label' => 'Banner only', 'url_or_phone' => 'https://example.test/banner', 'enabled' => true, 'show_in_footer' => false]);
        SocialLink::create(['platform' => 'custom', 'label' => 'Disabled channel', 'url_or_phone' => 'https://example.test/disabled', 'enabled' => false, 'show_in_footer' => true]);

        $response = $this->get('/faq');

        $xpath = $this->xpath($response->getContent());
        $this->assertSame(0, $xpath->query('//footer//h3[contains(., "Connect Directly")]')->length);
        $this->assertSame(0, $xpath->query('//footer//a[@data-social-platform]')->length);
        $response->assertDontSeeText('Public contact details will be added once confirmed.');

        SocialLink::create(['platform' => 'youtube', 'label' => 'Official fixture channel', 'url_or_phone' => 'https://example.test/official', 'enabled' => true, 'show_in_footer' => true]);
        $xpath = $this->xpath($this->get('/faq')->getContent());

        $this->assertSame(1, $xpath->query('//footer//h3[contains(., "Connect Directly")]')->length);
        $this->assertSame(1, $xpath->query('//footer//a[@data-social-platform]')->length);
        $this->assertSame('https://example.test/official', $xpath->evaluate('string(//footer//a[@data-social-platform]/@href)'));
    }

    public function test_publishing_workbook_inactive_preserves_content_drafts_and_translations(): void
    {
        $question = "What's included in the Arabic Alphabet Workbook?";
        $workbook = Faq::create(['question' => $question, 'answer' => 'Retained workbook answer.', 'topic' => 'Resources & Games', 'sort_order' => 31, 'active' => true, 'featured' => false]);
        app(TranslationService::class)->updateEnglishSource($workbook, ['question' => $question, 'answer' => $workbook->answer]);
        foreach (['fr', 'de'] as $locale) {
            $workbook->translations()->create(['locale' => $locale, 'status' => 'published', 'question' => 'Workbook '.$locale, 'answer' => 'Preserved '.$locale]);
        }
        foreach (['Can I use your learning resources without taking private lessons?', 'Are the language games free?'] as $retained) {
            Faq::create(['question' => $retained, 'answer' => 'Preserved resource guidance.', 'topic' => 'Resources & Games', 'active' => true]);
        }
        $draft = $workbook->revisions()->create(['revision_number' => 1, 'title' => 'Unpublished workbook edit', 'content' => ['answer' => 'Private draft stays in history.'], 'status' => 'draft']);
        $translations = $workbook->translations()->get()->map->only(['locale', 'status', 'question', 'answer'])->all();
        $before = $workbook->only(['question', 'answer', 'sort_order', 'featured', 'topic']);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'admin']));

        $this->put(route('admin.content.faq.update', $workbook), [...$before, 'active' => false, 'action' => 'publish'])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertFalse($workbook->fresh()->active);
        $this->assertSame($before, $workbook->fresh()->only(array_keys($before)));
        $this->assertSame($translations, $workbook->translations()->get()->map->only(['locale', 'status', 'question', 'answer'])->all());
        $this->assertSame('archived', $draft->fresh()->status);
        $this->assertSame(['answer' => 'Private draft stays in history.'], $draft->fresh()->content);
        $this->assertSame(false, $workbook->revisions()->where('status', 'published')->firstOrFail()->content['active']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'faq_updated', 'entity_id' => $workbook->id]);
        foreach (['', '/fr', '/de'] as $prefix) {
            $questions = $prefix === '/de' ? 'Fragen' : 'questions';
            $this->get($prefix.'/faq')->assertSeeText('2 '.$questions)->assertDontSeeText($question)->assertSeeText('Are the language games free?')->assertSeeText('Can I use your learning resources without taking private lessons?');
            $this->get($prefix.'/faq?'.http_build_query(['topic' => 'Resources & Games']))->assertSeeText('2 '.$questions)->assertDontSeeText($question);
            $this->get($prefix.'/faq?q=workbook')->assertSeeText('0 '.$questions)->assertDontSeeText($question)->assertDontSeeText('Retained workbook answer.');
        }
    }

    public function test_existing_copy_field_identities_and_published_localized_overrides_remain_editable_and_escaped(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $catalog = app(WebsiteCopyCatalog::class);
        $service = app(WebsiteCopyService::class);
        $fields = $catalog->fields('faq');
        $this->assertArrayHasKey('ui__b0e5b39157295001850fde5aacabfff10f0213b7', $fields);
        $this->assertArrayHasKey('ui__3d78c6b253bebc371ace1d70d5cd3f0aaae4ff64', $catalog->fields('pricing'));
        $this->assertArrayHasKey('ui__269937e5b73e69c7c0727ea91388b57ba627d2d5', $fields);
        $values = $service->values('faq', 'en');
        $values['ui__b0e5b39157295001850fde5aacabfff10f0213b7'] = "Published invitation <img src=x onerror=alert(1)>\n\nPublished reassurance.";
        $values['ui__269937e5b73e69c7c0727ea91388b57ba627d2d5'] = 'Get in touch';
        $values['links__pricing'] = 'https://example.test/pricing?from=faq';
        $service->save($admin, 'faq', 'en', $values, 'publish', $service->state('faq', 'en'));
        WebsiteCopy::create(['page' => 'faq', 'locale' => 'fr', 'published_content' => ['ui__b0e5b39157295001850fde5aacabfff10f0213b7' => "Invitation française.\n\nVotre parcours."]]);
        WebsiteCopy::create(['page' => 'pricing', 'locale' => 'en', 'published_content' => ['ui__3d78c6b253bebc371ace1d70d5cd3f0aaae4ff64' => 'Published individual-session note.']]);
        Setting::set('whatsapp_public', true);
        Setting::set('whatsapp_url', 'https://wa.me/12025550123');

        $response = $this->get('/de/faq');

        $response->assertSeeText('Published invitation <img src=x onerror=alert(1)>')->assertSeeText('Published reassurance.')->assertSeeText('Get in touch')->assertDontSee('<img src=x', false)->assertSee('href="https://example.test/pricing?from=faq"', false);
        $this->assertSame(2, $this->xpath($response->getContent())->query('//section[@class="faq-help"]/div[1]/p')->length);
        $this->get('/fr/faq')->assertSeeText('Invitation française.')->assertSeeText('Votre parcours.')->assertDontSeeText('Published invitation');
        $this->get('/de/preise')->assertSeeText('Published individual-session note.');
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}

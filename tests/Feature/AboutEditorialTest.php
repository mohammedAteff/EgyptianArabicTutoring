<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\CMS\Services\WebsiteCopyService;
use Database\Factories\AdministratorFactory;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AboutEditorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_narrative_preserves_six_sections_principles_contexts_and_offer(): void
    {
        $response = $this->get('/about');

        $response->assertOk()->assertSeeText("Hi, I'm Abdallah.")
            ->assertSeeText("And that's the kind of experience I try to create.")
            ->assertSeeText('One of my favorite parts of teaching is seeing something that once seemed complicated finally begin to make sense.')
            ->assertSeeText("Arabic takes practice and consistency. But that doesn't mean the process can't also be interesting, engaging, and enjoyable.")
            ->assertSeeText('My aim is to make the experience feel connected, so every lesson has something to build on.')
            ->assertSeeText("Whatever your motivation, we can build an approach around what you're trying to achieve.")
            ->assertSeeText('$25 USD · 60 minutes')->assertSeeText("There's no obligation to purchase a package afterward.")
            ->assertSeeText('If you decide to enroll in an eligible 8- or 12-session coaching package within 48 hours of completing your diagnostic, the full $25 you\'ve already paid is credited toward your package price.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(6, $xpath->query('//main//section[starts-with(@id,"about-section-")]')->length);
        $this->assertSame(1, $xpath->query('//main//h1')->length);
        $this->assertSame(5, $xpath->query('//main//h2')->length);
        $this->assertSame(4, $xpath->query('//*[@id="about-section-3"]//ol/li/h3 | //*[@id="about-section-3"]//ol/li/div/h3')->length);
        $this->assertSame(3, $xpath->query('//*[@id="about-section-5"]//h3')->length);
        $this->assertSame(0, $xpath->query('//main//img')->length);
        $this->assertSame('/booking', parse_url($xpath->evaluate('string(//main//a[@data-cta="booking"]/@href)'), PHP_URL_PATH));
        $this->assertSame('/pricing', parse_url($xpath->evaluate('string(//main//a[contains(.,"Explore Lessons")]/@href)'), PHP_URL_PATH));
        $this->assertStringContainsString('born and raised in Cairo', $xpath->evaluate('string(//meta[@name="description"]/@content)'));
    }

    public function test_layout_identity_and_actions_survive_section_reordering_and_visibility(): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => [
            '_sections__about__5__order' => 1, '_sections__about__0__order' => 9,
            '_sections__about__1__visible' => false, 'about__2__blocks__3__text' => 'A different learning plan heading',
            'links__booking' => '/booking', 'links__pricing' => '/pricing',
        ]]);

        $response = $this->get('/about');

        $response->assertDontSeeText("I've Always Been Fascinated by How Languages Work.");
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('about-section-6', $xpath->evaluate('string((//main//section[starts-with(@id,"about-section-")])[1]/@id)'));
        $this->assertSame('about-section-1', $xpath->evaluate('string((//main//section[starts-with(@id,"about-section-")])[last()]/@id)'));
        $this->assertSame("Hi, I'm Abdallah.", $xpath->evaluate('string(//main//h1)'));
        $this->assertSame('A different learning plan heading', $xpath->evaluate('string((//*[@id="about-section-3"]//h3)[1])'));
        $this->assertSame(1, $xpath->query('//*[@id="about-section-6"]//a[@data-cta="booking"]')->length);
    }

    public function test_hidden_introduction_keeps_one_primary_heading_without_a_portrait_placeholder(): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => ['_sections__about__0__visible' => false]]);

        $response = $this->get('/about');

        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//main//h1')->length);
        $this->assertSame("I've Always Been Fascinated by How Languages Work.", $xpath->evaluate('string(//main//h1)'));
        $this->assertSame(0, $xpath->query('//*[@id="about-section-1"] | //main//figure')->length);
    }

    public function test_legacy_rich_bodies_keep_their_complete_content_without_assuming_english_structure(): void
    {
        Setting::set('about_biography', '<p>Custom introduction.</p><p><bdi lang="ar" dir="rtl">أهلاً</bdi> A second idea.</p><ul><li>One preserved item</li><li>Another preserved item</li></ul><script>alert("unsafe-biography")</script>');
        Setting::set('about_philosophy', '<h3>A custom approach</h3><p>Beginning of the explanation.</p><p>Complete final paragraph.</p><script>alert("unsafe-philosophy")</script>');

        $response = $this->get('/about');

        $response->assertSeeText('Custom introduction.')->assertSeeText('A second idea.')
            ->assertSeeText('One preserved item')->assertSeeText('Another preserved item')
            ->assertSeeText('Beginning of the explanation.')->assertSeeText('Complete final paragraph.')
            ->assertDontSee('unsafe-biography')->assertDontSee('unsafe-philosophy');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(2, $xpath->query('//*[@id="about-section-1"]//ul/li')->length);
        $this->assertSame(0, $xpath->query('//*[@id="about-section-3"]//ol')->length);
    }

    public function test_approved_numbered_legacy_philosophy_keeps_every_passage_in_four_identifiable_principles(): void
    {
        $blocks = config('public_content.about.2.blocks');
        $legacy = implode("\n\n", array_column($blocks, 'text'));
        Setting::set('about_philosophy', $legacy);

        $response = $this->get('/about');

        $xpath = $this->xpath($response->getContent());
        $headings = $xpath->query('//*[@id="about-section-3"]//ol/li/div/h3');
        $this->assertSame(4, $headings->length);
        $this->assertSame('A Learning Plan That Adapts to You', $headings->item(0)->textContent);
        $this->assertSame('Clear Explanations, Not Just Rules to Memorize', $headings->item(1)->textContent);
        $this->assertSame('Practice That Builds on Understanding', $headings->item(2)->textContent);
        $this->assertSame('A Comfortable Place to Learn', $headings->item(3)->textContent);
        foreach ($blocks as $block) {
            if ($block['type'] !== 'heading') {
                $response->assertSeeText($block['text']);
                $this->assertSame(1, substr_count($xpath->evaluate('string(//*[@id="about-section-3"])'), $block['text']));
            }
        }
        $this->assertSame($legacy, Setting::get('about_philosophy'));
    }

    public function test_published_copy_inheriting_a_legacy_body_receives_the_same_principle_grouping(): void
    {
        Setting::set('about_philosophy', "An independent introduction.\n\n1. First custom idea\n\nFirst complete explanation.\n\n2. Second custom idea\n\nSecond complete explanation.");
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => ['about__0__title' => 'Published greeting']]);

        $response = $this->get('/about');

        $response->assertSeeText('Published greeting')->assertSeeText('An independent introduction.')
            ->assertSeeText('First complete explanation.')->assertSeeText('Second complete explanation.');
        $this->assertSame(2, $this->xpath($response->getContent())->query('//*[@id="about-section-3"]//ol/li/div/h3')->length);
    }

    #[TestWith(['lines'])]
    #[TestWith(['paragraphs'])]
    public function test_numbered_legacy_formats_use_structure_instead_of_english_heading_text(string $format): void
    {
        $separator = $format === 'lines' ? "\n" : "\n\n";
        Setting::set('about_philosophy', implode($separator, ['Une introduction.', '1. Comprendre ensemble', 'Une explication complète.', '2. Pratiquer avec confiance', 'La dernière idée.']));

        $response = $this->get('/about');

        $response->assertSeeTextInOrder(['Une introduction.', 'Comprendre ensemble', 'Une explication complète.', 'Pratiquer avec confiance', 'La dernière idée.']);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(2, $xpath->query('//*[@id="about-section-3"]//ol/li/div/h3')->length);
        $this->assertSame('Comprendre ensemble', $xpath->evaluate('string((//*[@id="about-section-3"]//h3)[1])'));
    }

    #[TestWith(['h3'])]
    #[TestWith(['p'])]
    public function test_rich_principles_preserve_nested_content_links_and_safe_heading_markup(string $tag): void
    {
        $firstPrefix = $tag === 'p' ? '1. ' : '';
        $secondPrefix = $tag === 'p' ? '2. ' : '';
        Setting::set('about_philosophy', '<p>Complete introduction.</p><'.$tag.'>'.$firstPrefix.'<em>Custom learning</em></'.$tag.'><p>First explanation.</p><ul><li>Nested practice <a href="https://example.test/learn" onclick="unsafe()">Useful link</a><ul><li>Inner item.</li></ul></li></ul><'.$tag.'>'.$secondPrefix.'<a href="/pricing">Another approach</a></'.$tag.'><blockquote><h4>Nested supporting title</h4><p>Complete nested explanation.</p></blockquote><p>Final explanation.</p><script>unsafe-script()</script>');

        $response = $this->get('/about');

        $response->assertSeeTextInOrder(['Complete introduction.', 'Custom learning', 'First explanation.', 'Nested practice', 'Useful link', 'Inner item.', 'Another approach', 'Nested supporting title', 'Complete nested explanation.', 'Final explanation.'])
            ->assertDontSee('onclick=')->assertDontSee('unsafe-script');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(2, $xpath->query('//*[@id="about-section-3"]//ol/li/div/h3')->length);
        $this->assertSame(1, $xpath->query('//*[@id="about-section-3"]//h3/em')->length);
        $this->assertSame('/pricing', $xpath->evaluate('string(//*[@id="about-section-3"]//h3/a/@href)'));
        $this->assertSame(1, $xpath->query('//*[@id="about-section-3"]//blockquote/h4')->length);
        $this->assertSame(1, $xpath->query('//*[@id="about-section-3"]//ul/li/ul/li')->length);
    }

    #[TestWith(['<p>1. An incomplete sequence</p><p>Preserve this explanation.</p><p>3. A custom third heading</p><ul><li>Preserve this item.</li></ul>'])]
    #[TestWith(['<p>1. An ordinary numbered line</p><p>2. Another numbered line</p><p>Preserve this explanation.</p><ul><li>Preserve this item.</li></ul>'])]
    public function test_ambiguous_custom_rich_text_remains_complete_without_forced_principles(string $body): void
    {
        Setting::set('about_philosophy', $body);

        $response = $this->get('/about');

        $response->assertSeeText('Preserve this explanation.')->assertSeeText('Preserve this item.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(0, $xpath->query('//*[@id="about-section-3"]//ol')->length);
        $this->assertSame(1, $xpath->query('//*[@id="about-section-3"]//ul/li')->length);
        $this->assertSame(1, substr_count($xpath->evaluate('string(//*[@id="about-section-3"])'), '1.'));
    }

    public function test_learning_group_labels_remain_editable_and_translatable_without_losing_support_conditions(): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'de', 'published_content' => [
            'ui__'.sha1('Live explanations') => 'Gemeinsam verstehen',
            'ui__'.sha1('Materials to revisit') => 'Materialien zum Nachlesen',
            'ui__'.sha1('Between our lessons') => 'Zwischen den Stunden',
        ]]);

        $response = $this->get('/de/ueber-uns');

        $response->assertSeeText('Gemeinsam verstehen')->assertSeeText('Materialien zum Nachlesen')->assertSeeText('Zwischen den Stunden')
            ->assertSeeText('kann ich kurze Audioerklärungen, Bildschirmaufnahmen oder Übungen vorbereiten')
            ->assertSeeText('ausführlichere Themen bekommen in unserer nächsten Sitzung die nötige Zeit.');
    }

    public function test_unstructured_fallback_keeps_its_own_ordered_list_link_and_multilingual_content(): void
    {
        Setting::set('about_philosophy', "\n<h3>My own approach</h3>\n<p>A complete <strong>custom explanation</strong> with أهلاً.</p><ol><li>Original first step<ul><li>A nested supporting detail.</li></ul></li><li>Original second step <a href=\"/resources\">Read more</a></li></ol><p>The complete ending.</p><script>unsafe-fallback()</script>");

        $response = $this->get('/about');

        $response->assertSeeTextInOrder(['My own approach', 'custom explanation', 'أهلاً', 'Original first step', 'A nested supporting detail.', 'Original second step', 'Read more', 'The complete ending.'])
            ->assertDontSee('unsafe-fallback');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//*[@id="about-section-3"]//ol')->length);
        $this->assertSame(2, $xpath->query('//*[@id="about-section-3"]//ol/li')->length);
        $this->assertSame(0, $xpath->query('//*[@id="about-section-3"]//ol/li//h3')->length);
        $this->assertSame('/resources', $xpath->evaluate('string(//*[@id="about-section-3"]//a/@href)'));
    }

    public function test_published_rich_blocks_and_additional_content_are_preserved_and_sanitized(): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => [
            'about__0__blocks__0__text' => '<p><strong>Published introduction</strong></p><p>Its complete second paragraph.</p><script>alert("unsafe-block")</script>',
            'about__2__blocks__4__text' => '<ul><li>A tailored principle explanation</li></ul>',
            '_sections__about__2__extra' => '<p>Additional philosophy.</p><ul><li>Additional practice.</li></ul><script>alert("unsafe-extra")</script>',
        ]]);

        $response = $this->get('/about');

        $response->assertSee('<strong>Published introduction</strong>', false)->assertSeeText('Its complete second paragraph.')
            ->assertSeeText('A tailored principle explanation')->assertSeeText('Additional philosophy.')->assertSeeText('Additional practice.')
            ->assertDontSee('unsafe-block')->assertDontSee('unsafe-extra');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(1, $xpath->query('//*[@id="about-section-3"]//ol/li[1]//ul/li')->length);
        $this->assertSame(1, $xpath->query('//*[@id="about-section-3"]//ul/li[contains(.,"Additional practice")]')->length);
    }

    public function test_about_draft_preview_uses_the_same_page_without_publishing_the_draft(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $copies = app(WebsiteCopyService::class);
        $copies->save($actor, 'about', 'en', array_replace($copies->values('about'), ['about__0__title' => 'Private About headline']), 'draft', $copies->state('about', 'en'));

        $this->get('/about')->assertDontSeeText('Private About headline');
        $response = $this->actingAs($actor)->get(route('admin.website-copy.preview', ['page' => 'about', 'locale' => 'en']));

        $response->assertSeeText('Private About headline')->assertSeeText('A Comfortable Place to Learn')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertNull(WebsiteCopy::query()->where('page', 'about')->sole()->published_content);
        $this->assertSame(1, $this->xpath($response->getContent())->query('//main//h1')->length);
    }

    public function test_about_preview_endpoints_reject_guests_and_the_copy_preview_rejects_an_assistant(): void
    {
        $this->get('/about/preview')->assertForbidden();
        $this->get('/p/about/preview')->assertForbidden();
        $this->get(route('admin.website-copy.preview', ['page' => 'about', 'locale' => 'en']))->assertRedirect();
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);

        $this->actingAs($assistant)->get(route('admin.website-copy.preview', ['page' => 'about', 'locale' => 'en']))->assertForbidden();
    }

    public function test_legacy_about_previews_show_complete_draft_overrides_without_affecting_the_public_page(): void
    {
        $page = Page::query()->create(['slug' => 'about', 'title' => 'Published About', 'content' => 'Published biography.', 'status' => 'published']);
        Setting::set('draft:about_biography', '<p>Private biography beginning.</p><p>Private biography ending.</p>');
        Setting::set('draft:about_philosophy', '<h3>A private approach</h3><p>Private philosophy ending.</p>');
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $this->get('/about')->assertSeeText('Published biography.')->assertDontSeeText('Private biography beginning.');
        $this->actingAs($admin);

        foreach (['/about/preview', '/p/about/preview'] as $path) {
            $response = $this->get($path);
            $response->assertSeeText('Private biography beginning.')->assertSeeText('Private biography ending.')
                ->assertSeeText('Private philosophy ending.')->assertSeeText('Administrator Preview Mode');
            $this->assertSame(1, $this->xpath($response->getContent())->query('//main//h1')->length);
        }
        $this->assertSame('Published biography.', $page->fresh()->content);
    }

    public function test_published_cta_labels_destinations_and_seo_remain_configurable(): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => [
            'links__booking' => 'https://example.test/diagnostic', 'links__pricing' => '/pricing',
            'ui__'.sha1('Book Your Diagnostic') => 'Find a time with Abdallah',
            'ui__'.sha1('Explore Lessons & Pricing') => 'Read the lesson options',
            'seo__title' => 'Meet your Arabic teacher', 'seo__description' => 'A published About description.',
        ]]);

        $response = $this->get('/about');

        $response->assertSeeText('Find a time with Abdallah')->assertSeeText('Read the lesson options');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('https://example.test/diagnostic', $xpath->evaluate('string(//main//a[@data-cta="booking"]/@href)'));
        $this->assertSame('A published About description.', $xpath->evaluate('string(//meta[@name="description"]/@content)'));
        $this->assertSame('Meet your Arabic teacher — Egyptian Arabic with Abdallah', $xpath->evaluate('string(//title)'));
    }

    #[TestWith(['/fr/a-propos'])]
    #[TestWith(['/de/ueber-uns'])]
    public function test_untranslated_about_keeps_english_language_attributes_and_a_fallback_notice(string $path): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => [
            'about__0__title' => 'A custom English profile awaiting translation.',
        ]]);
        $response = $this->get($path);

        $response->assertSeeText('A custom English profile awaiting translation.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('en', $xpath->evaluate('string(//html/@lang)'));
        $this->assertSame(1, $xpath->query('//main/p[@role="status"]')->length);
        $this->assertSame('en', $xpath->evaluate('string(//*[@id="about-section-1"]/@lang)'));
    }

    public function test_published_page_translation_retains_its_biography_language_with_english_sections(): void
    {
        $page = Page::query()->create(['slug' => 'about', 'title' => 'About', 'status' => 'published', 'content' => 'English legacy biography']);
        $page->translations()->create(['locale' => 'fr', 'title' => 'À propos d’Abdallah', 'content' => '<p>Une biographie approuvée.</p><p>La suite de cette biographie.</p>', 'status' => 'published', 'published_at' => now()->subDay()]);

        $response = $this->get('/fr/a-propos');

        $response->assertSeeText('À propos d’Abdallah')->assertSeeText('Une biographie approuvée.')->assertSeeText('La suite de cette biographie.');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('fr', $xpath->evaluate('string(//*[@id="about-section-1"]/@lang)'));
        $this->assertSame('en', $xpath->evaluate('string(//*[@id="about-section-2"]/@lang)'));
    }

    public function test_published_website_copy_translation_keeps_translated_principles_and_local_ctas(): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'de', 'published_content' => [
            'about__0__title' => 'Ein freigegebener Titel', 'about__2__blocks__3__text' => 'Ein persönlicher Lernplan',
        ]]);

        $response = $this->get('/de/ueber-uns');

        $response->assertSeeText('Ein freigegebener Titel')->assertSeeText('Ein persönlicher Lernplan');
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('de', $xpath->evaluate('string(//*[@id="about-section-3"]/@lang)'));
        $this->assertSame(0, $xpath->query('//main/p[@role="status"]')->length);
        $this->assertSame('/de/buchen', parse_url($xpath->evaluate('string(//main//a[@data-cta="booking"]/@href)'), PHP_URL_PATH));
    }

    public function test_diagnostic_actions_share_the_offer_with_its_complete_terms(): void
    {
        $response = $this->get('/about');

        $response->assertOk();
        $xpath = $this->xpath($response->getContent());
        $panel = '//*[@id="about-section-6"]//div[contains(concat(" ",normalize-space(@class)," ")," about-profile-offer-panel ")]';
        $this->assertSame(1, $xpath->query($panel.'//a[@data-cta="booking"]')->length);
        $this->assertSame(1, $xpath->query($panel.'//a[contains(.,"Explore Lessons")]')->length);
        $this->assertSame(2, $xpath->query('//*[@id="about-section-6"]//a')->length);
        $offer = $xpath->evaluate('string('.$panel.')');
        foreach (['Diagnostic & Learning Roadmap', '$25 USD · 60 minutes', "There's no obligation to purchase a package afterward.", 'eligible 8- or 12-session coaching package within 48 hours', 'credited toward your package price.'] as $passage) {
            $this->assertStringContainsString($passage, $offer);
        }
        $this->assertSame(0, $xpath->query($panel.'//*[@tabindex] | '.$panel.'[@tabindex]')->length);
    }

    public function test_unstructured_custom_offer_keeps_all_rich_content_and_actions_without_inventing_groups(): void
    {
        config(['public_content.about.5.blocks' => [
            ['type' => 'paragraph', 'text' => '<p>Une invitation personnelle.</p><ul><li>Premier point</li><li><bdi lang="ar" dir="rtl">أهلاً</bdi> Une précision.</li></ul>'],
            ['type' => 'paragraph', 'text' => '<p>Dernière condition sans prix prédéfini.</p><script>alert("unsafe-offer")</script>'],
        ]]);
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => [
            '_sections__about__5__order' => 1,
            '_sections__about__0__order' => 9,
            '_sections__about__5__extra' => '<p>Additional offer context.</p>',
            'ui__'.sha1('Book Your Diagnostic') => 'Choose our first conversation',
        ]]);

        $response = $this->get('/about');

        $response->assertOk()->assertDontSee('unsafe-offer')->assertSeeText('Additional offer context.');
        $xpath = $this->xpath($response->getContent());
        $panel = '//*[@id="about-section-6"]//div[contains(concat(" ",normalize-space(@class)," ")," about-profile-offer-panel ")]';
        foreach (['Une invitation personnelle.', 'Premier point', 'Une précision.', 'Dernière condition sans prix prédéfini.', 'Choose our first conversation', 'Explore Lessons & Pricing'] as $passage) {
            $this->assertSame(1, substr_count($xpath->evaluate('string('.$panel.')'), $passage));
        }
        $this->assertSame(2, $xpath->query($panel.'//ul/li')->length);
        $this->assertSame(0, $xpath->query($panel.'//h3')->length);
        $this->assertSame(1, $xpath->query('//main//a[@data-cta="booking"]')->length);
        $this->assertSame('about-section-6', $xpath->evaluate('string((//main//section[starts-with(@id,"about-section-")])[1]/@id)'));
    }

    public function test_hidden_invitation_does_not_leave_an_offer_or_disconnected_actions(): void
    {
        WebsiteCopy::query()->create(['page' => 'about', 'locale' => 'en', 'published_content' => ['_sections__about__5__visible' => false]]);

        $response = $this->get('/about');

        $response->assertOk();
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(0, $xpath->query('//*[@id="about-section-6"] | //main//a[@data-cta="booking"]')->length);
        $this->assertSame(0, $xpath->query('//main//a[contains(.,"Explore Lessons")]')->length);
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}

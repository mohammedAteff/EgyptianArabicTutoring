<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\PageTranslation;
use App\Services\ContentService;
use Database\Seeders\PolicyPagesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\DomCrawler\Crawler;
use Tests\TestCase;

class PhaseEVerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_pricing_page_structure_and_canonical_values(): void
    {
        $response = $this->get('/pricing');
        $response->assertOk();

        // Verify view and action
        $response->assertViewIs('public.pricing');

        // Numbers check
        $response->assertSee('25'); // Diagnostic
        $response->assertSee('280'); // Foundation Total
        $response->assertSee('255'); // Foundation Invoice
        $response->assertSee('390'); // Fluency Total
        $response->assertSee('365'); // Fluency Invoice

        // Confirm primary CTA points to booking and does not invoke payment gateways
        $content = $response->getContent();
        $crawler = new Crawler($content);
        $ctaHref = $crawler->filter('a[data-cta="booking"][data-primary-cta="diagnostic"]')->attr('href');
        $this->assertStringContainsString('/booking', $ctaHref);

        // Assert absence of checkout forms or card inputs
        $this->assertCount(0, $crawler->filter('form[action*="checkout"]'));
        $this->assertCount(0, $crawler->filter('input[type="credit-card"]'));
    }

    /** @test */
    public function test_localized_pricing_pages_render_and_point_to_localized_booking(): void
    {
        // French pricing
        $frResponse = $this->get('/fr/tarifs');
        $frResponse->assertOk();
        $frResponse->assertViewIs('public.pricing');
        $frContent = $frResponse->getContent();
        $frCrawler = new Crawler($frContent);
        $frCtaHref = $frCrawler->filter('a[data-cta="booking"][data-primary-cta="diagnostic"]')->attr('href');
        $this->assertStringContainsString('/fr/reservation', $frCtaHref);

        // German pricing
        $deResponse = $this->get('/de/preise');
        $deResponse->assertOk();
        $deResponse->assertViewIs('public.pricing');
        $deContent = $deResponse->getContent();
        $deCrawler = new Crawler($deContent);
        $deCtaHref = $deCrawler->filter('a[data-cta="booking"][data-primary-cta="diagnostic"]')->attr('href');
        $this->assertStringContainsString('/de/buchen', $deCtaHref);
    }

    /** @test */
    public function test_stale_about_translation_remains_publicly_visible_and_updates_database_states(): void
    {
        // 1. Create canonical About page with French Rev 1
        $page = app(ContentService::class)->createPage([
            'slug' => 'about',
            'en' => ['title' => 'About Me', 'content' => 'Original English'],
            'fr' => ['title' => 'À Propos', 'content' => 'Texte français original'],
        ]);

        // Verify initial database states
        $this->assertEquals('published', $page->translations()->where('locale', 'fr')->value('status'));
        $this->assertEquals(1, $page->translations()->where('locale', 'fr')->value('source_revision_id'));
        $this->assertEquals(1, $page->current_source_revision);

        // 2. Publish English Rev 2 -> French Rev 1 transitions to stale in database
        app(ContentService::class)->updateEnglishSource($page, [
            'title' => 'About Abdallah',
            'content' => 'Updated English Content',
        ]);
        $page->refresh();

        // Verify exact database state transition
        $this->assertEquals('stale', $page->translations()->where('locale', 'fr')->value('status'));
        $this->assertEquals(1, $page->translations()->where('locale', 'fr')->value('source_revision_id'));
        $this->assertEquals(2, $page->current_source_revision);

        // 3. Verify public rendering
        $response = $this->get('/fr/a-propos');
        $response->assertOk();

        // Must render French content, NOT English fallback
        $response->assertSee('Texte français original');
        $response->assertDontSee('Updated English Content');
        $response->assertDontSee('Ce contenu n\'est pas encore disponible en français');

        // Canonical link points to itself (French URL)
        $content = $response->getContent();
        $crawler = new Crawler($content);
        $canonicalHref = $crawler->filter('link[rel="canonical"]')->attr('href');
        $this->assertEquals(url('/fr/a-propos'), $canonicalHref);
    }

    /** @test */
    public function test_untranslated_privacy_policy_falls_back_to_english_with_alert(): void
    {
        // Clean any existing 'fr' translation for privacy to ensure pure untranslated state
        $existing = Page::where('slug', 'privacy')->first();
        if ($existing) {
            PageTranslation::where('page_id', $existing->id)->where('locale', '!=', 'en')->delete();
        }

        // Seed Privacy page with English translation only
        $page = app(ContentService::class)->createPage([
            'slug' => 'privacy',
            'en' => ['title' => 'Privacy Policy', 'content' => 'Authoritative English Privacy Text'],
        ]);

        // Verify English Revision 1 snapshot exists in immutable revision history
        $this->assertDatabaseHas('entity_translation_revisions', [
            'entity_type' => 'page',
            'entity_id' => $page->id,
            'revision_number' => 1,
            'locale' => 'en',
        ]);

        $response = $this->get('/fr/confidentialite');
        $response->assertOk();

        // Fallback banner rendered
        $response->assertSee(__('content_fallback_banner', [], 'fr'));
        $response->assertSee('Authoritative English Privacy Text');

        // Canonical points to canonical English route
        $content = $response->getContent();
        $crawler = new Crawler($content);
        $canonicalHref = $crawler->filter('link[rel="canonical"]')->attr('href');
        $this->assertEquals(url('/privacy'), $canonicalHref);

        // Hreflang suppressed for fallback locale
        $hreflangs = $crawler->filter('link[rel="alternate"][hreflang="fr"]');
        $this->assertCount(0, $hreflangs);
    }

    /** @test */
    public function test_policy_pages_seeder_seeds_authoritative_privacy_and_terms(): void
    {
        $this->seed(PolicyPagesSeeder::class);

        $privacy = Page::where('slug', 'privacy')->first();
        $this->assertNotNull($privacy);
        $this->assertEquals('published', $privacy->translations()->where('locale', 'en')->value('status'));

        $terms = Page::where('slug', 'terms')->first();
        $this->assertNotNull($terms);
        $this->assertEquals('published', $terms->translations()->where('locale', 'en')->value('status'));
        $this->assertStringContainsString(
            'qualified legal counsel',
            $privacy->translations()->where('locale', 'en')->value('content'),
        );

        $this->assertDatabaseHas('entity_translation_revisions', [
            'entity_type' => 'page',
            'entity_id' => $privacy->id,
            'revision_number' => 1,
            'locale' => 'en',
        ]);

        $this->assertDatabaseHas('entity_translation_revisions', [
            'entity_type' => 'page',
            'entity_id' => $terms->id,
            'revision_number' => 1,
            'locale' => 'en',
        ]);
    }

    /** @test */
    public function test_missing_policy_records_do_not_render_invented_legal_claims(): void
    {
        Page::query()->whereIn('slug', ['privacy', 'terms'])->get()->each->delete();

        $privacy = $this->get('/privacy')->assertOk();
        $privacy->assertSee('Authoritative policy unavailable');
        $privacy->assertDontSee('No Third-Party Tracking Scripts');
        $privacy->assertDontSee('retained for a maximum of 180 days');

        $terms = $this->get('/terms')->assertOk();
        $terms->assertSee('Authoritative terms unavailable');
        $terms->assertDontSee('Rescheduling & Cancellation Policy');
        $terms->assertDontSee('24 hours prior');
    }
}

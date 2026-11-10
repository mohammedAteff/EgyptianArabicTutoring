<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\ContentService;
use App\Domains\CMS\Services\TranslationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LegalPagePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_numbered_terms_have_linked_sections_and_preserve_escaped_copy_and_review_details(): void
    {
        Http::preventStrayRequests();
        Page::query()->create(['slug' => 'terms', 'title' => 'Terms & Conditions', 'status' => 'published', 'published_at' => now(),
            'content' => "Local working copy.\r\n\r\n1. [T-01] Provider\r\nContact details [CONFIRM: OPERATOR-01].\r\n\r\n2. [T-02] <script>alert(1)</script>\r\nA & B <img src=x onerror=alert(2)>\r\n\r\nLast paragraph stays visible."]);

        $response = $this->get(route('terms', ['lang' => 'en']))->assertOk()
            ->assertSee('href="#legal-section-1"', false)->assertSee('id="legal-section-2"', false)
            ->assertSeeText('OPERATOR-01')->assertSeeText('Local working copy.')->assertSeeText('Last paragraph stays visible.')
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('<img src=x onerror=alert(2)>')->assertDontSee('<img src=x onerror=alert(2)>', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(2, $xpath->query('//section[starts-with(@id,"legal-section-")]')->length);
        $this->assertSame(1, $xpath->query('//h1')->length);
        Http::assertNothingSent();
    }

    #[TestWith(['terms', '/fr/conditions'])]
    #[TestWith(['privacy', '/fr/confidentialite'])]
    public function test_published_localized_copy_is_used_with_localized_policy_navigation(string $slug, string $path): void
    {
        Http::preventStrayRequests();
        $page = app(ContentService::class)->createPage(['slug' => $slug, 'en' => ['title' => 'English policy', 'content' => "1. Details\nEnglish-only details."]]);
        $content = "1. Votre parcours\nDes informations en français.\n\nA & B <script>alert(1)</script>\n";
        app(TranslationService::class)->saveDraft($page, 'fr', ['title' => 'Informations importantes', 'content' => $content]);
        app(TranslationService::class)->publishTranslation($page, 'fr');

        $this->get($path.'?lang=fr')->assertOk()->assertSeeText('Des informations en français.')
            ->assertDontSeeText('English-only details.')->assertSee('href="'.localized_url('policy', locale: 'fr').'"', false)
            ->assertSee('A & B <script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false);
        $this->assertSame($content, $page->fresh()->liveTranslation('fr')->content);
        Http::assertNothingSent();
    }

    public function test_legacy_unstructured_copy_remains_visible_without_inventing_section_headings(): void
    {
        Http::preventStrayRequests();
        Page::query()->create(['slug' => 'privacy', 'title' => 'Privacy', 'status' => 'published', 'published_at' => now(), 'content' => "First paragraph.\nSecond line.\n\nFinal paragraph."]);

        $this->get(route('privacy', ['lang' => 'en']))->assertOk()->assertSeeText('First paragraph.')
            ->assertSeeText('Second line.')->assertSeeText('Final paragraph.')->assertDontSee('id="legal-section-1"', false);
        Http::assertNothingSent();
    }

    public function test_other_cms_pages_keep_their_existing_rich_text_sanitization(): void
    {
        $page = app(ContentService::class)->createPage(['slug' => 'information', 'en' => ['title' => 'Information', 'content' => 'Original text.']]);
        app(TranslationService::class)->saveDraft($page, 'fr', ['title' => 'Informations', 'content' => '<p>Visible <strong>formatting</strong>.<script>alert(1)</script></p>']);
        app(TranslationService::class)->publishTranslation($page, 'fr');

        $this->assertSame('<p>Visible <strong>formatting</strong>.</p>', $page->fresh()->liveTranslation('fr')->content);
    }

    public function test_policy_blocks_keep_subheadings_and_sanitize_allowed_rich_text(): void
    {
        config(['public_content.policy.blocks' => [
            ['type' => 'paragraph', 'text' => 'Policy introduction.'],
            ['type' => 'heading', 'text' => '1. Refunds'],
            ['type' => 'heading', 'text' => 'A. Partially used packages'],
            ['type' => 'paragraph', 'text' => '<strong>First payment</strong><script>alert(1)</script>'],
        ]]);

        $this->view('public.policy', ['title' => 'Policy', 'copyLocale' => 'en'])->assertSee('id="legal-section-1"', false)
            ->assertSee('<h3>A. Partially used packages</h3>', false)->assertSee('<strong>First payment</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)->assertSee('Policy introduction.');
    }
}

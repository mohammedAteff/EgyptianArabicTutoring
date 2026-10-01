<?php

namespace Tests\Feature;

use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\TranslationService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LocalizedPublicFlowAndSeoTest extends TestCase
{
    use RefreshDatabase;

    protected TranslationService $translationService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->translationService = app(TranslationService::class);
    }

    public function test_french_and_german_homepage_renders_localized_shell_and_navigation(): void
    {
        // English
        $responseEn = $this->get('/');
        $responseEn->assertStatus(200);
        $responseEn->assertSee('Home');
        $responseEn->assertSee('Resources');
        $responseEn->assertSee('Book a Lesson');

        // French
        $responseFr = $this->get('/fr');
        $responseFr->assertStatus(200);
        $responseFr->assertSee('Accueil');
        $responseFr->assertSee('Ressources');
        $responseFr->assertSee('Réserver une leçon');
        $responseFr->assertSee('href="'.url('/fr/reservation').'"', false);

        // German
        $responseDe = $this->get('/de');
        $responseDe->assertStatus(200);
        $responseDe->assertSee('Startseite');
        $responseDe->assertSee('Ressourcen');
        $responseDe->assertSee('Stunde buchen');
        $responseDe->assertSee('href="'.url('/de/buchen').'"', false);
    }

    public function test_resource_detail_with_published_translation_renders_translated_content_and_correct_seo(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Grammar Guides',
            'slug' => 'grammar-guides',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Egyptian Verbs 101',
            'slug' => 'egyptian-verbs-101',
            'short_description' => 'Complete guide to Egyptian verbs in English.',
            'category_id' => $category->id,
            'is_active' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'file_path' => 'resources/dummy.pdf',
            'file_type' => 'pdf',
        ]);

        // Publish French translation
        $this->translationService->saveDraft($resource, 'fr', [
            'title' => 'Verbes Égyptiens 101',
            'short_description' => 'Guide complet des verbes égyptiens en français.',
        ]);
        $this->translationService->publishTranslation($resource, 'fr');

        // Request French resource detail
        $response = $this->get('/fr/ressources/egyptian-verbs-101');
        $response->assertStatus(200);
        $response->assertSee('Verbes Égyptiens 101');
        $response->assertSee('Guide complet des verbes égyptiens en français.');
        $response->assertDontSee('content_fallback_banner');

        // SEO: self-canonicalizes to French URL
        $response->assertSee('<link rel="canonical" href="'.url('/fr/ressources/egyptian-verbs-101').'">', false);

        // SEO: hreflang emits en and fr, but omits absent de
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="fr"', false);
        $response->assertSee('hreflang="x-default"', false);
        $response->assertDontSee('hreflang="de"', false);
    }

    public function test_resource_detail_with_missing_translation_shows_fallback_banner_and_english_canonical(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Vocabulary Sheets',
            'slug' => 'vocab-sheets',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Cairo Street Phrases',
            'slug' => 'cairo-street-phrases',
            'short_description' => 'Essential phrases for everyday Cairo life.',
            'category_id' => $category->id,
            'is_active' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'file_path' => 'resources/dummy2.pdf',
            'file_type' => 'pdf',
        ]);

        // No German translation exists
        $response = $this->get('/de/ressourcen/cairo-street-phrases');
        $response->assertStatus(200);

        // Shows fallback banner in German
        $response->assertSee('Dieser Inhalt ist noch nicht auf Deutsch verfügbar. Die englische Version wird angezeigt.');
        $response->assertSee('Cairo Street Phrases');
        $response->assertSee('Essential phrases for everyday Cairo life.');

        // SEO: fallback must point canonical to English
        $response->assertSee('<link rel="canonical" href="'.url('/resources/cairo-street-phrases').'">', false);

        // SEO: fallback must emit ZERO hreflang alternates
        $response->assertDontSee('hreflang=', false);
    }

    public function test_english_page_omits_untranslated_locales_from_hreflang(): void
    {
        $category = ResourceCategory::create([
            'name' => 'General',
            'slug' => 'general',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'English Only Resource',
            'slug' => 'english-only-resource',
            'short_description' => 'Has no translations anywhere.',
            'category_id' => $category->id,
            'is_active' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'file_path' => 'resources/dummy3.pdf',
            'file_type' => 'pdf',
        ]);

        $response = $this->get('/resources/english-only-resource');
        $response->assertStatus(200);
        $response->assertSee('<link rel="canonical" href="'.url('/resources/english-only-resource').'">', false);

        // Only English is eligible, so fr and de must NOT be advertised in hreflang
        $response->assertDontSee('hreflang="fr"', false);
        $response->assertDontSee('hreflang="de"', false);
    }

    public function test_stale_translation_remains_visible_and_participates_in_hreflang(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Audio Guides',
            'slug' => 'audio-guides',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Pronunciation Guide Original',
            'slug' => 'pronunciation-guide',
            'short_description' => 'Original English description.',
            'category_id' => $category->id,
            'is_active' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'file_path' => 'resources/dummy4.pdf',
            'file_type' => 'pdf',
        ]);

        // Publish French translation
        $this->translationService->saveDraft($resource, 'fr', [
            'title' => 'Guide de prononciation',
            'short_description' => 'Description française originale.',
        ]);
        $this->translationService->publishTranslation($resource, 'fr');

        // Admin updates English source -> makes French stale
        $this->translationService->updateEnglishSource($resource, [
            'title' => 'Pronunciation Guide Revised Edition',
            'short_description' => 'Updated English description with new chapters.',
        ]);

        // Verify status is stale
        $frenchTranslation = $resource->translations()->where('locale', 'fr')->first();
        $this->assertEquals('stale', $frenchTranslation->status);

        // Request French page
        $response = $this->get('/fr/ressources/pronunciation-guide');
        $response->assertStatus(200);
        $response->assertSee('Guide de prononciation');
        $response->assertSee('Description française originale.');
        // Stale translations must NOT display fallback banner
        $response->assertDontSee('content_fallback_banner');
        // Must self-canonicalize
        $response->assertSee('<link rel="canonical" href="'.url('/fr/ressources/pronunciation-guide').'">', false);
        // Must participate in hreflang
        $response->assertSee('hreflang="fr"', false);
        $response->assertSee('hreflang="en"', false);
    }

    public function test_games_index_and_show_rendering_with_translations(): void
    {
        $game = Game::create([
            'title' => 'Cairo Numbers',
            'slug' => 'cairo-numbers',
            'description' => 'Learn Egyptian Arabic numbers 1 to 100.',
            'badge' => 'Numbers',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->translationService->saveDraft($game, 'de', [
            'title' => 'Kairo Zahlen',
            'description' => 'Lerne ägyptisch-arabische Zahlen von 1 bis 100.',
        ]);
        $this->translationService->publishTranslation($game, 'de');

        // German index
        $responseIndex = $this->get('/de/spiele');
        $responseIndex->assertStatus(200);
        $responseIndex->assertSee('Kairo Zahlen');
        $responseIndex->assertSee('Lerne ägyptisch-arabische Zahlen von 1 bis 100.');

        // German show
        $responseShow = $this->get('/de/spiele/cairo-numbers');
        $responseShow->assertStatus(200);
        $responseShow->assertSee('Kairo Zahlen');
        $responseShow->assertDontSee('content_fallback_banner');
        $responseShow->assertSee('<link rel="canonical" href="'.url('/de/spiele/cairo-numbers').'">', false);
    }

    public function test_faq_and_about_pages_render_localized_content(): void
    {
        $faq = Faq::create([
            'question' => 'How do online lessons work?',
            'answer' => 'Lessons take place over Zoom or Google Meet with shared notes.',
            'category' => 'General',
            'sort_order' => 1,
            'active' => true,
        ]);

        $this->translationService->saveDraft($faq, 'fr', [
            'question' => 'Comment fonctionnent les cours en ligne ?',
            'answer' => 'Les cours se déroulent via Zoom ou Google Meet avec des notes partagées.',
        ]);
        $this->translationService->publishTranslation($faq, 'fr');

        $responseFaq = $this->get('/fr/faq');
        $responseFaq->assertStatus(200);
        $responseFaq->assertSee('Comment fonctionnent les cours en ligne ?');
        $responseFaq->assertSee('Les cours se déroulent via Zoom ou Google Meet avec des notes partagées.');

        // About page
        $aboutPage = Page::create([
            'title' => 'About Ahmad',
            'slug' => 'about',
            'content' => 'Ahmad is a native Arabic tutor from Cairo.',
            'status' => 'published',
        ]);

        $this->translationService->saveDraft($aboutPage, 'de', [
            'title' => 'Über Ahmad',
            'content' => 'Ahmad ist ein muttersprachlicher Arabischlehrer aus Kairo.',
        ]);
        $this->translationService->publishTranslation($aboutPage, 'de');

        $responseAbout = $this->get('/de/ueber-uns');
        $responseAbout->assertStatus(200);
        $responseAbout->assertSee('Über Ahmad');
        $responseAbout->assertSee('Ahmad ist ein muttersprachlicher Arabischlehrer aus Kairo.');
    }

    public function test_booking_wizard_and_confirmation_render_in_french(): void
    {
        $sessionType = SessionType::create([
            'title' => 'Private Lesson',
            'slug' => 'private-lesson',
            'duration_minutes' => 60,
            'price' => 25.00,
            'currency' => 'USD',
            'active' => true,
        ]);

        $responseWizard = $this->get('/fr/reservation');
        $responseWizard->assertStatus(200);
        $responseWizard->assertSee('Affichage des horaires dans votre fuseau horaire :');
        $responseWizard->assertSee('Changer de fuseau horaire');
        $responseWizard->assertSee('Vos coordonnées');

        // Create a confirmed booking to inspect localized confirmation view
        $contact = Contact::create([
            'email' => 'pierre@example.fr',
            'name' => 'Pierre Dubois',
            'phone' => '+33 6 12 34 56 78',
        ]);

        $startUtc = CarbonImmutable::tomorrow('UTC')->setHour(14)->setMinute(0);
        $endUtc = $startUtc->addMinutes(60);

        $booking = Booking::create([
            'contact_id' => $contact->id,
            'session_type_id' => $sessionType->id,
            'start_at_utc' => $startUtc,
            'end_at_utc' => $endUtc,
            'business_timezone' => 'Africa/Cairo',
            'customer_timezone' => 'Europe/Paris',
            'business_local_date_at_booking' => $startUtc->setTimezone('Africa/Cairo')->toDateString(),
            'business_local_start_time_at_booking' => '16:00',
            'business_local_end_time_at_booking' => '17:00',
            'customer_local_date_at_booking' => $startUtc->setTimezone('Europe/Paris')->toDateString(),
            'customer_local_start_time_at_booking' => '15:00',
            'customer_local_end_time_at_booking' => '16:00',
            'business_utc_offset_at_booking' => '+02:00',
            'customer_utc_offset_at_booking' => '+01:00',
            'status' => 'confirmed',
            'idempotency_key' => (string) Str::uuid(),
            'confirmation_token' => Str::random(64),
        ]);

        $responseConf = $this->get('/fr/reservation/confirmation/'.$booking->confirmation_token);
        $responseConf->assertStatus(200);
        $responseConf->assertSee('Réservation confirmée');
        $responseConf->assertSee('Votre cours est programmé !');
        $responseConf->assertSee('Calendrier des leçons');
        $responseConf->assertSee('Votre heure locale');
        $responseConf->assertSee('Heure du tuteur (Le Caire)');
        $responseConf->assertSee('Ajouter au calendrier (.ics)');
        $responseConf->assertSee('Que se passe-t-il ensuite ?');
    }

    public function test_policy_pages_render_fallback_banner_and_english_canonical_with_zero_hreflang_when_requested_in_french_or_german(): void
    {
        // French terms
        $responseFrTerms = $this->get('/fr/conditions');
        $responseFrTerms->assertStatus(200);
        $responseFrTerms->assertSee(__('content_fallback_banner', [], 'fr'));
        $responseFrTerms->assertSee('<link rel="canonical" href="'.url('/terms').'">', false);
        $responseFrTerms->assertDontSee('hreflang=', false);
        $responseFrTerms->assertSee('lang="en" dir="ltr"', false);

        // German privacy
        $responseDePrivacy = $this->get('/de/datenschutz');
        $responseDePrivacy->assertStatus(200);
        $responseDePrivacy->assertSee(__('content_fallback_banner', [], 'de'));
        $responseDePrivacy->assertSee('<link rel="canonical" href="'.url('/privacy').'">', false);
        $responseDePrivacy->assertDontSee('hreflang=', false);
        $responseDePrivacy->assertSee('lang="en" dir="ltr"', false);
    }

    public function test_custom_page_supports_french_route_and_canonical_when_translated_and_falls_back_when_not(): void
    {
        $page = Page::create([
            'title' => 'Learning Methodology',
            'slug' => 'methodology',
            'content' => 'Structured immersion method explained in English.',
            'status' => 'published',
        ]);

        $this->translationService->saveDraft($page, 'fr', [
            'title' => 'Méthodologie d\'apprentissage',
            'content' => 'Méthode d\'immersion structurée expliquée en français.',
        ]);
        $this->translationService->publishTranslation($page, 'fr');

        // French custom page
        $responseFr = $this->get('/fr/p/methodology');
        $responseFr->assertStatus(200);
        $responseFr->assertSee('Méthodologie', false);
        $responseFr->assertSee('d\'immersion structurée', false);
        $responseFr->assertDontSee(__('content_fallback_banner', [], 'fr'));
        $responseFr->assertSee('<link rel="canonical" href="'.url('/fr/p/methodology').'">', false);
        $responseFr->assertSee('hreflang="fr"', false);
        $responseFr->assertSee('hreflang="en"', false);

        // German custom page (not translated)
        $responseDe = $this->get('/de/p/methodology');
        $responseDe->assertStatus(200);
        $responseDe->assertSee(__('content_fallback_banner', [], 'de'));
        $responseDe->assertSee('Structured immersion method explained in English.');
        $responseDe->assertSee('<link rel="canonical" href="'.url('/p/methodology').'">', false);
        $responseDe->assertDontSee('hreflang=', false);
    }

    public function test_mixed_faq_list_applies_per_item_fallback_tagging_and_excludes_partially_translated_locale_from_hreflang(): void
    {
        $faq1 = Faq::create([
            'question' => 'How long are lessons?',
            'answer' => 'Each lesson is 60 minutes.',
            'category' => 'General',
            'sort_order' => 1,
            'active' => true,
        ]);

        $faq2 = Faq::create([
            'question' => 'What platform is used?',
            'answer' => 'We use Zoom or Google Meet.',
            'category' => 'General',
            'sort_order' => 2,
            'active' => true,
        ]);

        // Translate only FAQ 1 into French
        $this->translationService->saveDraft($faq1, 'fr', [
            'question' => 'Combien de temps dure une leçon ?',
            'answer' => 'Chaque leçon dure 60 minutes.',
        ]);
        $this->translationService->publishTranslation($faq1, 'fr');

        $response = $this->get('/fr/faq');
        $response->assertStatus(200);

        // Global page is not marked as full fallback because at least one is translated
        $response->assertDontSee('content_fallback_banner');

        // FAQ 1 is translated
        $response->assertSee('Combien de temps dure une leçon ?');
        $response->assertSee('Chaque leçon dure 60 minutes.');

        // FAQ 2 is untranslated and has per-item fallback markup and badge
        $response->assertSee('What platform is used?');
        $response->assertSee('English Fallback');
        $response->assertSee('lang="en" dir="ltr"', false);

        // Because French is only partially translated (mixed), it is NOT advertised as an alternate hreflang
        $response->assertDontSee('hreflang="fr"', false);
    }

    public function test_navigation_and_gated_resource_request_preserves_locale(): void
    {
        $category = ResourceCategory::create([
            'name' => 'Cheat Sheets',
            'slug' => 'cheat-sheets',
            'sort_order' => 1,
            'active' => true,
        ]);

        $resource = Resource::create([
            'title' => 'Cairo Street Slang',
            'slug' => 'cairo-street-slang',
            'short_description' => 'Top 50 expressions.',
            'category_id' => $category->id,
            'is_active' => true,
            'status' => 'published',
            'published_at' => now()->subDay(),
            'file_path' => 'resources/slang.pdf',
            'file_type' => 'pdf',
        ]);

        // French home page links to French resource catalog and French games
        $homeFr = $this->get('/fr');
        $homeFr->assertStatus(200);
        $homeFr->assertSee('href="'.url('/fr/ressources').'"', false);
        $homeFr->assertSee('href="'.url('/fr/jeux').'"', false);
        $visitorCookie = $homeFr->getCookie('_va_visitor');
        $sessionCookie = $homeFr->getCookie('_va_session');
        $this->assertNotNull($visitorCookie);
        $this->assertNotNull($sessionCookie);

        // Gated resource request on French resource
        $postResponse = $this->withCredentials()->withCookies([
            '_va_visitor' => $visitorCookie->getValue(),
            '_va_session' => $sessionCookie->getValue(),
        ])->post('/fr/ressources/cairo-street-slang/request', [
            'email' => 'jean@example.fr',
            'name' => 'Jean Dupont',
        ]);

        // Must redirect to the French resource page, not English
        $postResponse->assertRedirect(url('/fr/ressources/cairo-street-slang'));
    }
}

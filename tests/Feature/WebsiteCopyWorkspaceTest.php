<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\WebsiteCopy;
use App\Domains\CMS\Services\WebsiteCopyService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class WebsiteCopyWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function copyFields(string $page = 'home', string $locale = 'en', array $changes = []): array
    {
        request()->attributes->remove('website_copy_documents');
        $copies = app(WebsiteCopyService::class);

        return ['page' => $page, 'locale' => $locale, 'values' => array_replace($copies->values($page, $locale, true), $changes), 'state' => $copies->state($page, $locale), 'action' => 'publish', 'reviewed_source' => '1'];
    }

    public function test_english_copy_publishes_without_a_hidden_translation_review_field(): void
    {
        $data = $this->copyFields(page: 'shared');
        unset($data['reviewed_source']);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']))
            ->post(route('admin.website-copy.update'), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(WebsiteCopy::query()->where('page', 'shared')->where('locale', 'en')->firstOrFail()->published_content);
    }

    public function test_translation_publication_still_requires_the_visible_source_review(): void
    {
        $data = $this->copyFields(locale: 'fr');
        unset($data['reviewed_source']);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']))
            ->post(route('admin.website-copy.update'), $data)->assertSessionHasErrors('reviewed_source');
        $this->assertDatabaseCount('website_copies', 0);
        $data['action'] = 'draft';
        $this->post(route('admin.website-copy.update'), $data)->assertSessionHasNoErrors();
    }

    private function settingsFields(array $changes = []): array
    {
        return array_replace(['site_name' => 'New name', 'business_timezone' => 'Africa/Cairo', 'default_language' => 'en', 'action' => 'publish',
            'announcement' => ['enabled' => '1', 'audience' => 'public', 'severity' => 'information', 'dismissible' => '1', 'message_en' => 'New announcement', 'message_fr' => '', 'message_de' => '', 'cta_label_en' => '', 'cta_label_fr' => '', 'cta_label_de' => '', 'cta_url' => '', 'start_at' => '', 'end_at' => '']], $changes);
    }

    public function test_workspace_is_the_single_editor_and_social_channels_contains_only_social_controls(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $this->get(route('admin.website-copy.index'))->assertOk()->assertSee('Website Copy &amp; Pages', false)->assertSee('Français')->assertSee('Deutsch')->assertSee('Preview saved draft')->assertSee('Revision history');
        $this->get(route('admin.content.index'))->assertOk()->assertSee('Social Channels')->assertDontSee('Custom Standalone Pages')->assertDontSee('Add Frequently Asked Question');
        $this->get(route('admin.pages.index'))->assertRedirect(route('admin.website-copy.index', ['tab' => 'pages']));
        $this->assertDatabaseCount('website_copies', 0);
    }

    public function test_saved_copy_draft_is_private_and_can_be_previewed_without_publication(): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $data = $this->copyFields(changes: ['home__0__title' => 'Private draft headline']);
        $data['action'] = 'draft';
        $this->actingAs($actor)->post(route('admin.website-copy.update'), $data)->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertDontSee('Private draft headline');
        $this->get(route('admin.website-copy.preview', ['page' => 'home', 'locale' => 'en']))->assertOk()->assertSee('Private draft headline')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertNull(WebsiteCopy::query()->first()->published_content);
        $this->assertSame(0, DB::table('analytics_events')->count());
    }

    public function test_rendered_editor_fields_can_round_trip_as_a_complete_private_draft(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $response = $this->get(route('admin.website-copy.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $values = [];
        foreach ($xpath->query('//form[@id="website-copy-editor"]//*[@name]') as $element) {
            $name = $element->getAttribute('name');
            if (! preg_match('/^values\[([^\]]+)\]$/', $name, $matches)) {
                continue;
            }
            if ($element->getAttribute('type') === 'checkbox' && ! $element->hasAttribute('checked')) {
                continue;
            }
            $values[$matches[1]] = $element->tagName === 'textarea' ? $element->textContent : $element->getAttribute('value');
        }
        $expected = $this->copyFields();
        $this->assertEqualsCanonicalizing(array_keys($expected['values']), array_keys($values));
        $this->assertSame($expected['values']['home__0__title'], $values['home__0__title']);
        $this->assertSame($expected['values']['home__0__blocks__0__text'], html_entity_decode($values['home__0__blocks__0__text']));
        $values['home__0__title'] = 'Round trip draft';
        $this->post(route('admin.website-copy.update'), array_replace($expected, ['values' => $values, 'action' => 'draft']))->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('Round trip draft');
        $this->get(route('admin.website-copy.index'))->assertSee('Round trip draft');
    }

    public function test_empty_translated_static_wording_uses_the_published_english_value(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $key = 'ui__'.sha1('Explore Lessons & Pricing');
        $this->post(route('admin.website-copy.update'), $this->copyFields(changes: [$key => 'English resource link']))->assertSessionHasNoErrors();
        $this->post(route('admin.website-copy.update'), $this->copyFields(locale: 'fr', changes: [$key => '']))->assertSessionHasNoErrors();
        $this->get('/fr')->assertOk()->assertSee('English resource link');
    }

    public function test_existing_about_translation_survives_an_english_copy_publication(): void
    {
        $page = Page::query()->create(['slug' => 'about', 'title' => 'About', 'content' => 'Original English biography', 'status' => 'published', 'published_at' => now()->subDay()]);
        $page->translations()->create(['locale' => 'fr', 'title' => 'À propos', 'content' => 'Biographie française existante', 'status' => 'published', 'published_at' => now()->subDay()]);
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $this->post(route('admin.website-copy.update'), $this->copyFields(page: 'about', changes: ['about__0__blocks__0__text' => 'Updated English biography']))->assertSessionHasNoErrors();
        $this->get('/fr/a-propos')->assertOk()->assertSee('Biographie française existante')->assertDontSee('Updated English biography');
        $this->get(route('admin.website-copy.index', ['page' => 'about', 'locale' => 'fr']))->assertOk()->assertViewHas('values', fn (array $values): bool => $values['about__0__blocks__0__text'] === 'Biographie française existante');
        $this->get(route('admin.website-copy.preview', ['page' => 'shared', 'locale' => 'en']))->assertOk();
    }

    #[TestWith(['fr', 'Une nouvelle introduction'])]
    #[TestWith(['de', 'Eine neue Einleitung'])]
    public function test_localized_copy_publishes_independently_and_english_is_preserved(string $locale, string $headline): void
    {
        $actor = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $this->actingAs($actor)->post(route('admin.website-copy.update'), $this->copyFields(changes: ['home__0__title' => 'Published English heading']))->assertSessionHasNoErrors();
        $this->post(route('admin.website-copy.update'), $this->copyFields(locale: $locale, changes: ['home__0__title' => $headline]))->assertSessionHasNoErrors();
        $this->get('/'.$locale)->assertOk()->assertSee($headline)->assertDontSee('Published English heading');
        $this->get('/')->assertSee('Published English heading')->assertDontSee($headline);
    }

    public function test_changed_source_refuses_a_stale_translation_form(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $old = $this->copyFields(locale: 'fr', changes: ['home__0__title' => 'Old translation']);
        $this->post(route('admin.website-copy.update'), $this->copyFields(changes: ['home__0__title' => 'New English source']))->assertSessionHasNoErrors();
        $this->post(route('admin.website-copy.update'), $old)->assertSessionHasErrors('state');
        $this->assertDatabaseMissing('website_copies', ['page' => 'home', 'locale' => 'fr']);
    }

    public function test_rich_text_is_sanitized_and_button_destinations_are_validated(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $data = $this->copyFields(changes: ['home__0__blocks__0__text' => '<p><strong>Formatted introduction</strong></p><script>alert(1)</script>', 'links__booking' => 'https://example.test/new-booking']);
        $this->post(route('admin.website-copy.update'), $data)->assertSessionHasNoErrors();
        $this->get('/')->assertSee('<strong>Formatted introduction</strong>', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('https://example.test/new-booking');
        $this->post(route('admin.website-copy.update'), $this->copyFields(changes: ['links__booking' => 'javascript:alert(1)']))->assertSessionHasErrors('values.links__booking');
    }

    public function test_section_visibility_and_order_apply_to_the_public_page(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $this->post(route('admin.website-copy.update'), $this->copyFields(changes: ['_sections__home__1__visible' => '0', '_sections__home__8__order' => '1', '_sections__home__0__order' => '9']))->assertSessionHasNoErrors();
        $this->get('/')->assertDontSee('Everyday Egyptian Arabic')->assertSeeInOrder(["Let's Find Your Way Forward in Arabic.", 'Learn Arabic with a plan built around you.'], false);
    }

    public function test_previous_revision_can_be_reviewed_and_restored_as_a_private_draft(): void
    {
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $this->post(route('admin.website-copy.update'), $this->copyFields(changes: ['home__0__title' => 'First edition']))->assertSessionHasNoErrors();
        $first = WebsiteCopy::query()->first()->revisions()->where('status', 'published')->first();
        $this->assertSame('First edition', $first->content['values']['home__0__title']);
        $this->post(route('admin.website-copy.update'), $this->copyFields(changes: ['home__0__title' => 'Second edition']))->assertSessionHasNoErrors();
        $this->get(route('admin.website-copy.index', ['revision' => $first->id]))->assertOk()->assertViewHas('values', fn (array $values): bool => $values['home__0__title'] === 'First edition')->assertSee('First edition')->assertSee('Reviewing revision');
        $restore = $this->copyFields(changes: $first->content['values']);
        $restore['action'] = 'draft';
        $this->post(route('admin.website-copy.update'), $restore)->assertSessionHasNoErrors();
        $this->get('/')->assertSee('Second edition')->assertDontSee('First edition');
    }

    public function test_settings_and_announcement_draft_never_change_live_values(): void
    {
        Setting::set('site_name', 'Current name');
        Setting::set('announcement.message_en', 'Current announcement', 'announcement');
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']));
        $this->post(route('admin.settings.update'), $this->settingsFields(['action' => 'draft']))->assertSessionHasNoErrors();
        $this->assertSame('Current name', Setting::get('site_name'));
        $this->assertSame('Current announcement', Setting::get('announcement.message_en'));
        $this->get(route('admin.settings.index'))->assertOk()->assertSee('New name')->assertSee('New announcement')->assertSee('Showing the saved settings draft');
        $this->post(route('admin.settings.update'), $this->settingsFields())->assertSessionHasNoErrors();
        $this->assertSame('New name', Setting::get('site_name'));
        $this->assertSame('New announcement', Setting::get('announcement.message_en'));
        $this->assertNull(Setting::get('admin_settings.draft'));
    }

    public function test_invalid_announcement_refuses_the_entire_settings_publication(): void
    {
        Setting::set('site_name', 'Original name');
        $data = $this->settingsFields();
        $data['announcement']['cta_url'] = 'javascript:alert(1)';
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']))->post(route('admin.settings.update'), $data)->assertSessionHasErrors('announcement.cta_url');
        $this->assertSame('Original name', Setting::get('site_name'));
        $this->assertNull(Setting::get('announcement.enabled'));
    }

    public function test_settings_have_one_form_and_no_duplicate_copy_fields(): void
    {
        $response = $this->actingAs(AdministratorFactory::new()->create(['role' => 'super_admin']))->get(route('admin.settings.index'))->assertOk()->assertDontSee('Homepage Copy &amp; Sections', false)->assertDontSee('Save Announcement');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@id="business-settings"]//select[@name="announcement[enabled]"]')->length);
        $this->assertSame(0, $xpath->query('//textarea[@name="hero_subtitle"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="counters_learning_hours_headline"]')->length);
    }

    public function test_guests_and_assistants_cannot_edit_or_preview_copy(): void
    {
        $this->get(route('admin.website-copy.index'))->assertRedirect(route('admin.login'));
        $this->actingAs(AdministratorFactory::new()->create(['role' => 'assistant']));
        $this->get(route('admin.website-copy.index'))->assertForbidden();
        $this->post(route('admin.website-copy.update'), $this->copyFields())->assertForbidden();
        $this->get(route('admin.website-copy.preview', ['page' => 'home', 'locale' => 'en']))->assertForbidden();
        $this->assertDatabaseCount('website_copies', 0);
    }
}

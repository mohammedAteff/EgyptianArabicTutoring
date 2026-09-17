<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\CMS\Models\Media;
use App\Domains\CMS\Models\Page;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CmsAndMediaWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Administrator $admin;

    protected ResourceCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Administrator::create([
            'name' => 'CMS Admin',
            'email' => 'admin@boltlanding.test',
            'password' => Hash::make('Password123!'),
            'role' => 'super_admin',
        ]);

        $this->category = ResourceCategory::create([
            'name' => 'Vocabulary Guides',
            'slug' => 'vocab',
            'active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_draft_page_never_leaks_publicly_to_unauthenticated_visitors(): void
    {
        $draftPage = Page::create([
            'title' => 'Secret Curriculum Draft',
            'slug' => 'secret-curriculum',
            'content' => 'Top secret lesson plan not yet published.',
            'status' => 'draft',
            'published_at' => null,
        ]);

        // 1. Regular public view must 404
        $this->get('/p/secret-curriculum')
            ->assertNotFound();

        // 2. Preview endpoint without authentication must be forbidden (403)
        $this->get('/p/secret-curriculum/preview')
            ->assertForbidden();
    }

    public function test_draft_resource_and_disabled_game_never_leak_publicly(): void
    {
        $draftResource = Resource::create([
            'title' => 'Unreleased Advanced Grammar',
            'slug' => 'advanced-grammar',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'draft',
            'published_at' => null,
            'is_gated' => true,
        ]);

        $disabledGame = Game::create([
            'title' => 'Secret Dialect Puzzle',
            'slug' => 'secret-puzzle',
            'status' => 'disabled',
            'sort_order' => 1,
        ]);

        // Public visitor cannot access draft resource
        $this->get('/resources/advanced-grammar')->assertNotFound();
        $this->get('/resources/advanced-grammar/preview')->assertForbidden();

        // Public visitor cannot access disabled game
        $this->get('/games/secret-puzzle')->assertNotFound();
        $this->get('/games/secret-puzzle/preview')->assertForbidden();
    }

    public function test_authorized_admin_can_preview_draft_page_resource_and_game(): void
    {
        $draftPage = Page::create([
            'title' => 'Secret Curriculum Draft',
            'slug' => 'secret-curriculum',
            'content' => 'Top secret lesson plan not yet published.',
            'status' => 'draft',
        ]);

        $draftResource = Resource::create([
            'title' => 'Unreleased Advanced Grammar',
            'slug' => 'advanced-grammar',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'draft',
            'is_gated' => true,
        ]);

        $disabledGame = Game::create([
            'title' => 'Secret Dialect Puzzle',
            'slug' => 'secret-puzzle',
            'status' => 'disabled',
            'sort_order' => 1,
        ]);

        // Authenticated admin can preview all drafts
        $this->actingAs($this->admin, 'web')
            ->get('/p/secret-curriculum/preview')
            ->assertOk()
            ->assertSee('Top secret lesson plan not yet published.')
            ->assertSee('Draft Preview Mode');

        $this->actingAs($this->admin, 'web')
            ->get('/resources/advanced-grammar/preview')
            ->assertOk()
            ->assertSee('Unreleased Advanced Grammar')
            ->assertSee('Draft Preview Mode');

        $this->actingAs($this->admin, 'web')
            ->get('/games/secret-puzzle/preview')
            ->assertOk()
            ->assertSee('Secret Dialect Puzzle')
            ->assertSee('Draft Preview Mode');
    }

    public function test_valid_signed_preview_url_is_denied_to_unauthenticated_guests(): void
    {
        $draftPage = Page::create([
            'title' => 'Client Review Document',
            'slug' => 'client-review',
            'content' => 'Confidential feedback draft for external auditor.',
            'status' => 'draft',
        ]);

        // Generate genuine signed URL
        $validSignedUrl = URL::temporarySignedRoute('pages.preview', now()->addHour(), ['slug' => $draftPage->slug]);

        // 1. Valid signed URL MUST be denied to unauthenticated guests (never leak unpublished content)
        $this->get($validSignedUrl)->assertForbidden();

        // 2. Tampered signature is also denied
        $tamperedUrl = $validSignedUrl.'&tampered=1';
        $this->get($tamperedUrl)->assertForbidden();

        // 3. Forged signature parameter is also denied
        $forgedUrl = url('/p/client-review/preview?signature=forged_fake_signature');
        $this->get($forgedUrl)->assertForbidden();

        // 4. Authenticated admin accessing the preview URL succeeds
        $this->actingAs($this->admin, 'web')
            ->get($validSignedUrl)
            ->assertOk()
            ->assertSee('Confidential feedback draft for external auditor.')
            ->assertSee('Draft Preview Mode');
    }

    public function test_published_version_stays_public_during_draft_edits_and_explicit_publish_switches_versions(): void
    {
        // 1. Create a published page
        $page = Page::create([
            'title' => 'Original Travel Guide',
            'slug' => 'travel-guide',
            'content' => 'Published V1: Original public text.',
            'excerpt' => 'V1 Excerpt',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Public visitor sees published V1 text
        $this->get('/p/travel-guide')
            ->assertOk()
            ->assertSee('Published V1: Original public text.');

        // 2. Admin edits page and saves as draft (action=draft)
        $this->actingAs($this->admin, 'web')
            ->put(route('admin.pages.update', $page->id), [
                'action' => 'draft',
                'title' => 'Draft New Title',
                'slug' => 'travel-guide',
                'content' => 'Unpublished V2: Draft revisions in progress.',
                'excerpt' => 'Draft V2 Excerpt',
                'status' => 'draft',
            ])
            ->assertRedirect();

        // Database live page MUST remain published with original content!
        $page->refresh();
        $this->assertEquals('published', $page->status);
        $this->assertEquals('Original Travel Guide', $page->title);
        $this->assertEquals('Published V1: Original public text.', $page->content);

        // Public visitor STILL sees Published V1 text (no leak of draft)
        $this->get('/p/travel-guide')
            ->assertOk()
            ->assertSee('Published V1: Original public text.')
            ->assertDontSee('Unpublished V2: Draft revisions in progress.');

        // Authenticated admin preview displays the draft content
        $this->actingAs($this->admin, 'web')
            ->get('/p/travel-guide/preview')
            ->assertOk()
            ->assertSee('Draft New Title')
            ->assertSee('Unpublished V2: Draft revisions in progress.')
            ->assertSee('Draft Preview Mode');

        // Unauthenticated visitor is denied from preview
        Auth::logout();
        $this->flushSession();
        $this->get('/p/travel-guide/preview')->assertForbidden();

        // 3. Admin explicitly publishes the changes (action=publish)
        $this->actingAs($this->admin, 'web')
            ->put(route('admin.pages.update', $page->id), [
                'action' => 'publish',
                'title' => 'Official Travel Guide V2',
                'slug' => 'travel-guide',
                'content' => 'Published V2: Officially launched revision.',
                'excerpt' => 'V2 Excerpt',
                'status' => 'published',
            ])
            ->assertRedirect();

        // Now public visitor sees the updated published content!
        $this->get('/p/travel-guide')
            ->assertOk()
            ->assertSee('Official Travel Guide V2')
            ->assertSee('Published V2: Officially launched revision.');
    }

    public function test_home_and_about_support_draft_before_publish_workflow(): void
    {
        // 1. Admin saves draft for homepage and about
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.settings.update'), [
                'action' => 'draft',
                'site_name' => 'Egyptian Arabic Academy',
                'business_timezone' => 'Africa/Cairo',
                'default_language' => 'en',
                'hero_title' => 'Secret Draft Hero Headline',
                'hero_subtitle' => 'Draft subtitle for review only.',
                'about_biography' => 'Draft Biography text for Ahmed.',
                'about_philosophy' => 'Draft Philosophy text.',
                'cancellation_policy' => '24 hours notice required.',
                'rescheduling_policy' => 'Rescheduling is free.',
                'booking_instructions' => 'Pick a time.',
            ])
            ->assertRedirect();

        // 2. Public homepage and about MUST NOT display the draft copy
        $this->get('/')
            ->assertOk()
            ->assertDontSee('Secret Draft Hero Headline');

        $this->get('/about')
            ->assertOk()
            ->assertDontSee('Draft Biography text for Ahmed.');

        // 3. Unauthenticated visitors are rejected from preview
        Auth::logout();
        $this->flushSession();
        $this->get('/preview/home')->assertForbidden();
        $this->get('/about/preview')->assertForbidden();

        // 4. Authenticated admin preview displays the draft copy
        $this->actingAs($this->admin, 'web')
            ->get('/preview/home')
            ->assertOk()
            ->assertSee('Secret Draft Hero Headline')
            ->assertSee('Draft Preview Mode');

        $this->actingAs($this->admin, 'web')
            ->get('/about/preview')
            ->assertOk()
            ->assertSee('Draft Biography text for Ahmed.')
            ->assertSee('Draft Preview Mode');

        // 5. Admin publishes settings (action=publish)
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.settings.update'), [
                'action' => 'publish',
                'site_name' => 'Egyptian Arabic Academy',
                'business_timezone' => 'Africa/Cairo',
                'default_language' => 'en',
                'hero_title' => 'Official Launched Hero Headline',
                'hero_subtitle' => 'Official launched subtitle.',
                'about_biography' => 'Official Launched Biography text.',
                'about_philosophy' => 'Official Philosophy.',
                'cancellation_policy' => '24 hours notice required.',
                'rescheduling_policy' => 'Rescheduling is free.',
                'booking_instructions' => 'Pick a time.',
            ])
            ->assertRedirect();

        // Now public pages render the official published settings
        $this->get('/')
            ->assertOk()
            ->assertSee('Official Launched Hero Headline');

        $this->get('/about')
            ->assertOk()
            ->assertSee('Official Launched Biography text.');
    }

    public function test_admin_forms_contain_media_picker_trigger(): void
    {
        // Check Page Edit
        $page = Page::create([
            'title' => 'Picker Form Test',
            'slug' => 'picker-form-test',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin, 'web')
            ->get(route('admin.pages.edit', $page->id))
            ->assertOk()
            ->assertSee('openMediaPicker')
            ->assertSee('Media Library Asset Picker');

        // Check Resource Edit
        $resource = Resource::create([
            'title' => 'Resource Form Test',
            'slug' => 'resource-form-test',
            'category_id' => $this->category->id,
            'file_type' => 'pdf',
            'status' => 'draft',
        ]);

        $this->actingAs($this->admin, 'web')
            ->get(route('admin.resources.edit', $resource->id))
            ->assertOk()
            ->assertSee('openMediaPicker')
            ->assertSee('Media Library Asset Picker');

        // Check Settings
        $this->actingAs($this->admin, 'web')
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('openMediaPicker')
            ->assertSee('Preview Homepage')
            ->assertSee('Preview About');
    }

    public function test_page_publish_and_rollback_restores_exact_prior_revision(): void
    {
        // 1. Admin creates page
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.pages.store'), [
                'title' => 'Cairo Travel Prep',
                'slug' => 'cairo-travel-prep',
                'content' => 'Version 1: Basic street vocabulary for tourists.',
                'excerpt' => 'V1 Excerpt',
                'status' => 'published',
                'og_image_path' => 'media/cairo-v1.webp',
            ])
            ->assertRedirect();

        $page = Page::where('slug', 'cairo-travel-prep')->firstOrFail();
        $this->assertEquals(1, $page->revisions()->count());
        $rev1 = $page->revisions()->firstOrFail();

        // 2. Admin edits page to Version 2
        $this->actingAs($this->admin, 'web')
            ->put(route('admin.pages.update', $page->id), [
                'title' => 'Cairo Travel Prep (Updated)',
                'slug' => 'cairo-travel-prep',
                'content' => 'Version 2: Expanded market bargaining tips.',
                'excerpt' => 'V2 Excerpt',
                'status' => 'published',
                'og_image_path' => 'media/cairo-v2.webp',
            ])
            ->assertRedirect();

        $this->assertEquals('Version 2: Expanded market bargaining tips.', $page->fresh()->content);
        $this->assertEquals(2, $page->revisions()->count());

        // 3. Admin rolls back to revision #1
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.pages.revisions.restore', ['page' => $page->id, 'revision' => $rev1->id]))
            ->assertRedirect();

        $page->refresh();
        $this->assertEquals('Cairo Travel Prep', $page->title);
        $this->assertEquals('Version 1: Basic street vocabulary for tourists.', $page->content);
        $this->assertEquals('V1 Excerpt', $page->excerpt);
        $this->assertEquals('media/cairo-v1.webp', $page->og_image_path);
    }

    public function test_home_and_about_cms_edits_render_on_public_pages(): void
    {
        // 1. Update homepage settings
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.settings.update'), [
                'site_name' => 'Custom Arabic Academy',
                'business_timezone' => 'Africa/Cairo',
                'default_language' => 'en',
                'hero_title' => 'Custom Fluency in 30 Days',
                'hero_subtitle' => 'Exclusive Egyptian Arabic coaching in Zamalek.',
                'home_approach_title' => 'Why Our Cairo Method Works',
                'home_resources_title' => 'Download Premium Curriculums',
                'home_cta_title' => 'Claim Your Private Consultation Now',
                'site_footer_text' => 'Custom footer notice for students worldwide.',
                'cancellation_policy' => '24 hours notice required.',
                'rescheduling_policy' => 'Rescheduling is free.',
                'booking_instructions' => 'Pick a time.',
            ])
            ->assertRedirect();

        // Create a published resource so the featured resources section is displayed
        Resource::create([
            'category_id' => $this->category->id,
            'title' => 'Cairo Street Vocab',
            'slug' => 'cairo-street-vocab',
            'description' => 'Daily phrases in Cairo Egyptian Arabic.',
            'file_path' => 'resources/cairo.pdf',
            'file_name' => 'cairo.pdf',
            'file_size_bytes' => 2048,
            'mime_type' => 'application/pdf',
            'status' => 'published',
            'published_at' => now(),
        ]);

        // Guest visits homepage and sees the custom CMS copy
        $this->get('/')
            ->assertOk()
            ->assertSee('Custom Fluency in 30 Days')
            ->assertSee('Exclusive Egyptian Arabic coaching in Zamalek.')
            ->assertSee('Why Our Cairo Method Works')
            ->assertSee('Download Premium Curriculums')
            ->assertSee('Claim Your Private Consultation Now')
            ->assertSee('Custom footer notice for students worldwide.');

        // 2. Update About page via Page CMS
        $aboutPage = Page::create([
            'title' => 'About Master Ahmad',
            'slug' => 'about',
            'content' => 'Custom Ahmad Story: Born in Giza with 15 years teaching foreigners.',
            'status' => 'published',
            'og_image_path' => 'media/ahmad-portrait.webp',
            'published_at' => now(),
        ]);

        // Guest visits /about and sees the custom bio
        $this->get('/about')
            ->assertOk()
            ->assertSee('Custom Ahmad Story: Born in Giza with 15 years teaching foreigners.');
    }

    public function test_media_can_be_selected_and_associated_with_entities(): void
    {
        Storage::fake('public');

        // 1. Upload media asset
        $uploaded = UploadedFile::fake()->image('pyramid-guide.webp', 800, 600);
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.media.store'), [
                'file' => $uploaded,
                'alt_text' => 'Pyramid Guide Cover',
            ])
            ->assertRedirect();

        $media = Media::where('filename', 'pyramid-guide.webp')->firstOrFail();

        // 2. Create resource with cover_image_path pointing to media
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.resources.store'), [
                'title' => 'Pyramid Vocabulary Pack',
                'slug' => 'pyramid-vocab',
                'category_id' => $this->category->id,
                'file_type' => 'pdf',
                'status' => 'published',
                'cover_image_path' => $media->path,
            ])
            ->assertRedirect();

        $resource = Resource::where('slug', 'pyramid-vocab')->firstOrFail();
        $this->assertEquals($media->path, $resource->cover_image_path);

        // 3. Public index and show display the cover
        $this->get('/resources')
            ->assertOk()
            ->assertSee($media->path);

        $this->get('/resources/pyramid-vocab')
            ->assertOk()
            ->assertSee($media->path);
    }

    public function test_referenced_media_cannot_be_silently_deleted(): void
    {
        Storage::fake('public');

        $uploaded = UploadedFile::fake()->image('in-use-banner.png', 400, 300);
        $this->actingAs($this->admin, 'web')
            ->post(route('admin.media.store'), [
                'file' => $uploaded,
            ]);

        $media = Media::where('filename', 'in-use-banner.png')->firstOrFail();

        // Reference the media on a Page
        $page = Page::create([
            'title' => 'Page Using Media',
            'slug' => 'page-using-media',
            'content' => 'Some content',
            'status' => 'published',
            'og_image_path' => $media->path,
            'published_at' => now(),
        ]);

        // Attempt deletion while referenced
        $deleteRes = $this->actingAs($this->admin, 'web')
            ->delete(route('admin.media.destroy', $media->id));

        $deleteRes->assertRedirect();
        $deleteRes->assertSessionHas('error');

        // Asset MUST remain in DB and on disk
        $this->assertDatabaseHas('media', ['id' => $media->id]);
        Storage::disk('public')->assertExists($media->path);

        // Remove reference
        $page->update(['og_image_path' => null]);

        // Now deletion should succeed
        $deleteSuccess = $this->actingAs($this->admin, 'web')
            ->delete(route('admin.media.destroy', $media->id));

        $deleteSuccess->assertRedirect();
        $this->assertDatabaseMissing('media', ['id' => $media->id]);
        Storage::disk('public')->assertMissing($media->path);
    }

    public function test_media_picker_endpoint_returns_json_asset_list(): void
    {
        Storage::fake('public');

        Media::create([
            'filename' => 'picker-test.webp',
            'disk' => 'public',
            'path' => 'media/picker-test.webp',
            'mime_type' => 'image/webp',
            'file_size' => 12345,
            'alt_text' => 'Picker Test',
        ]);

        $res = $this->actingAs($this->admin, 'web')
            ->get(route('admin.media.picker'));

        $res->assertOk()
            ->assertJsonFragment([
                'filename' => 'picker-test.webp',
                'path' => 'media/picker-test.webp',
            ]);
    }
}

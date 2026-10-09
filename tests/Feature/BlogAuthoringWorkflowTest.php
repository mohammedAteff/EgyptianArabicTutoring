<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Blog;
use App\Domains\CMS\Models\BlogCategory;
use App\Domains\CMS\Models\Media;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\BlogImageService;
use App\Domains\CMS\Services\BlogService;
use App\Domains\CMS\Services\RichTextSanitizer;
use Database\Factories\AdministratorFactory;
use Database\Factories\BlogFactory;
use Database\Factories\ResourceFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BlogAuthoringWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function data(array $overrides = []): array
    {
        return $overrides + ['title' => 'Learning guide', 'slug' => 'learning-guide', 'excerpt' => 'An authentic article summary.', 'body' => '<h2>Learn</h2><p dir="rtl" lang="ar">أهلاً وسهلاً</p>', 'status' => 'draft', 'locale' => 'en', 'lock_version' => 1];
    }

    public function test_incomplete_drafts_can_be_saved_but_cannot_be_published(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.blog.store'), $this->data(['title' => '', 'excerpt' => '', 'body' => '', 'slug' => '']))
            ->assertRedirect()->assertSessionHasNoErrors();
        $blog = Blog::query()->sole();
        $this->get($blog->publicUrl())->assertNotFound();
        $this->put(route('admin.blog.update', $blog), $this->data(['title' => '', 'excerpt' => '', 'body' => '', 'slug' => $blog->slug, 'status' => 'published']))->assertSessionHasErrors('title');
        $this->assertSame('draft', $blog->fresh()->status);
        $this->get(route('sitemap'))->assertDontSee($blog->slug);
    }

    public function test_owner_can_publish_update_preview_unpublish_and_archive_without_deleting_history(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.blog.store'), $this->data())->assertSessionHasNoErrors();
        $blog = Blog::query()->sole();
        $this->get(route('admin.blog.preview', $blog))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertSee('noindex', false);
        $this->put(route('admin.blog.update', $blog), $this->data(['status' => 'published']))->assertSessionHasNoErrors();
        $blog->refresh();
        $firstPublication = $blog->published_at->toDateTimeString();
        $this->get($blog->publicUrl())->assertOk()->assertSee('Written by: Abdallah');
        $this->put(route('admin.blog.update', $blog), $this->data(['lock_version' => 2, 'status' => 'published', 'body' => '<p>Revised explanation.</p>']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('blog_revisions', 1);
        $this->assertNotNull($blog->fresh()->editorial_updated_at);
        $this->put(route('admin.blog.update', $blog), $this->data(['lock_version' => 3, 'status' => 'draft', 'body' => '<p>Revised explanation.</p>']))->assertSessionHasNoErrors();
        $this->get($blog->publicUrl())->assertNotFound();
        $this->get(route('sitemap'))->assertDontSee('/blog/'.$blog->slug);
        $this->delete(route('admin.blog.destroy', $blog), ['lock_version' => 4])->assertSessionHasNoErrors();
        $this->assertSame('archived', $blog->fresh()->status);
        $this->assertSame($firstPublication, $blog->fresh()->published_at->toDateTimeString());
        $this->assertDatabaseCount('blogs', 1);
        $this->assertDatabaseCount('blog_revisions', 3);
    }

    public function test_preview_and_write_actions_require_an_authorized_publisher(): void
    {
        $blog = BlogFactory::new()->create();
        $this->get(route('admin.blog.preview', $blog))->assertRedirect();
        $assistant = AdministratorFactory::new()->create(['role' => 'assistant']);
        $this->actingAs($assistant)->get(route('admin.blog.preview', $blog))->assertForbidden();
        $this->post(route('admin.blog.store'), $this->data(['status' => 'published']))->assertForbidden();
        $this->assertDatabaseCount('blogs', 1);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.blog.preview', $blog))->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_unchanged_legacy_html_is_preserved_byte_for_byte_on_a_metadata_save(): void
    {
        $body = '<table class="legacy-lesson"><tbody><tr><td dir="rtl">اللغة العربية</td></tr></tbody></table><!-- editorial spacing -->';
        $blog = BlogFactory::new()->create(['body' => $body, 'status' => 'published', 'published_at' => now()->subDay(), 'lock_version' => 1, 'translation_group_id' => (string) Str::uuid()]);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $saved = app(BlogService::class)->update($blog, ['seo_title' => 'Clearer search title', 'body' => $body], 1, $admin->id);
        $this->assertSame($body, $saved->body);
        $this->assertNull($saved->editorial_updated_at);
        $this->assertSame($body, $saved->revisions()->sole()->snapshot['body']);
        $this->get($saved->publicUrl())->assertOk()->assertSee('اللغة العربية')->assertSee('<table', false);
    }

    public function test_arabic_direction_and_source_tables_survive_sanitizing_without_active_html(): void
    {
        $clean = app(RichTextSanitizer::class)->sanitizeBlog('<p dir="rtl" lang="ar">العربية <bdi dir="rtl">أهلاً</bdi></p><table><tr><td>قاهرة</td></tr></table><script>alert(1)</script><a href="javascript:alert(1)">link</a>');
        $this->assertStringContainsString('dir="rtl"', $clean);
        $this->assertStringContainsString('lang="ar"', $clean);
        $this->assertStringContainsString('<table>', $clean);
        $this->assertStringNotContainsString('<script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $blog = new Blog(['body' => '<p>'.str_repeat('العربية ', 400).'</p>']);
        $this->assertSame(2, $blog->readingMinutes());
    }

    public function test_public_locale_links_offer_only_actual_published_versions_and_missing_versions_are_honest(): void
    {
        $group = (string) Str::uuid();
        $en = BlogFactory::new()->create(['slug' => 'english-explanation', 'locale' => 'en', 'translation_group_id' => $group, 'status' => 'published', 'published_at' => now()->subDay()]);
        $fr = BlogFactory::new()->create(['slug' => 'explication-francaise', 'locale' => 'fr', 'translation_group_id' => $group, 'status' => 'published', 'published_at' => now()->subDay()]);
        BlogFactory::new()->create(['slug' => 'german-private-draft', 'locale' => 'de', 'translation_group_id' => $group]);
        $this->get($en->publicUrl())->assertOk()->assertSee('/fr/blog/explication-francaise')->assertDontSee('hreflang="de"', false)->assertDontSee('german-private-draft');
        $this->get('/fr/blog/'.$en->slug)->assertStatus(301)->assertRedirect($fr->publicUrl());
        $this->get($fr->publicUrl())->assertOk()->assertSee('<html lang="fr"', false)->assertSee('hreflang="en"', false);
        $this->get('/de/blog/'.$en->slug)->assertNotFound()->assertSee('This article hasn', false)->assertSee($en->slug);
        $this->get('/sitemap.xml')->assertOk()->assertSee($en->slug)->assertSee($fr->slug)->assertDontSee('german-private-draft');
    }

    public function test_null_legacy_translation_groups_do_not_link_unrelated_articles(): void
    {
        $en = BlogFactory::new()->create(['locale' => 'en', 'status' => 'published', 'published_at' => now()->subDay(), 'translation_group_id' => null]);
        $fr = BlogFactory::new()->create(['locale' => 'fr', 'status' => 'published', 'published_at' => now()->subDay(), 'translation_group_id' => null]);
        $this->get($en->publicUrl())->assertOk()->assertDontSee($fr->slug);
        $this->get('/fr/blog/'.$en->slug)->assertNotFound()->assertDontSee($fr->slug);
    }

    public function test_explicit_translation_creation_groups_a_legacy_source_without_generating_content(): void
    {
        $source = BlogFactory::new()->create(['translation_group_id' => null]);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->post(route('admin.blog.store'), $this->data(['translation_of' => $source->id, 'locale' => 'fr', 'title' => '', 'excerpt' => '', 'body' => '', 'slug' => '']))
            ->assertSessionHasNoErrors();
        $translation = Blog::where('locale', 'fr')->sole();
        $this->assertSame($source->fresh()->translation_group_id, $translation->translation_group_id);
        $this->assertSame('', $translation->body);
        $this->assertSame('draft', $translation->status);
        $this->post(route('admin.blog.store'), $this->data(['translation_of' => $source->id, 'locale' => 'fr', 'slug' => 'duplicate-fr']))->assertSessionHasErrors('locale');
    }

    public function test_public_search_category_sort_and_featured_selection_use_real_published_content(): void
    {
        $first = BlogCategory::factory()->create(['name' => 'Learning Arabic', 'slug' => 'learning']);
        $second = BlogCategory::factory()->create(['name' => 'Culture', 'slug' => 'culture']);
        $old = BlogFactory::new()->create(['title' => 'Old Arabic guide', 'slug' => 'old-guide', 'category_id' => $first->id, 'status' => 'published', 'published_at' => now()->subDays(3)]);
        $new = BlogFactory::new()->create(['title' => 'New Arabic guide', 'slug' => 'new-guide', 'category_id' => $first->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        BlogFactory::new()->create(['title' => 'Culture guide', 'category_id' => $second->id, 'status' => 'published', 'published_at' => now()->subDay()]);
        BlogFactory::new()->create(['title' => 'Private Arabic draft', 'category_id' => $first->id]);
        Setting::set('blog.featured.en', (string) $new->id);
        $this->get('/blog?q=Arabic&category=learning&sort=oldest')->assertOk()->assertSeeInOrder([$old->title, $new->title])->assertDontSee('Culture guide')->assertDontSee('Private Arabic draft');
        $this->get('/blog')->assertSee('Featured Article')->assertSee($new->title);
        $new->update(['status' => 'draft']);
        $this->get('/blog')->assertDontSee('Featured Article')->assertDontSee($new->title);
        $this->get('/blog?q=unmatched')->assertSee('No Articles Found');
    }

    public function test_media_upload_and_picker_exclude_non_images_and_private_assets_from_articles(): void
    {
        Storage::fake('public');
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('document.pdf', 5, 'application/pdf'), 'images_only' => 1])->assertUnprocessable();
        $response = $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('arabic.png'), 'images_only' => 1, 'alt_text' => 'Arabic example'])->assertCreated();
        $this->assertNotNull(app(BlogImageService::class)->url($response->json('path')));
        Media::create(['disk' => 'private', 'path' => 'secret.png', 'filename' => 'secret.png', 'mime_type' => 'image/png', 'file_size' => 10]);
        Media::create(['disk' => 'public', 'path' => 'document.pdf', 'filename' => 'document.pdf', 'mime_type' => 'application/pdf', 'file_size' => 10]);
        $this->getJson(route('admin.media.picker', ['images_only' => 1]))->assertOk()->assertJsonCount(1)->assertJsonPath('0.alt_text', 'Arabic example');
        $this->expectException(ValidationException::class);
        app(BlogService::class)->create($this->data(['featured_image_path' => 'document.pdf']), $admin->id);
    }

    public function test_inline_images_in_live_content_or_revisions_prevent_media_deletion(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('lesson.png')->store('media', 'public');
        $media = Media::create(['disk' => 'public', 'path' => $path, 'filename' => 'lesson.png', 'mime_type' => 'image/png', 'file_size' => 100]);
        $blog = BlogFactory::new()->create(['body' => '<p><img src="'.Storage::disk('public')->url($path).'" alt="Lesson"></p>']);
        $this->assertTrue($media->isReferenced());
        $blog->revisions()->create(['snapshot' => ['body' => $blog->body], 'created_at' => now()]);
        $blog->update(['body' => '<p>Removed image from current body.</p>']);
        $this->assertTrue($media->fresh()->isReferenced());
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->delete(route('admin.media.destroy', $media))->assertSessionHas('error');
        Storage::disk('public')->assertExists($path);
    }

    public function test_article_seo_is_specific_safe_and_consistent_and_related_drafts_do_not_leak(): void
    {
        $blog = BlogFactory::new()->create(['status' => 'published', 'published_at' => now()->subDay(), 'seo_title' => 'Article search title', 'seo_description' => 'Article search summary', 'canonical_url' => 'https://example.com/source-guide', 'body' => '<p dir="rtl">أهلاً</p><img src="/storage/missing.png" alt="Missing">']);
        $related = BlogFactory::new()->create(['status' => 'draft', 'title' => 'Private related draft']);
        $hidden = ResourceFactory::new()->create(['status' => 'published', 'published_at' => now()->subDay(), 'public_catalogue' => false, 'title' => 'Hidden related resource']);
        $blog->relatedArticles()->attach($related);
        $blog->relatedResources()->attach($hidden);
        $response = $this->get($blog->publicUrl())->assertOk();
        $response->assertSee('<title>Article search title', false)->assertSee('property="og:type" content="article"', false)->assertSee('property="og:url" content="https://example.com/source-guide"', false)->assertSee('application/ld+json', false)->assertSee('"name":"Abdallah"', false)->assertSee('dir="rtl"', false)->assertDontSee('missing.png')->assertDontSee($related->title)->assertDontSee($hidden->title);
        $this->get('/sitemap.xml')->assertDontSee('/blog/'.$blog->slug);
    }

    public function test_authoring_screen_exposes_complex_source_without_publishing_or_generating_translations(): void
    {
        $body = '<h2>Arabic notes</h2><p dir="rtl" lang="ar">أهلاً وسهلاً — العربية</p><table class="legacy-table"><tbody><tr><td>قاهرة · Qāhira</td></tr></tbody></table><!-- preserve spacing -->';
        $blog = BlogFactory::new()->create(['title' => 'Synthetic editor review', 'body' => $body]);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.blog.edit', $blog))
            ->assertOk()->assertSeeText('HTML Source')->assertSeeText('Article Language')
            ->assertSeeText('Save Draft')->assertSeeText('Publish Article')->assertSeeText('Convert to Visual Format')
            ->assertSee(e($body), false);
        $this->assertSame($body, $blog->fresh()->body);
        $this->assertSame('draft', $blog->fresh()->status);
        $this->assertSame(1, Blog::count());

        $output = getenv('BLOG_UI_REVIEW_DIR');
        if (is_string($output) && is_dir($output) && app()->environment('testing')) {
            $html = str_replace(config('app.url'), getenv('BLOG_UI_REVIEW_BASE_URL') ?: config('app.url'), $response->getContent());
            $html = str_replace('</head>', '<script>document.addEventListener("submit", event => event.preventDefault(), true);</script></head>', $html);
            file_put_contents($output.'/codex-blog-editor-review.html', $html);
        }
    }
}

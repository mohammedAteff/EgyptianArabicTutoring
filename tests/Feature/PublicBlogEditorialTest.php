<?php

namespace Tests\Feature;

use App\Domains\CMS\Models\Blog;
use App\Domains\CMS\Models\BlogCategory;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Models\WebsiteCopy;
use Database\Factories\AdministratorFactory;
use Database\Factories\BlogFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicBlogEditorialTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed> $attributes */
    private function article(array $attributes = []): Blog
    {
        return BlogFactory::new()->create($attributes + ['status' => 'published', 'published_at' => now()->subDay()]);
    }

    public function test_default_lead_is_removed_before_pagination_on_every_page_and_explicit_page_one(): void
    {
        $articles = BlogFactory::new()->count(15)->create(['status' => 'published', 'published_at' => now()->subDay()]);
        $lead = $articles->last();
        $first = $this->get('/blog')->assertOk();
        $this->assertSame($lead->id, $first['lead']->id);
        $this->assertNull($first['featured']);
        $this->assertCount(12, $first['blogs']);
        $this->assertSame(14, $first['blogs']->total());
        $second = $this->get('/blog?page=2')->assertOk();
        $this->assertNull($second['lead']);
        $this->assertCount(2, $second['blogs']);
        $seen = [$lead->id, ...$first['blogs']->pluck('id'), ...$second['blogs']->pluck('id')];
        $this->assertCount(15, array_unique($seen));
        $this->assertEqualsCanonicalizing($articles->pluck('id')->all(), $seen);
        $this->assertSame($lead->id, $this->get('/blog?page=1')->assertOk()['lead']->id);
        $this->assertSame($articles->pluck('id')->reverse()->values()->all(), $seen);
    }

    public function test_configured_feature_must_be_published_due_and_in_the_content_locale(): void
    {
        $older = $this->article(['published_at' => now()->subDays(3)]);
        $newest = $this->article();
        Setting::set('blog.featured.en', (string) $older->id);
        $featured = $this->get('/blog')->assertOk();
        $this->assertSame($older->id, $featured['featured']->id);
        $this->assertSame([$newest->id], $featured['blogs']->pluck('id')->all());
        foreach ([['status' => 'draft'], ['published_at' => now()->addDay()], ['locale' => 'fr'], ['status' => 'archived']] as $invalid) {
            $article = $this->article($invalid);
            Setting::set('blog.featured.en', (string) $article->id);
            $response = $this->get('/blog')->assertOk();
            $this->assertNull($response['featured']);
            $this->assertSame($newest->id, $response['lead']->id);
        }
    }

    public function test_search_category_and_oldest_sort_include_the_full_collection_without_a_lead(): void
    {
        $category = BlogCategory::factory()->create(['slug' => 'learning']);
        $articles = BlogFactory::new()->count(15)->create(['title' => 'Arabic fixture', 'status' => 'published', 'published_at' => now()->subDay(), 'category_id' => $category->id]);
        Setting::set('blog.featured.en', (string) $articles->first()->id);
        foreach (['q=Arabic', 'category=learning', 'sort=oldest', 'q=Arabic&category=learning&sort=oldest'] as $query) {
            $first = $this->get('/blog?'.$query)->assertOk();
            $second = $this->get('/blog?'.$query.'&page=2')->assertOk();
            $this->assertNull($first['lead']);
            $this->assertNull($first['featured']);
            $this->assertSame(15, $first['blogs']->total());
            $seen = [...$first['blogs']->pluck('id'), ...$second['blogs']->pluck('id')];
            $expected = str_contains($query, 'oldest') ? $articles->pluck('id') : $articles->pluck('id')->reverse();
            $this->assertSame($expected->values()->all(), $seen);
            $this->assertStringContainsString(str_replace('&', '&amp;', $query), $first->getContent());
        }
        $this->get('/blog?q=Arabic&category=learning&sort=oldest&page=99')->assertRedirect(url('/blog?q=Arabic&category=learning&sort=oldest&page=2').'#article-library');
    }

    public function test_controls_follow_publication_size_and_keep_active_filters_recoverable(): void
    {
        $this->get('/blog?q=anything&category=unknown')->assertOk()->assertSee('Good Things Take a Little Thought.')->assertDontSee('No Articles Found')->assertDontSee('blogs-invitation')->assertDontSee('id="article-search"', false);
        $category = BlogCategory::factory()->create();
        $this->article(['category_id' => $category->id]);
        $this->get('/blog')->assertOk()->assertDontSee('id="article-search"', false)->assertDontSee('id="article-category"', false)->assertSee('blog-card-lead');
        $this->get('/blog?q=unmatched')->assertOk()->assertSee('No Articles Found')->assertSee('value="unmatched"', false)->assertSee('Clear Search &amp; Filters', false);
        $this->get('/blog?category=unknown')->assertOk()->assertSee('Nothing in This Category Yet')->assertSee('View All Articles')->assertSee('value="unknown" selected', false);
        $this->article();
        $this->get('/blog')->assertOk()->assertSee('id="article-search"', false)->assertSee('id="article-sort"', false)->assertDontSee('id="article-category"', false);
        $other = BlogCategory::factory()->create();
        $this->article(['category_id' => $other->id]);
        $this->get('/blog')->assertOk()->assertSee('id="article-category"', false);
    }

    public function test_drafts_scheduled_and_archived_content_are_excluded_from_every_public_query(): void
    {
        $this->freezeTime();
        $category = BlogCategory::factory()->create(['name' => 'Private-only category']);
        foreach ([['status' => 'draft'], ['status' => 'archived'], ['published_at' => now()->addSecond()]] as $invalid) {
            $hidden = $this->article($invalid + ['category_id' => $category->id]);
            $this->get($hidden->publicUrl())->assertNotFound()->assertDontSee($hidden->title);
        }
        $this->get('/blog?q=anything')->assertSee('Good Things Take a Little Thought.')->assertDontSee($category->name);
        $this->get('/sitemap.xml')->assertDontSee('/blog/'.$hidden->slug);
        $visible = $this->article(['published_at' => now()->subSecond()]);
        $this->get($visible->publicUrl())->assertOk();
    }

    public function test_index_copy_overrides_do_not_replace_article_seo_or_translation_metadata(): void
    {
        WebsiteCopy::factory()->create(['page' => 'blogs', 'locale' => 'en', 'published_content' => ['seo__title' => 'Index-only title', 'seo__description' => 'Index-only description', 'ui__'.sha1('Copy Link') => 'Save article address']]);
        $article = $this->article(['seo_title' => 'Article-specific title', 'seo_description' => 'Article-specific description']);
        $this->get('/blog')->assertOk()->assertSee('<title>Index-only title', false);
        $this->get($article->publicUrl())->assertOk()->assertSee('<title>Article-specific title', false)->assertSee('content="Article-specific description"', false)->assertSee('Save article address')->assertDontSee('Index-only title')->assertDontSee('hreflang="fr"', false);
    }

    public function test_reading_transform_preserves_unicode_and_stored_body_and_normalizes_heading_hierarchy(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('lesson.png', 800, 600)->store('media', 'public');
        $arabic = 'أَهْلًا وَسَهْلًا — القاهرة، ١٢٣ (2026)!';
        $body = '<h1>Lesson heading</h1><h2>Subsection</h2><p><span dir="rtl" lang="ar">'.$arabic.'</span> · ahlan wa-sahlan · Welcome! <sup>2</sup> H<sub>2</sub>O</p><blockquote dir="rtl" lang="ar">'.$arabic.'</blockquote><ol start="3"><li>Example</li></ol><pre><code>long_example()</code></pre><table><thead><tr><th>Arabic</th><th>Translation</th></tr></thead><tbody><tr><td><span dir="rtl" lang="ar">'.$arabic.'</span></td><td>Welcome</td></tr></tbody></table><img src="'.Storage::disk('public')->url($path).'" alt="Arabic lesson example"><img src="/storage/missing.png" alt="Missing example">';
        $article = $this->article(['body' => $body]);
        $response = $this->get($article->publicUrl())->assertOk()->assertSee('<h2>Lesson heading</h2>', false)->assertSee('<h3>Subsection</h3>', false)->assertSee($arabic)->assertSee('role="region" tabindex="0" aria-label="Scrollable table"', false)->assertSee('<table lang="en">', false)->assertSee('width="800" height="600" loading="lazy"', false)->assertDontSee('missing.png');
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertSame(1, $document->getElementsByTagName('h1')->length);
        $this->assertSame($body, $article->fresh()->body);
    }

    public function test_self_related_articles_are_not_shown_and_private_preview_has_no_copy_control(): void
    {
        $article = $this->article();
        $related = $this->article();
        $scheduled = $this->article(['published_at' => now()->addDay()]);
        $article->relatedArticles()->attach([$article->id, $related->id, $scheduled->id]);
        $response = $this->get($article->publicUrl())->assertOk();
        $this->assertSame([$related->id], $response['relatedArticles']->pluck('id')->all());
        $response->assertSee('data-copy-text="'.$article->publicUrl().'"', false)->assertSee('data-copy-failure="Copy unavailable"', false)->assertDontSee($scheduled->title);
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.blog.preview', $article))->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')->assertDontSee('data-copy-text=', false)->assertDontSee('application/ld+json');
    }

    public function test_filter_limits_remain_validated_and_whitespace_search_uses_default_lead(): void
    {
        $article = $this->article();
        $this->assertSame($article->id, $this->get('/blog?q=%20%20')->assertOk()['lead']->id);
        $this->getJson('/blog?q='.str_repeat('a', 201))->assertUnprocessable();
        $this->getJson('/blog?category='.str_repeat('a', 121))->assertUnprocessable();
        $this->getJson('/blog?sort=random')->assertUnprocessable();
        $this->get('/blog?page=999')->assertRedirect(url('/blog').'#article-library');
    }
}

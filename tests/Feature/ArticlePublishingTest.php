<?php

namespace Tests\Feature;

use App\Domains\CMS\Services\ArticleService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class ArticlePublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_article_html_is_purified_and_external_or_data_images_are_removed(): void
    {
        $admin = AdministratorFactory::new()->create();
        $article = app(ArticleService::class)->create($this->articleData(
            '<h2>Safe heading</h2><script>alert(1)</script><img src="data:image/svg+xml,<svg onload=alert(1)>" onerror="alert(2)"><img src="https://evil.example/tracker.png"><img src="/storage/%252e%252e/private/secret.png"><img src="/storage/%255c..%255cprivate/secret.png"><img src="/storage/articles/lesson.png" alt="lesson" onerror="alert(3)"><a href="javascript:alert(4)">bad link</a>'
        ), $admin->id);

        $this->assertStringContainsString('<h2>Safe heading</h2>', $article->body);
        $this->assertStringContainsString('/storage/articles/lesson.png', $article->body);
        $this->assertStringNotContainsString('<script', strtolower($article->body));
        $this->assertStringNotContainsString('onerror', strtolower($article->body));
        $this->assertStringNotContainsString('data:image', strtolower($article->body));
        $this->assertStringNotContainsString('evil.example', strtolower($article->body));
        $this->assertStringNotContainsString('private/secret.png', strtolower($article->body));
        $this->assertStringNotContainsString('javascript:', strtolower($article->body));
    }

    public function test_published_updates_snapshot_complete_content_and_old_slug_returns_301(): void
    {
        $admin = AdministratorFactory::new()->create();
        $service = app(ArticleService::class);
        $article = $service->create($this->articleData('<p>Original body</p>'), $admin->id);
        $article = $service->update($article, $this->articleData('<p>Published body</p>', 'published'), 1, $admin->id);
        $article = $service->update($article, $this->articleData('<p>Revised body</p>', 'published', 'arabic-learning-guide-v2'), 2, $admin->id);

        $this->assertDatabaseCount('article_revisions', 1);
        $snapshot = json_decode((string) DB::table('article_revisions')->where('article_id', $article->id)->orderBy('id')->value('snapshot'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('article-guide', $snapshot['slug']);
        $this->assertSame('<p>Published body</p>', $snapshot['body']);
        $this->assertDatabaseHas('article_slug_redirects', ['article_id' => $article->id, 'old_slug' => 'article-guide']);

        $this->get('/articles/article-guide')->assertStatus(301)->assertRedirect(route('articles.show', 'arabic-learning-guide-v2'));
        $this->get('/articles/arabic-learning-guide-v2')->assertOk()->assertSee('Revised body')->assertSee('Guide title');
    }

    public function test_stale_article_editor_version_is_rejected_with_conflict(): void
    {
        $admin = AdministratorFactory::new()->create();
        $service = app(ArticleService::class);
        $article = $service->create($this->articleData('<p>Draft</p>'), $admin->id);
        $service->update($article, $this->articleData('<p>First update</p>'), 1, $admin->id);

        $this->expectException(ConflictHttpException::class);
        $service->update($article, $this->articleData('<p>Stale update</p>'), 1, $admin->id);
    }

    public function test_drafts_never_leak_through_public_article_route(): void
    {
        $admin = AdministratorFactory::new()->create();
        $article = app(ArticleService::class)->create($this->articleData('<p>Private draft</p>'), $admin->id);

        $this->get(route('articles.show', $article->slug))->assertNotFound();
        $this->get(route('articles.index'))->assertOk()->assertDontSee('Private draft');
    }

    public function test_public_and_admin_navigation_use_blogs_terminology_without_renaming_routes(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSeeText('Blogs')
            ->assertSeeText('New blog posts are coming soon.')
            ->assertDontSeeText('Articles');

        $this->actingAs($admin, 'web')
            ->get(route('admin.articles.index'))
            ->assertOk()
            ->assertSeeText('Blogs')
            ->assertSeeText('Add Blog Post')
            ->assertDontSeeText('Articles');

        $this->get(route('admin.articles.create'))
            ->assertOk()
            ->assertSeeText('Add Blog Post')
            ->assertSeeText('Create Blog Post')
            ->assertDontSeeText('Create article');
    }

    /** @return array<string, mixed> */
    private function articleData(string $body, string $status = 'draft', string $slug = 'article-guide'): array
    {
        return [
            'title' => 'Guide title', 'slug' => $slug, 'excerpt' => 'A useful language guide.',
            'body' => $body, 'status' => $status, 'locale' => 'en',
            'seo_title' => 'SEO guide', 'seo_description' => 'Learn Egyptian Arabic.',
        ];
    }
}

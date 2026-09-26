<?php

namespace Tests\Feature;

use App\Domains\CMS\Services\BlogService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class BlogPublishingTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_html_is_purified_and_external_or_data_images_are_removed(): void
    {
        $admin = AdministratorFactory::new()->create();
        $blog = app(BlogService::class)->create($this->blogData(
            '<h2>Safe heading</h2><script>alert(1)</script><img src="data:image/svg+xml,<svg onload=alert(1)>" onerror="alert(2)"><img src="https://evil.example/tracker.png"><img src="/storage/%252e%252e/private/secret.png"><img src="/storage/%255c..%255cprivate/secret.png"><img src="/storage/blog/lesson.png" alt="lesson" onerror="alert(3)"><a href="javascript:alert(4)">bad link</a>'
        ), $admin->id);

        $this->assertStringContainsString('<h2>Safe heading</h2>', $blog->body);
        $this->assertStringContainsString('/storage/blog/lesson.png', $blog->body);
        $this->assertStringNotContainsString('<script', strtolower($blog->body));
        $this->assertStringNotContainsString('onerror', strtolower($blog->body));
        $this->assertStringNotContainsString('data:image', strtolower($blog->body));
        $this->assertStringNotContainsString('evil.example', strtolower($blog->body));
        $this->assertStringNotContainsString('private/secret.png', strtolower($blog->body));
        $this->assertStringNotContainsString('javascript:', strtolower($blog->body));
    }

    public function test_published_updates_snapshot_complete_content_and_old_slug_returns_301(): void
    {
        $admin = AdministratorFactory::new()->create();
        $service = app(BlogService::class);
        $blog = $service->create($this->blogData('<p>Original body</p>'), $admin->id);
        $blog = $service->update($blog, $this->blogData('<p>Published body</p>', 'published'), 1, $admin->id);
        $blog = $service->update($blog, $this->blogData('<p>Revised body</p>', 'published', 'arabic-learning-guide-v2'), 2, $admin->id);

        $this->assertDatabaseCount('blog_revisions', 1);
        $snapshot = json_decode((string) DB::table('blog_revisions')->where('blog_id', $blog->id)->orderBy('id')->value('snapshot'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('blog-guide', $snapshot['slug']);
        $this->assertSame('<p>Published body</p>', $snapshot['body']);
        $this->assertDatabaseHas('blog_slug_redirects', ['blog_id' => $blog->id, 'old_slug' => 'blog-guide']);

        $this->get('/blog/blog-guide')->assertStatus(301)->assertRedirect(route('blog.show', 'arabic-learning-guide-v2'));
        $this->get('/blog/arabic-learning-guide-v2')->assertOk()->assertSee('Revised body')->assertSee('Guide title');
    }

    public function test_stale_blog_editor_version_is_rejected_with_conflict(): void
    {
        $admin = AdministratorFactory::new()->create();
        $service = app(BlogService::class);
        $blog = $service->create($this->blogData('<p>Draft</p>'), $admin->id);
        $service->update($blog, $this->blogData('<p>First update</p>'), 1, $admin->id);

        $this->expectException(ConflictHttpException::class);
        $service->update($blog, $this->blogData('<p>Stale update</p>'), 1, $admin->id);
    }

    public function test_drafts_never_leak_through_public_blog_route(): void
    {
        $admin = AdministratorFactory::new()->create();
        $blog = app(BlogService::class)->create($this->blogData('<p>Private draft</p>'), $admin->id);

        $this->get(route('blog.show', $blog->slug))->assertNotFound();
        $this->get(route('blog.index'))->assertOk()->assertDontSee('Private draft');
    }

    public function test_public_and_admin_navigation_use_singular_blog_terminology(): void
    {
        $admin = AdministratorFactory::new()->create(['role' => 'admin']);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSeeText('Blog')
            ->assertSeeText('New blog posts are coming soon.')
            ->assertDontSeeText('Blogs');

        $this->actingAs($admin, 'web')
            ->get(route('admin.blog.index'))
            ->assertOk()
            ->assertSeeText('Blog')
            ->assertSeeText('Add Blog Post')
            ->assertDontSeeText('Blogs');

        $this->get(route('admin.blog.create'))
            ->assertOk()
            ->assertSeeText('Add Blog Post')
            ->assertSeeText('Create Blog Post');
    }

    /** @return array<string, mixed> */
    private function blogData(string $body, string $status = 'draft', string $slug = 'blog-guide'): array
    {
        return [
            'title' => 'Guide title', 'slug' => $slug, 'excerpt' => 'A useful language guide.',
            'body' => $body, 'status' => $status, 'locale' => 'en',
            'seo_title' => 'SEO guide', 'seo_description' => 'Learn Egyptian Arabic.',
        ];
    }
}

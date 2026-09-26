<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Blog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BlogService
{
    public function __construct(private RichTextSanitizer $sanitizer) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $administratorId): Blog
    {
        $data['slug'] = $this->normalizeSlug((string) $data['slug']);
        $this->assertSlugIsAvailable($data['slug']);
        $data['body'] = $this->sanitizeBody((string) $data['body']);
        $data['author_id'] = $administratorId;
        $data['lock_version'] = 1;
        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = $data['published_at'] ?? now('UTC');
        }

        return Blog::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Blog $blog, array $data, int $baseVersion, int $administratorId): Blog
    {
        return DB::transaction(function () use ($blog, $data, $baseVersion, $administratorId): Blog {
            $locked = Blog::query()->whereKey($blog->id)->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $baseVersion) {
                throw new ConflictHttpException('This blog post changed while you were editing. Reload before saving.');
            }

            $data['slug'] = $this->normalizeSlug((string) $data['slug']);
            $this->assertSlugIsAvailable($data['slug'], $locked);
            $data['body'] = $this->sanitizeBody((string) $data['body']);
            $data['lock_version'] = $locked->lock_version + 1;
            if (($data['status'] ?? null) === 'published') {
                $data['published_at'] = $locked->published_at ?? now('UTC');
            } elseif (($data['status'] ?? null) !== 'published') {
                $data['published_at'] = null;
            }

            if ($locked->status === 'published') {
                DB::table('blog_revisions')->insert([
                    'blog_id' => $locked->id,
                    'snapshot' => json_encode($locked->only([
                        'title', 'slug', 'excerpt', 'body', 'featured_image_path',
                        'seo_title', 'seo_description', 'canonical_url', 'status',
                        'published_at', 'locale', 'translation_group_id', 'lock_version',
                    ]), JSON_THROW_ON_ERROR),
                    'revised_by' => $administratorId,
                    'created_at' => now('UTC'),
                ]);
            }

            if ($locked->slug !== $data['slug']) {
                DB::table('blog_slug_redirects')->insert([
                    'blog_id' => $locked->id,
                    'old_slug' => $locked->slug,
                    'created_at' => now('UTC'),
                ]);
            }

            $locked->update($data);

            return $locked->fresh();
        }, 5);
    }

    public function sanitizeBody(string $body): string
    {
        return $this->sanitizer->sanitize($body);
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = Str::slug(trim($slug));
        if ($normalized === '') {
            throw ValidationException::withMessages(['slug' => 'A valid blog post slug is required.']);
        }

        return $normalized;
    }

    private function assertSlugIsAvailable(string $slug, ?Blog $blog = null): void
    {
        $existingBlog = Blog::query()->where('slug', $slug)->when($blog, fn ($query) => $query->where('id', '<>', $blog->id))->exists();
        $redirectExists = DB::table('blog_slug_redirects')->where('old_slug', $slug)->exists();
        if ($existingBlog || $redirectExists) {
            throw ValidationException::withMessages(['slug' => 'That slug is already in use or reserved by a redirect.']);
        }
    }
}

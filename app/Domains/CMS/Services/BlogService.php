<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Blog;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BlogService
{
    public function __construct(private RichTextSanitizer $sanitizer, private BlogImageService $images) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $administratorId): Blog
    {
        try {
            return DB::transaction(function () use ($data, $administratorId): Blog {
                if (! empty($data['translation_of'])) {
                    $source = Blog::query()->whereKey($data['translation_of'])->lockForUpdate()->firstOrFail();
                    if (! $source->translation_group_id) {
                        $source->update(['translation_group_id' => (string) Str::uuid(), 'lock_version' => $source->lock_version + 1]);
                    }
                    $data['translation_group_id'] = $source->translation_group_id;
                }
                unset($data['translation_of']);
                $data = $this->prepare($data);
                $this->assertSlugIsAvailable($data['slug']);
                $this->assertTranslationIsAvailable($data);
                $data['body'] = $this->sanitizeBody((string) $data['body']);
                $this->assertPublishable($data);
                $relations = $this->relations($data);
                $data = array_diff_key($data, $relations);
                $data['author_id'] = $administratorId;
                $data['lock_version'] = 1;
                $data['published_at'] = $data['status'] === 'published' ? ($data['published_at'] ?? now('UTC')) : null;
                $blog = Blog::create($data);
                $this->syncRelations($blog, $relations);

                return $blog->fresh(['category', 'relatedArticles', 'relatedResources', 'relatedGames']);
            });
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['slug' => 'This article URL or language version is already in use. Reload and choose another.']);
        }
    }

    /** @param array<string, mixed> $data */
    public function update(Blog $blog, array $data, int $baseVersion, int $administratorId): Blog
    {
        try {
            return DB::transaction(function () use ($blog, $data, $baseVersion, $administratorId): Blog {
                $locked = Blog::query()->whereKey($blog->id)->lockForUpdate()->firstOrFail();
                if ($locked->lock_version !== $baseVersion) {
                    throw new ConflictHttpException('This article changed while you were editing. Reload before saving.');
                }
                $data = $this->prepare($data, $locked);
                $this->assertSlugIsAvailable($data['slug'], $locked);
                $this->assertTranslationIsAvailable($data, $locked);
                $data['body'] = $data['body'] === $locked->body ? $locked->body : $this->sanitizeBody((string) $data['body']);
                $this->assertPublishable($data);
                $relations = $this->relations($data, $locked);
                $data = array_diff_key($data, $relations);
                $data['lock_version'] = $locked->lock_version + 1;
                $data['published_at'] = $locked->published_at ?? ($data['status'] === 'published' ? now('UTC') : null);
                $meaningful = collect(['title', 'excerpt', 'body', 'category_id', 'featured_image_path', 'featured_image_alt'])
                    ->contains(fn (string $field): bool => array_key_exists($field, $data) && $data[$field] != $locked->getAttribute($field));
                foreach (['related_article_ids' => 'relatedArticles', 'related_resource_ids' => 'relatedResources', 'related_game_ids' => 'relatedGames'] as $key => $relation) {
                    $previous = $locked->{$relation}()->pluck($locked->{$relation}()->getRelated()->getTable().'.id')->map(fn ($id): int => (int) $id)->sort()->values()->all();
                    $next = $relations[$key];
                    sort($next);
                    $meaningful = $meaningful || $previous !== $next;
                }
                $data['editorial_updated_at'] = $locked->published_at && $meaningful ? now('UTC') : $locked->editorial_updated_at;
                if ($locked->published_at) {
                    $snapshot = $locked->toArray();
                    foreach (['related_article_ids' => 'relatedArticles', 'related_resource_ids' => 'relatedResources', 'related_game_ids' => 'relatedGames'] as $key => $relation) {
                        $snapshot[$key] = $locked->{$relation}()->pluck($locked->{$relation}()->getRelated()->getTable().'.id')->all();
                    }
                    $locked->revisions()->create(['snapshot' => $snapshot, 'revised_by' => $administratorId, 'created_at' => now('UTC')]);
                }
                if ($locked->slug !== $data['slug'] && $locked->published_at) {
                    DB::table('blog_slug_redirects')->updateOrInsert(['old_slug' => $locked->slug], ['blog_id' => $locked->id, 'created_at' => now('UTC')]);
                }
                $locked->update($data);
                $this->syncRelations($locked, $relations);

                return $locked->fresh(['category', 'relatedArticles', 'relatedResources', 'relatedGames']);
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['slug' => 'This article URL or language version is already in use. Reload and choose another.']);
        }
    }

    public function sanitizeBody(string $body): string
    {
        return $this->sanitizer->sanitizeBlog($body);
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function prepare(array $data, ?Blog $blog = null): array
    {
        $stored = $blog?->getAttributes() ?? [];
        $data['status'] ??= $stored['status'] ?? 'draft';
        $data['title'] = array_key_exists('title', $data) ? (string) $data['title'] : ($stored['title'] ?? '');
        $data['excerpt'] = array_key_exists('excerpt', $data) ? (string) $data['excerpt'] : ($stored['excerpt'] ?? '');
        $data['body'] = array_key_exists('body', $data) ? (string) $data['body'] : ($stored['body'] ?? '');
        $slug = (string) ($data['slug'] ?? $stored['slug'] ?? $data['title']);
        $data['slug'] = Str::slug(trim($slug)) ?: 'draft-'.Str::lower((string) Str::ulid());
        $data['locale'] ??= $stored['locale'] ?? 'en';
        $data['translation_group_id'] = $stored['translation_group_id'] ?? ($data['translation_group_id'] ?? (string) Str::uuid());
        if (! in_array($data['locale'], ['en', 'fr', 'de'], true) || ! in_array($data['status'], ['draft', 'published', 'archived'], true)) {
            throw ValidationException::withMessages(['locale' => 'Choose a supported language and publication status.']);
        }
        if ($blog && $data['locale'] !== $blog->locale) {
            throw ValidationException::withMessages(['locale' => 'An existing article keeps its language. Use Add Translation to create another version.']);
        }
        if (array_key_exists('featured_image_path', $data) && (! $blog || $data['featured_image_path'] !== $blog->featured_image_path)) {
            $this->images->assertSelectable($data['featured_image_path']);
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function assertPublishable(array $data): void
    {
        if ($data['status'] !== 'published') {
            return;
        }
        $text = html_entity_decode(strip_tags($data['body']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($data['title']) === '') {
            throw ValidationException::withMessages(['title' => 'Please enter an article title.']);
        }
        if (trim($data['excerpt']) === '') {
            throw ValidationException::withMessages(['excerpt' => 'Please add a short article summary before publishing.']);
        }
        if (! preg_match('/[\\p{L}\\p{N}]/u', $text)) {
            throw ValidationException::withMessages(['body' => 'Please add article content before publishing.']);
        }
    }

    /** @param array<string, mixed> $data */
    private function assertTranslationIsAvailable(array $data, ?Blog $blog = null): void
    {
        if (Blog::query()->where('translation_group_id', $data['translation_group_id'])->where('locale', $data['locale'])
            ->when($blog, fn ($query) => $query->whereKeyNot($blog->id))->exists()) {
            throw ValidationException::withMessages(['locale' => 'This article already has a version in that language. Edit the existing version.']);
        }
    }

    private function assertSlugIsAvailable(string $slug, ?Blog $blog = null): void
    {
        $existing = Blog::query()->where('slug', $slug)->when($blog, fn ($query) => $query->whereKeyNot($blog->id))->exists();
        $redirect = DB::table('blog_slug_redirects')->where('old_slug', $slug)->when($blog, fn ($query) => $query->where('blog_id', '<>', $blog->id))->exists();
        if ($existing || $redirect) {
            throw ValidationException::withMessages(['slug' => "This article URL isn't available. Please choose another slug."]);
        }
    }

    /** @param array<string, mixed> $data @return array<string, array<int, int>> */
    private function relations(array $data, ?Blog $blog = null): array
    {
        $result = [];
        foreach (['related_article_ids' => 'relatedArticles', 'related_resource_ids' => 'relatedResources', 'related_game_ids' => 'relatedGames'] as $key => $relation) {
            $result[$key] = array_values(array_unique(array_map('intval', $data[$key] ?? ($blog ? $blog->{$relation}()->pluck($blog->{$relation}()->getRelated()->getTable().'.id')->all() : []))));
            if ($key === 'related_article_ids' && $blog && in_array($blog->id, $result[$key], true)) {
                throw ValidationException::withMessages([$key => 'An article cannot link to itself.']);
            }
            $query = match ($key) {
                'related_article_ids' => Blog::published(),
                'related_resource_ids' => Resource::publicCatalogue(),
                default => Game::query()->where('status', 'available'),
            };
            $previous = $blog ? $blog->{$relation}()->pluck($blog->{$relation}()->getRelated()->getTable().'.id')->map(fn ($id): int => (int) $id)->all() : [];
            $new = array_diff($result[$key], $previous);
            if ($query->whereIn('id', $new)->count() !== count($new)) {
                throw ValidationException::withMessages([$key => 'Choose genuinely published and publicly available related content.']);
            }
        }

        return $result;
    }

    /** @param array<string, array<int, int>> $relations */
    private function syncRelations(Blog $blog, array $relations): void
    {
        $blog->relatedArticles()->sync($relations['related_article_ids']);
        $blog->relatedResources()->sync($relations['related_resource_ids']);
        $blog->relatedGames()->sync($relations['related_game_ids']);
    }
}

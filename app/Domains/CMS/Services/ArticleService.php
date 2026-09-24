<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Article;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Purifier;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ArticleService
{
    public function __construct(private Purifier $purifier) {}

    /** @param array<string, mixed> $data */
    public function create(array $data, int $administratorId): Article
    {
        $data['slug'] = $this->normalizeSlug((string) $data['slug']);
        $this->assertSlugIsAvailable($data['slug']);
        $data['body'] = $this->sanitizeBody((string) $data['body']);
        $data['author_id'] = $administratorId;
        $data['lock_version'] = 1;
        if (($data['status'] ?? 'draft') === 'published') {
            $data['published_at'] = $data['published_at'] ?? now('UTC');
        }

        return Article::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(Article $article, array $data, int $baseVersion, int $administratorId): Article
    {
        return DB::transaction(function () use ($article, $data, $baseVersion, $administratorId): Article {
            $locked = Article::query()->whereKey($article->id)->lockForUpdate()->firstOrFail();
            if ($locked->lock_version !== $baseVersion) {
                throw new ConflictHttpException('This article changed while you were editing. Reload before saving.');
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
                DB::table('article_revisions')->insert([
                    'article_id' => $locked->id,
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
                DB::table('article_slug_redirects')->insert([
                    'article_id' => $locked->id,
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
        $body = $this->removeNonStorageImages($body);

        return (string) $this->purifier->clean($body, [
            'HTML.Allowed' => 'h1,h2,h3,h4,p,b,i,strong,em,ul,ol,li,blockquote,a[href|title],img[src|alt],br',
            'URI.AllowedSchemes' => ['http' => true, 'https' => true, 'mailto' => true],
            'Attr.EnableID' => false,
        ]);
    }

    private function removeNonStorageImages(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $images = iterator_to_array($document->getElementsByTagName('img'));
            foreach ($images as $image) {
                if (! $image instanceof DOMElement || ! $this->isSameOriginStorageUrl($image->getAttribute('src'))) {
                    $image?->parentNode?->removeChild($image);
                }
            }

            $body = $document->getElementsByTagName('body')->item(0);
            $cleanHtml = '';
            if ($body) {
                foreach ($body->childNodes as $child) {
                    $cleanHtml .= $document->saveHTML($child);
                }
            }

            return $cleanHtml;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function isSameOriginStorageUrl(string $source): bool
    {
        $source = trim($source);
        $base = parse_url(URL::to('/storage/'));
        $parsed = parse_url($source);
        if (! $base || ! is_array($base) || ! $parsed || ! is_array($parsed)) {
            return false;
        }

        if (isset($parsed['scheme']) || isset($parsed['host'])) {
            if (! in_array(strtolower((string) ($parsed['scheme'] ?? '')), ['http', 'https'], true)
                || strtolower((string) ($parsed['scheme'] ?? '')) !== strtolower((string) ($base['scheme'] ?? ''))
                || strtolower((string) ($parsed['host'] ?? '')) !== strtolower((string) ($base['host'] ?? ''))
                || isset($parsed['user']) || isset($parsed['pass'])) {
                return false;
            }
            if (isset($parsed['port']) && $parsed['port'] !== ($base['port'] ?? null)) {
                return false;
            }
        } elseif (! str_starts_with($source, '/')) {
            return false;
        }

        $path = (string) ($parsed['path'] ?? '');
        $fullyDecoded = false;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $decodedPath = rawurldecode($path);
            if ($decodedPath === $path) {
                $fullyDecoded = true;
                break;
            }

            $path = $decodedPath;
        }
        $path = str_replace('\\', '/', $path);
        $allowedPath = rtrim((string) ($base['path'] ?? '/storage/'), '/').'/';
        if (! $fullyDecoded
            || ! str_starts_with($path, $allowedPath)
            || preg_match('~(?:^|/)\.\.(?:/|$)~', $path)
            || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        return true;
    }

    private function normalizeSlug(string $slug): string
    {
        $normalized = Str::slug(trim($slug));
        if ($normalized === '') {
            throw ValidationException::withMessages(['slug' => 'A valid article slug is required.']);
        }

        return $normalized;
    }

    private function assertSlugIsAvailable(string $slug, ?Article $article = null): void
    {
        $existingArticle = Article::query()->where('slug', $slug)->when($article, fn ($query) => $query->where('id', '<>', $article->id))->exists();
        $redirectExists = DB::table('article_slug_redirects')->where('old_slug', $slug)->exists();
        if ($existingArticle || $redirectExists) {
            throw ValidationException::withMessages(['slug' => 'That slug is already in use or reserved by a redirect.']);
        }
    }
}

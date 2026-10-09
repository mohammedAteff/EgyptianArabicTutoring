<?php

namespace App\Domains\CMS\Services;

use App\Domains\CMS\Models\Blog;
use DOMDocument;
use Illuminate\Support\Facades\Storage;

class BlogPresentationService
{
    public function __construct(private BlogService $blogs, private BlogImageService $images) {}

    /** @return array<string, mixed> */
    public function data(Blog $blog, bool $preview = false): array
    {
        $blog->loadMissing('category');
        $versions = Blog::published()->when($blog->translation_group_id, fn ($query) => $query->where('translation_group_id', $blog->translation_group_id), fn ($query) => $query->whereKey($blog->id))->get(['id', 'title', 'slug', 'locale']);
        $image = $this->images->url($blog->featured_image_path);
        $canonical = $blog->canonical_url ?: $blog->publicUrl();
        $schema = [
            '@context' => 'https://schema.org', '@type' => 'BlogPosting',
            'headline' => $blog->title, 'description' => $blog->seo_description ?: $blog->excerpt,
            'author' => ['@type' => 'Person', 'name' => 'Abdallah'],
            'inLanguage' => $blog->locale, 'mainEntityOfPage' => $canonical,
        ];
        if ($image) {
            $schema['image'] = url($image);
        }
        if ($blog->published_at) {
            $schema['datePublished'] = $blog->published_at->toIso8601String();
        }
        if ($blog->editorial_updated_at) {
            $schema['dateModified'] = $blog->editorial_updated_at->toIso8601String();
        }

        return [
            'blog' => $blog, 'preview' => $preview, 'isPreview' => $preview,
            'title' => $blog->seo_title ?: $blog->title,
            'metaDescription' => $blog->seo_description ?: $blog->excerpt,
            'canonicalOverride' => $canonical, 'ogType' => 'article', 'ogImage' => $image,
            'articleSchema' => $preview ? null : $schema, 'contentLocale' => $blog->locale,
            'entityLocales' => $preview ? [] : $versions->pluck('locale')->all(),
            'articleAlternates' => $preview ? [] : $versions->mapWithKeys(fn (Blog $version): array => [$version->locale => $version->publicUrl()])->all(),
            'versions' => $versions, 'featuredImage' => $image,
            'articleBody' => $this->body($blog->body),
            'relatedArticles' => $blog->relatedArticles()->published()->with('category')->get(),
            'relatedResources' => $blog->relatedResources()->publicCatalogue()->with('translations', 'category')->get(),
            'relatedGames' => $blog->relatedGames()->available()->with('translations')->get(),
        ];
    }

    private function body(string $body): string
    {
        $body = $this->blogs->sanitizeBody($body);
        if (! str_contains($body, '<img')) {
            return $body;
        }
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8">'.$body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            $base = rtrim((string) parse_url(Storage::disk('public')->url('/'), PHP_URL_PATH), '/').'/';
            foreach (iterator_to_array($document->getElementsByTagName('img')) as $image) {
                $path = rawurldecode((string) parse_url($image->getAttribute('src'), PHP_URL_PATH));
                if (! str_starts_with($path, $base) || ! $this->images->url(substr($path, strlen($base)))) {
                    $image->parentNode?->removeChild($image);
                }
            }
            $bodyElement = $document->getElementsByTagName('body')->item(0);
            if (! $bodyElement) {
                return '';
            }
            $result = '';
            foreach ($bodyElement->childNodes as $child) {
                $result .= $document->saveHTML($child);
            }

            return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}

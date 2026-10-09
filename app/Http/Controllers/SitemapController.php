<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Models\Blog;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\LocalizedUrlService;
use App\Domains\Resources\Models\Resource;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(LocalizedUrlService $urls): Response
    {
        $entries = [];
        foreach (['home', 'pricing', 'about', 'faq', 'resources', 'games', 'blogs', 'policy', 'terms', 'privacy'] as $key) {
            $entries[] = ['url' => $urls->getLocalizedUrl($key, 'en'), 'updated' => null];
        }
        foreach (Blog::published()->orderBy('id')->get() as $blog) {
            $url = $blog->publicUrl();
            if ($blog->canonical_url && rtrim($blog->canonical_url, '/') !== rtrim($url, '/')) {
                continue;
            }
            $entries[] = ['url' => $url, 'updated' => ($blog->editorial_updated_at ?? $blog->published_at)?->toIso8601String()];
        }
        foreach (Resource::publicCatalogue()->with('translations')->get() as $resource) {
            foreach ($resource->getAvailableLocales() as $locale) {
                $entries[] = ['url' => $urls->getLocalizedUrl('resource.detail', $locale, $resource->slug), 'updated' => null];
            }
        }
        foreach (Page::query()->where('status', 'published')->whereNotIn('slug', ['about'])->with('translations')->get() as $page) {
            foreach ($page->getAvailableLocales() as $locale) {
                $entries[] = ['url' => $urls->getLocalizedUrl('page.show', $locale, $page->slug), 'updated' => null];
            }
        }

        return response()->view('sitemap', ['entries' => $entries])->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}

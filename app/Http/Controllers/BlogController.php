<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Models\Blog;
use App\Domains\CMS\Models\BlogCategory;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\BlogPresentationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:200'], 'category' => ['nullable', 'string', 'max:120'], 'sort' => ['nullable', 'in:newest,oldest']]);
        $locale = app()->getLocale();
        $fallback = $locale !== 'en' && ! Blog::published()->where('locale', $locale)->exists();
        $contentLocale = $fallback ? 'en' : $locale;
        $base = Blog::published()->where('locale', $contentLocale);
        $totalPublished = (clone $base)->count();
        $query = (clone $base)->with('category');
        $search = trim((string) ($filters['q'] ?? ''));
        $category = (string) ($filters['category'] ?? '');
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')->orWhere('excerpt', 'like', '%'.$search.'%')->orWhere('body', 'like', '%'.$search.'%');
            });
        }
        if ($category !== '') {
            $query->whereHas('category', fn ($query) => $query->where('slug', $category));
        }
        $sort = $filters['sort'] ?? 'newest';
        $query->orderBy('published_at', $sort === 'oldest' ? 'asc' : 'desc')->orderBy('id', $sort === 'oldest' ? 'asc' : 'desc');
        $featured = null;
        if ($search === '' && $category === '' && ! $request->filled('page')) {
            $featured = (clone $base)->with('category')->whereKey(Setting::get('blog.featured.'.$contentLocale))->first();
        }

        return view('blog.index', [
            'title' => 'Blogs', 'metaDescription' => 'Practical explanations, learning advice, and thoughtful articles about Egyptian Arabic, Modern Standard Arabic, language and life in Egypt.',
            'blogs' => $query->paginate(12)->withQueryString(), 'categories' => BlogCategory::query()->whereHas('blogs', fn ($query) => $query->published()->where('locale', $contentLocale))->orderBy('name')->get(),
            'search' => $search, 'selectedCategory' => $category, 'sort' => $sort, 'totalPublished' => $totalPublished,
            'featured' => $featured, 'isFallback' => $fallback, 'contentLocale' => $contentLocale,
            'entityLocales' => Blog::published()->distinct()->pluck('locale')->all() ?: ['en'],
        ]);
    }

    public function show(string $slug, BlogPresentationService $presentation): View|RedirectResponse|Response
    {
        $blog = Blog::published()->where('slug', $slug)->first();
        if (! $blog) {
            $redirect = DB::table('blog_slug_redirects')->where('old_slug', $slug)->first();
            if ($redirect) {
                $current = Blog::published()->whereKey($redirect->blog_id)->first();
                if ($current) {
                    return redirect($current->publicUrl(), 301);
                }
            }

            return response()->view('blog.unavailable', ['title' => 'Article Not Found', 'entityLocales' => ['en'], 'versions' => collect()], 404);
        }
        $locale = app()->getLocale();
        if ($blog->locale !== $locale) {
            $version = $blog->translation_group_id ? Blog::published()->where('translation_group_id', $blog->translation_group_id)->where('locale', $locale)->first() : null;
            if ($version) {
                return redirect($version->publicUrl(), 301);
            }

            return response()->view('blog.unavailable', [
                'title' => 'Translation Unavailable', 'translationUnavailable' => true, 'entityLocales' => [],
                'versions' => Blog::published()->when($blog->translation_group_id, fn ($query) => $query->where('translation_group_id', $blog->translation_group_id), fn ($query) => $query->whereKey($blog->id))->get(),
            ], 404)->header('X-Robots-Tag', 'noindex, follow');
        }

        return view('blog.show', $presentation->data($blog));
    }
}

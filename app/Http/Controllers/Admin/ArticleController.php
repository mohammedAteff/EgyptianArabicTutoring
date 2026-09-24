<?php

namespace App\Http\Controllers\Admin;

use App\Domains\CMS\Models\Article;
use App\Domains\CMS\Services\ArticleService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        return view('admin.articles.index', [
            'title' => 'Articles',
            'articles' => Article::query()->orderByDesc('updated_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.articles.form', ['title' => 'Create article', 'article' => null]);
    }

    public function store(Request $request, ArticleService $articles): RedirectResponse
    {
        $article = $articles->create($this->validated($request), (int) $request->user('web')->id);

        return redirect()->route('admin.articles.edit', $article)->with('success', 'Article created.');
    }

    public function edit(Article $article): View
    {
        return view('admin.articles.form', ['title' => 'Edit article', 'article' => $article]);
    }

    public function update(Request $request, Article $article, ArticleService $articles): RedirectResponse
    {
        $data = $this->validated($request);
        $version = (int) $data['lock_version'];
        unset($data['lock_version']);
        $articles->update($article, $data, $version, (int) $request->user('web')->id);

        return redirect()->route('admin.articles.edit', $article)->with('success', 'Article saved.');
    }

    public function preview(Article $article): View
    {
        return view('articles.show', [
            'article' => $article,
            'preview' => true,
            'title' => $article->seo_title ?: $article->title,
            'metaDescription' => $article->seo_description ?: $article->excerpt,
        ]);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255'],
            'excerpt' => ['required', 'string', 'max:2000'],
            'body' => ['required', 'string', 'max:2000000'],
            'featured_image_path' => ['nullable', 'string', 'max:255', Rule::exists('media', 'path')->where('disk', 'public')],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])],
            'locale' => ['required', 'string', 'max:8', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'translation_group_id' => ['nullable', 'uuid'],
            'lock_version' => ['required', 'integer', 'min:1'],
        ]);
    }
}

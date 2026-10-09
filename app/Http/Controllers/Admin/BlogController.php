<?php

namespace App\Http\Controllers\Admin;

use App\Domains\CMS\Models\Blog;
use App\Domains\CMS\Models\BlogCategory;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\BlogImageService;
use App\Domains\CMS\Services\BlogPresentationService;
use App\Domains\CMS\Services\BlogService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'locale' => ['nullable', Rule::in(['en', 'fr', 'de'])], 'category' => ['nullable', 'integer'],
        ]);
        $query = Blog::query()->with('category');
        foreach (['status', 'locale', 'category'] as $field) {
            if ($request->filled($field)) {
                $query->where($field === 'category' ? 'category_id' : $field, $filters[$field]);
            }
        }
        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.trim($filters['q']).'%');
        }

        return view('admin.blog.index', [
            'title' => 'Blogs & Articles', 'blogs' => $query->orderByDesc('updated_at')->paginate(20)->withQueryString(),
            'categories' => BlogCategory::query()->orderBy('name')->get(), 'filters' => $filters,
            'publishedArticles' => Blog::published()->orderBy('title')->get(['id', 'title', 'locale']),
        ]);
    }

    public function create(Request $request): View
    {
        $data = $request->validate(['translation_of' => ['nullable', 'integer', 'exists:blogs,id'], 'locale' => ['nullable', Rule::in(['en', 'fr', 'de'])]]);
        $source = isset($data['translation_of']) ? Blog::findOrFail($data['translation_of']) : null;

        return view('admin.blog.form', $this->formData(null) + [
            'title' => 'Add Article', 'translationSource' => $source, 'initialLocale' => $data['locale'] ?? 'en',
        ]);
    }

    public function store(Request $request, BlogService $blogs): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['lock_version']);
        $blog = $blogs->create($data, (int) $request->user('web')->id);

        return redirect()->route('admin.blog.edit', $blog)->with('success', $blog->status === 'published' ? 'Your article is now published.' : 'Your draft has been saved.');
    }

    public function edit(Blog $blog): View
    {
        return view('admin.blog.form', $this->formData($blog) + ['title' => 'Edit Article']);
    }

    public function update(Request $request, Blog $blog, BlogService $blogs): RedirectResponse
    {
        $data = $this->validated($request);
        unset($data['translation_of']);
        $version = (int) $data['lock_version'];
        unset($data['lock_version']);
        $saved = $blogs->update($blog, $data, $version, (int) $request->user('web')->id);

        return redirect()->route('admin.blog.edit', $saved)->with('success', $saved->status === 'draft' ? 'Your draft has been saved.' : 'Your changes have been saved.');
    }

    public function destroy(Request $request, Blog $blog, BlogService $blogs): RedirectResponse
    {
        $data = $request->validate(['lock_version' => ['required', 'integer', 'min:1']]);
        $blogs->update($blog, ['status' => 'archived'], (int) $data['lock_version'], (int) $request->user('web')->id);

        return redirect()->route('admin.blog.index')->with('success', 'Article archived. Its content, revisions and URL history are preserved.');
    }

    public function preview(Blog $blog, BlogPresentationService $presentation): Response
    {
        return response()->view('blog.show', $presentation->data($blog, true))
            ->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    public function storeCategory(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash:ascii', 'max:120', 'unique:blog_categories,slug']]);
        BlogCategory::create($data);

        return back()->with('success', 'Article category added.');
    }

    public function updateCategory(Request $request, BlogCategory $category): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'alpha_dash:ascii', 'max:120', Rule::unique('blog_categories', 'slug')->ignore($category->id)]]);
        $category->update($data);

        return back()->with('success', 'Article category saved.');
    }

    public function destroyCategory(BlogCategory $category): RedirectResponse
    {
        if ($category->blogs()->exists()) {
            throw ValidationException::withMessages(['category' => 'This category is in use. Reassign its articles before removing it.']);
        }
        $category->delete();

        return back()->with('success', 'Unused article category removed.');
    }

    public function feature(Request $request): RedirectResponse
    {
        $data = $request->validate(['locale' => ['required', Rule::in(['en', 'fr', 'de'])], 'blog_id' => ['nullable', 'integer']]);
        if (! empty($data['blog_id']) && ! Blog::published()->where('locale', $data['locale'])->whereKey($data['blog_id'])->exists()) {
            throw ValidationException::withMessages(['blog_id' => 'Choose a published article in the selected language.']);
        }
        Setting::set('blog.featured.'.$data['locale'], (string) ($data['blog_id'] ?? ''));

        return back()->with('success', 'Featured article saved.');
    }

    /** @return array<string, mixed> */
    private function formData(?Blog $blog): array
    {
        $blog?->load(['category', 'relatedArticles', 'relatedResources', 'relatedGames', 'revisions' => fn ($query) => $query->latest('id')->limit(10)]);
        $articles = Blog::published()->when($blog, fn ($query) => $query->whereKeyNot($blog->id));
        if ($blog) {
            $articles->orWhereIn('id', $blog->relatedArticles->pluck('id'));
        }
        $resources = Resource::publicCatalogue();
        $games = Game::available();
        if ($blog) {
            $resources->orWhereIn('id', $blog->relatedResources->pluck('id'));
            $games->orWhereIn('id', $blog->relatedGames->pluck('id'));
        }

        return [
            'blog' => $blog, 'categories' => BlogCategory::query()->orderBy('name')->get(),
            'relatedArticleChoices' => $articles->orderBy('title')->get(),
            'relatedResourceChoices' => $resources->orderBy('title')->get(), 'relatedGameChoices' => $games->orderBy('title')->get(),
            'versions' => $blog ? Blog::query()->when($blog->translation_group_id, fn ($query) => $query->where('translation_group_id', $blog->translation_group_id), fn ($query) => $query->whereKey($blog->id))->get() : collect(),
            'featuredImage' => $blog ? app(BlogImageService::class)->url($blog->featured_image_path) : null,
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $intent = $request->input('intent');
        if (in_array($intent, ['draft', 'publish'], true)) {
            $request->merge(['status' => $intent === 'publish' ? 'published' : 'draft']);
        }

        return $request->validate([
            'title' => ['nullable', 'string', 'max:255'], 'slug' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'string', 'max:2000'], 'body' => ['nullable', 'string', 'max:2000000'],
            'category_id' => ['nullable', 'integer', 'exists:blog_categories,id'],
            'featured_image_path' => ['nullable', 'string', 'max:255'], 'featured_image_alt' => ['nullable', 'string', 'max:300'],
            'seo_title' => ['nullable', 'string', 'max:255'], 'seo_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'status' => ['required', Rule::in(['draft', 'published', 'archived'])], 'locale' => ['required', Rule::in(['en', 'fr', 'de'])],
            'translation_group_id' => ['nullable', 'uuid'], 'translation_of' => ['nullable', 'integer', 'exists:blogs,id'], 'lock_version' => ['required', 'integer', 'min:1'],
            'related_article_ids' => ['array'], 'related_article_ids.*' => ['integer', 'distinct', 'exists:blogs,id'],
            'related_resource_ids' => ['array'], 'related_resource_ids.*' => ['integer', 'distinct', 'exists:resources,id'],
            'related_game_ids' => ['array'], 'related_game_ids.*' => ['integer', 'distinct', 'exists:games,id'],
        ]) + ['related_article_ids' => [], 'related_resource_ids' => [], 'related_game_ids' => []];
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Domains\CMS\Models\Blog;
use App\Domains\CMS\Services\BlogService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.index', [
            'title' => 'Blog',
            'blogs' => Blog::query()->orderByDesc('updated_at')->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', ['title' => 'Add Blog Post', 'blog' => null]);
    }

    public function store(Request $request, BlogService $blogs): RedirectResponse
    {
        $blog = $blogs->create($this->validated($request), (int) $request->user('web')->id);

        return redirect()->route('admin.blog.edit', $blog)->with('success', 'Blog post created.');
    }

    public function edit(Blog $blog): View
    {
        return view('admin.blog.form', ['title' => 'Edit Blog Post', 'blog' => $blog]);
    }

    public function update(Request $request, Blog $blog, BlogService $blogs): RedirectResponse
    {
        $data = $this->validated($request);
        $version = (int) $data['lock_version'];
        unset($data['lock_version']);
        $blogs->update($blog, $data, $version, (int) $request->user('web')->id);

        return redirect()->route('admin.blog.edit', $blog)->with('success', 'Blog post saved.');
    }

    public function destroy(Blog $blog): RedirectResponse
    {
        $blog->delete();

        return redirect()->route('admin.blog.index')->with('success', 'Blog post deleted.');
    }

    public function preview(Blog $blog): View
    {
        return view('blog.show', [
            'blog' => $blog,
            'preview' => true,
            'title' => $blog->seo_title ?: $blog->title,
            'metaDescription' => $blog->seo_description ?: $blog->excerpt,
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

<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Models\Blog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        return view('blog.index', [
            'blogs' => Blog::published()->orderByDesc('published_at')->paginate(12),
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $blog = Blog::published()->where('slug', $slug)->first();
        if ($blog) {
            return view('blog.show', [
                'blog' => $blog,
                'preview' => false,
                'title' => $blog->seo_title ?: $blog->title,
                'metaDescription' => $blog->seo_description ?: $blog->excerpt,
            ]);
        }

        $redirect = DB::table('blog_slug_redirects')->where('old_slug', $slug)->first();
        if ($redirect) {
            $current = Blog::published()->whereKey($redirect->blog_id)->firstOrFail();

            return redirect()->route('blog.show', $current->slug, 301);
        }

        abort(404);
    }
}

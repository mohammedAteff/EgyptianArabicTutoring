<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Models\Article;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ArticleController extends Controller
{
    public function index(): View
    {
        return view('articles.index', [
            'articles' => Article::published()->orderByDesc('published_at')->paginate(12),
        ]);
    }

    public function show(string $slug): View|RedirectResponse
    {
        $article = Article::published()->where('slug', $slug)->first();
        if ($article) {
            return view('articles.show', [
                'article' => $article,
                'preview' => false,
                'title' => $article->seo_title ?: $article->title,
                'metaDescription' => $article->seo_description ?: $article->excerpt,
            ]);
        }

        $redirect = DB::table('article_slug_redirects')->where('old_slug', $slug)->first();
        if ($redirect) {
            $current = Article::published()->whereKey($redirect->article_id)->firstOrFail();

            return redirect()->route('articles.show', $current->slug, 301);
        }

        abort(404);
    }
}

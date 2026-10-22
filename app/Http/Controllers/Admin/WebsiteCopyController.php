<?php

namespace App\Http\Controllers\Admin;

use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Services\WebsiteCopyCatalog;
use App\Domains\CMS\Services\WebsiteCopyService;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\GameController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\ResourceController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WebsiteCopyController extends Controller
{
    public function index(Request $request, WebsiteCopyCatalog $catalog, WebsiteCopyService $copies): View
    {
        $selection = $request->validate(['tab' => ['nullable', Rule::in(['copy', 'faqs', 'pages'])], 'page' => ['nullable', Rule::in(array_keys(WebsiteCopyCatalog::PAGES))], 'locale' => ['nullable', Rule::in(['en', 'fr', 'de'])], 'revision' => ['nullable', 'integer', 'min:1']]);
        $page = $selection['page'] ?? 'home';
        $locale = $selection['locale'] ?? 'en';
        $copy = $copies->document($page, $locale);
        $revision = isset($selection['revision']) ? $copy?->revisions()->findOrFail($selection['revision']) : null;
        abort_if(isset($selection['revision']) && ! $revision, 404);
        $values = array_replace($copies->values($page, $locale, true), $revision?->content['values'] ?? []);

        return view('admin.website-copy.index', ['title' => 'Website Copy & Pages', 'tab' => $selection['tab'] ?? 'copy', 'pageKey' => $page, 'locale' => $locale,
            'pageOptions' => WebsiteCopyCatalog::PAGES, 'fields' => $catalog->fields($page), 'values' => $values,
            'englishValues' => $copies->values($page, 'en'), 'publishedValues' => $copies->values($page, $locale), 'state' => $copies->state($page, $locale),
            'copy' => $copy, 'selectedRevision' => $revision, 'revisions' => $copy?->revisions()->latest('id')->limit(30)->get() ?? collect(),
            'translationStale' => $locale !== 'en' && $copy?->published_content !== null && $copy->source_hash !== $copies->sourceHash($page),
            'faqs' => Faq::query()->with(['translations', 'revisions' => fn ($query) => $query->where('status', 'draft')->latest('id')])->orderBy('sort_order')->orderBy('id')->get(),
            'pages' => Page::query()->where('slug', '!=', 'about')->withCount('revisions')->orderBy('title')->paginate(30)->withQueryString()]);
    }

    public function update(Request $request, WebsiteCopyService $copies): RedirectResponse
    {
        $data = $request->validate(['page' => ['required', Rule::in(array_keys(WebsiteCopyCatalog::PAGES))], 'locale' => ['required', Rule::in(['en', 'fr', 'de'])],
            'action' => ['required', Rule::in(['draft', 'publish'])], 'state' => ['required', 'string', 'size:64'], 'values' => ['required', 'array', 'max:800'],
            'reviewed_source' => [Rule::when($request->input('locale') !== 'en' && $request->input('action') === 'publish', ['required', 'accepted'])]]);
        $copies->save($request->user('web'), $data['page'], $data['locale'], $data['values'], $data['action'], $data['state']);

        return redirect()->route('admin.website-copy.index', ['page' => $data['page'], 'locale' => $data['locale']])->with('success', $data['action'] === 'publish' ? 'Website copy published.' : 'Draft saved. The live website is unchanged.');
    }

    public function preview(Request $request, WebsiteCopyService $copies): Response
    {
        $data = $request->validate(['page' => ['required', Rule::in(array_keys(WebsiteCopyCatalog::PAGES))], 'locale' => ['required', Rule::in(['en', 'fr', 'de'])]]);
        abort_unless(Auth::guard('web')->check(), 403);
        $page = $data['page'];
        app()->setLocale($data['locale']);
        $original = config('public_content');
        if ($page === 'shared') {
            $copies->apply('home');
        }
        $copies->apply($page, true);
        $target = match ($page) {
            'home', 'shared' => [HomeController::class, 'index'], 'about' => [PageController::class, 'about'],
            'pricing' => [PricingController::class, 'index'], 'resources' => [ResourceController::class, 'index'],
            'games' => [GameController::class, 'index'], 'faq' => [PageController::class, 'faq'],
            'policy' => [PageController::class, 'policy'], 'blogs' => [BlogController::class, 'index'],
            'booking' => [BookingController::class, 'index'],
            default => abort(404),
        };
        try {
            $view = $page === 'policy' ? view('public.policy', ['title' => config('public_content.policy.title')]) : app()->call([app($target[0]), $target[1]]);

            return response($view->with('isPreview', true)->render())->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex, nofollow');
        } finally {
            config(['public_content' => $original]);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\WebsiteCopyService;
use App\Domains\Games\Models\Game;
use App\Domains\Resources\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return $this->renderHome();
    }

    public function preview(Request $request): View
    {
        if (! Auth::guard('web')->check()) {
            abort(403, 'Preview requires administrator authentication.');
        }

        return $this->renderHome(isPreview: true);
    }

    protected function renderHome(bool $isPreview = false): View
    {
        $sessionType = SessionType::publicBookable()->first();

        $featuredResources = Resource::query()
            ->publicCatalogue()
            ->with(['category.translations', 'translations'])
            ->orderBy('sort_order')
            ->take(3)
            ->get();

        $featuredGames = Game::query()
            ->whereIn('status', ['available', 'coming_soon'])
            ->with('translations')
            ->orderBy('sort_order')
            ->take(3)
            ->get();

        $faqs = Faq::active()
            ->where('featured', true)
            ->with('translations')
            ->take(6)
            ->get();

        $getSetting = fn (string $key, mixed $default = null) => $isPreview
            ? (Setting::get('draft:'.$key) ?? Setting::get($key, $default))
            : Setting::get($key, $default);

        $sections = config('public_content.home');
        foreach (($isPreview || request()->attributes->get('website_copy_page') !== 'home') ? config('public_content.home_controls') : [] as $index => $controls) {
            foreach ($controls as $field => $key) {
                if ($field === 'intro') {
                    $sections[$index]['blocks'][0]['text'] = $getSetting($key, $sections[$index]['blocks'][0]['text']);
                } else {
                    $sections[$index][$field] = $getSetting($key, $sections[$index][$field]);
                }
            }
        }

        return view('public.home', [
            'sessionType' => $sessionType,
            'featuredResources' => $featuredResources,
            'featuredGames' => $featuredGames,
            'faqs' => $faqs,
            'homeSections' => $sections,
            'homeCtaButton' => app(WebsiteCopyService::class)->value('home', 'ui.final_booking_button', $getSetting('home_cta_button', 'Book Your Diagnostic')),
            'isPreview' => $isPreview,
            'isFallback' => app()->getLocale() !== 'en',
            'entityLocales' => ['en'],
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Setting;
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
        $sessionType = SessionType::where('active', true)->first();

        $featuredResources = Resource::query()
            ->where('status', 'published')
            ->whereNotNull('published_at')
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
            ->with('translations')
            ->take(6)
            ->get();

        $getSetting = fn (string $key, mixed $default = null) => $isPreview
            ? (Setting::get('draft:'.$key) ?? Setting::get($key, $default))
            : Setting::get($key, $default);

        return view('public.home', [
            'sessionType' => $sessionType,
            'featuredResources' => $featuredResources,
            'featuredGames' => $featuredGames,
            'faqs' => $faqs,
            'heroTitle' => $getSetting('hero_title', 'Speak Egyptian Arabic with Confidence'),
            'heroSubtitle' => $getSetting('hero_subtitle', 'Master authentic Egyptian street and conversational Arabic through structured, 1-on-1 private lessons with an experienced native speaker.'),
            'homeApproachBadge' => $getSetting('home_approach_badge', 'The Practical Method'),
            'homeApproachTitle' => $getSetting('home_approach_title', 'Why Learn Egyptian Arabic 1-on-1?'),
            'homeApproachIntro' => $getSetting('home_approach_intro', "Most Arabic courses teach Modern Standard Arabic (MSA), which native speakers don't speak at home or on the streets. We teach you authentic spoken Egyptian Arabic as it is used today."),
            'homeResourcesBadge' => $getSetting('home_resources_badge', 'Free Workbooks & Cheatsheets'),
            'homeResourcesTitle' => $getSetting('home_resources_title', 'Grow Your Vocabulary'),
            'homeResourcesSubtitle' => $getSetting('home_resources_subtitle', 'Download free structured guides prepared specifically for Egyptian Arabic learners.'),
            'homeGamesBadge' => $getSetting('home_games_badge', 'Interactive Practice'),
            'homeGamesTitle' => $getSetting('home_games_title', 'Play Egyptian Arabic Games'),
            'homeGamesSubtitle' => $getSetting('home_games_subtitle', 'Reinforce your memory with engaging numbers, food, and street phrase exercises.'),
            'homeCtaTitle' => $getSetting('home_cta_title', 'Ready to Speak Authentic Egyptian Arabic?'),
            'homeCtaSubtitle' => $getSetting('home_cta_subtitle', 'Reserve your first private lesson in minutes. Choose your local timezone and get instant confirmation with your calendar invitation.'),
            'homeCtaButton' => $getSetting('home_cta_button', 'Book Your Session Now'),
            'isPreview' => $isPreview,
            'isFallback' => false,
            'entityLocales' => ['en', 'fr', 'de'],
        ]);
    }
}

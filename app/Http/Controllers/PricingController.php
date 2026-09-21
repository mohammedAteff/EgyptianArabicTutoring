<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Services\LocalizedUrlService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function index(Request $request, LocalizedUrlService $urlService): View
    {
        $locale = app()->getLocale();
        $bookingUrl = $urlService->getLocalizedUrl('booking', $locale);

        return view('public.pricing', [
            'title' => __('pricing.title'),
            'bookingUrl' => $bookingUrl,
            'entityLocales' => ['en', 'fr', 'de'],
        ]);
    }
}

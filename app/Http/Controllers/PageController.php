<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        $page = Page::query()
            ->where('slug', 'about')
            ->where('status', 'published')
            ->with('translations')
            ->first();

        $resolved = $page?->resolveTranslation();
        $isFallback = $resolved ? $resolved['is_fallback'] : (app()->getLocale() !== 'en');

        return view('public.about', [
            'title' => $resolved['translation']?->title ?? ($page ? $page->title : 'About Ahmad & The Teaching Methodology'),
            'page' => $page,
            'translation' => $resolved['translation'] ?? null,
            'isFallback' => $isFallback,
            'entityLocales' => $page ? $page->getAvailableLocales() : ['en'],
            'biography' => $resolved['translation']?->content ?? ($page?->content ?? Setting::get('about_biography')),
            'philosophy' => Setting::get('about_philosophy'),
            'imagePath' => $page?->og_image_path ?? Setting::get('about_image_path'),
        ]);
    }

    public function preview(Request $request, string $slug): View
    {
        if (! Auth::guard('web')->check()) {
            abort(403, 'Preview requires administrator authentication.');
        }

        $page = Page::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $draftRevision = $page->revisions()->where('status', 'draft')->latest('id')->first();
        $title = $draftRevision?->title ?? $page->title;
        $content = $draftRevision?->content['body'] ?? $page->content;
        $excerpt = $draftRevision?->content['excerpt'] ?? $page->excerpt;
        $ogImagePath = $draftRevision?->content['og_image_path'] ?? $page->og_image_path;

        $previewPage = clone $page;
        $previewPage->title = $title;
        $previewPage->content = $content;
        $previewPage->excerpt = $excerpt;
        $previewPage->og_image_path = $ogImagePath;

        if ($slug === 'about') {
            return view('public.about', [
                'title' => '[PREVIEW] '.$title,
                'page' => $previewPage,
                'biography' => Setting::get('draft:about_biography') ?? $content ?? Setting::get('about_biography'),
                'philosophy' => Setting::get('draft:about_philosophy') ?? Setting::get('about_philosophy'),
                'imagePath' => Setting::get('draft:about_image_path') ?? $ogImagePath ?? Setting::get('about_image_path'),
                'isPreview' => true,
            ]);
        }

        return view('public.page', [
            'page' => $previewPage,
            'title' => '[PREVIEW] '.$title,
            'isPreview' => true,
        ]);
    }

    public function previewAbout(Request $request): View
    {
        if (! Auth::guard('web')->check()) {
            abort(403, 'Preview requires administrator authentication.');
        }

        $page = Page::query()->where('slug', 'about')->first();
        $draftRevision = $page?->revisions()->where('status', 'draft')->latest('id')->first();
        $title = $draftRevision?->title ?? ($page ? $page->title : 'About Ahmad & The Teaching Methodology');
        $content = $draftRevision?->content['body'] ?? $page?->content;
        $ogImagePath = $draftRevision?->content['og_image_path'] ?? $page?->og_image_path;

        return view('public.about', [
            'title' => '[PREVIEW] '.$title,
            'page' => $page,
            'biography' => Setting::get('draft:about_biography') ?? $content ?? Setting::get('about_biography'),
            'philosophy' => Setting::get('draft:about_philosophy') ?? Setting::get('about_philosophy'),
            'imagePath' => Setting::get('draft:about_image_path') ?? $ogImagePath ?? Setting::get('about_image_path'),
            'isPreview' => true,
        ]);
    }

    public function faq(): View
    {
        $faqs = Faq::active()->with('translations')->orderBy('sort_order')->get();

        $locale = app()->getLocale();
        $isFallback = false;
        if ($locale !== 'en') {
            $hasAnyTranslation = $faqs->contains(fn ($f) => $f->liveTranslation($locale) !== null);
            if (! $hasAnyTranslation) {
                $isFallback = true;
            }
        }

        $availableLocales = ['en'];
        foreach (['fr', 'de'] as $loc) {
            $allTranslated = $faqs->isNotEmpty() && $faqs->every(fn ($f) => $f->liveTranslation($loc) !== null);
            if ($allTranslated) {
                $availableLocales[] = $loc;
            }
        }

        return view('public.faq', [
            'faqs' => $faqs,
            'isFallback' => $isFallback,
            'entityLocales' => $availableLocales,
            'title' => 'Frequently Asked Questions',
        ]);
    }

    public function previewFaq(Request $request): View
    {
        if (! Auth::guard('web')->check()) {
            abort(403, 'Preview requires administrator authentication.');
        }

        $faqs = Faq::query()->with(['revisions' => function ($q) {
            $q->where('status', 'draft')->latest('id');
        }])->orderBy('sort_order')->get()->map(function ($faq) {
            $draftRevision = $faq->revisions->first();
            if ($draftRevision) {
                $previewFaq = clone $faq;
                $previewFaq->question = $draftRevision->title ?? $draftRevision->content['question'] ?? $faq->question;
                $previewFaq->answer = $draftRevision->content['answer'] ?? $faq->answer;
                $previewFaq->active = $draftRevision->content['active'] ?? $faq->active;

                return $previewFaq;
            }

            return $faq;
        });

        return view('public.faq', [
            'faqs' => $faqs,
            'title' => '[PREVIEW] Frequently Asked Questions',
            'isPreview' => true,
        ]);
    }

    public function terms(): View
    {
        $locale = app()->getLocale();
        $isFallback = ($locale !== 'en');

        return view('public.terms', [
            'title' => 'Terms of Service & Booking Policies',
            'isFallback' => $isFallback,
            'entityLocales' => ['en'],
        ]);
    }

    public function privacy(): View
    {
        $locale = app()->getLocale();
        $isFallback = ($locale !== 'en');

        return view('public.privacy', [
            'title' => 'Privacy Policy & Data Protection',
            'isFallback' => $isFallback,
            'entityLocales' => ['en'],
        ]);
    }

    public function show(string $slug): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('translations')
            ->firstOrFail();

        $resolved = $page->resolveTranslation();

        return view('public.page', [
            'page' => $page,
            'translation' => $resolved['translation'],
            'isFallback' => $resolved['is_fallback'],
            'entityLocales' => $page->getAvailableLocales(),
            'title' => $resolved['translation']?->title ?? $page->title,
        ]);
    }
}

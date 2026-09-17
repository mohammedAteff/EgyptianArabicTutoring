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
            ->first();

        return view('public.about', [
            'title' => $page ? $page->title : 'About Ahmad & The Teaching Methodology',
            'page' => $page,
            'biography' => $page?->content ?? Setting::get('about_biography'),
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
        $faqs = Faq::active()->get();

        return view('public.faq', [
            'faqs' => $faqs,
            'title' => 'Frequently Asked Questions',
        ]);
    }

    public function terms(): View
    {
        return view('public.terms', [
            'title' => 'Terms of Service & Booking Policies',
        ]);
    }

    public function privacy(): View
    {
        return view('public.privacy', [
            'title' => 'Privacy Policy & Data Protection',
        ]);
    }

    public function show(string $slug): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return view('public.page', [
            'page' => $page,
            'title' => $page->title,
        ]);
    }
}

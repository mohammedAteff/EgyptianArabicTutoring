<?php

namespace App\Http\Controllers;

use App\Domains\CMS\Models\Faq;
use App\Domains\CMS\Models\Page;
use App\Domains\CMS\Models\Setting;
use App\Domains\CMS\Services\RichTextSanitizer;
use App\Domains\CMS\Services\WebsiteCopyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
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
        $copy = app(WebsiteCopyService::class);
        $hasCopy = request()->attributes->get('website_copy_preview') || $copy->hasTranslation('about', app()->getLocale()) || $copy->document('about', 'en')?->published_content !== null;

        return view('public.about', [
            'title' => ($resolved['translation'] ?? null)?->getAttribute('title') ?? 'About Abdallah',
            'metaDescription' => Str::limit(trim(strip_tags(app(RichTextSanitizer::class)->sanitize((string) config('public_content.about.0.blocks.0.text')))), 160),
            'page' => $page,
            'translation' => $resolved['translation'] ?? null,
            'isFallback' => $isFallback,
            'entityLocales' => $page ? $page->getAvailableLocales() : ['en'],
            'biography' => $hasCopy ? null : (($resolved && $resolved['effective_locale'] !== 'en')
                ? $resolved['translation']->getAttribute('content')
                : (Setting::get('about_biography') ?: (($resolved['translation'] ?? null)?->getAttribute('content') ?? $page?->content))),
            'biographyLocale' => $resolved['effective_locale'] ?? 'en',
            'biographyHeading' => ($resolved && $resolved['effective_locale'] !== 'en') ? $resolved['translation']->getAttribute('title') : null,
            'philosophy' => $hasCopy ? null : Setting::get('about_philosophy'),
            'imagePath' => $hasCopy ? request()->attributes->get('website_copy_tree')['image'] : ($page->og_image_path ?? Setting::get('about_image_path')),
        ]);
    }

    public function preview(Request $request, string $slug, RichTextSanitizer $sanitizer): View
    {
        if (! Auth::guard('web')->check()) {
            abort(403, 'Preview requires administrator authentication.');
        }

        $page = Page::query()
            ->where('slug', $slug)
            ->firstOrFail();

        $draftRevision = $page->revisions()->where('status', 'draft')->latest('id')->first();
        $title = $draftRevision->title ?? $page->title;
        $content = $sanitizer->sanitize((string) ($draftRevision?->content['body'] ?? $page->content));
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
                'biography' => Setting::get('draft:about_biography') ?? $content,
                'philosophy' => Setting::get('draft:about_philosophy') ?? Setting::get('about_philosophy'),
                'imagePath' => Setting::get('draft:about_image_path') ?? $ogImagePath ?? Setting::get('about_image_path'),
                'isPreview' => true,
            ]);
        }

        return view('public.page', [
            'page' => $previewPage,
            'content' => $content,
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
        $title = $draftRevision->title ?? ($page ? $page->title : 'About Abdallah & The Teaching Methodology');
        $content = $draftRevision?->content['body'] ?? $page?->content;
        $ogImagePath = $draftRevision?->content['og_image_path'] ?? $page?->og_image_path;

        return view('public.about', [
            'title' => '[PREVIEW] '.$title,
            'page' => $page,
            'biography' => Setting::get('draft:about_biography') ?? $content,
            'philosophy' => Setting::get('draft:about_philosophy') ?? Setting::get('about_philosophy'),
            'imagePath' => Setting::get('draft:about_image_path') ?? $ogImagePath ?? Setting::get('about_image_path'),
            'isPreview' => true,
        ]);
    }

    public function faq(Request $request): View
    {
        $validator = Validator::make($request->only(['q', 'topic']), ['q' => ['nullable', 'string', 'max:200'], 'topic' => ['nullable', 'string', 'max:100']]);
        $filterErrors = $validator->errors();
        $search = $filterErrors->has('q') ? '' : trim((string) $request->input('q', ''));
        $topic = $filterErrors->has('topic') ? '' : (string) $request->input('topic', '');
        $allFaqs = Faq::active()->with(['translations' => fn ($query) => $query->whereIn('status', ['published', 'stale'])])->get();
        $availableTopics = $allFaqs->pluck('topic')->filter(fn (?string $value): bool => filled($value))->unique();
        $canonicalTopics = collect(config('public_content.faq_topics', []));
        $topics = $canonicalTopics->intersect($availableTopics)
            ->merge($availableTopics->diff($canonicalTopics)->sort(SORT_NATURAL | SORT_FLAG_CASE))->values();
        $allFaqs = $allFaqs->sortBy(fn (Faq $faq): int => $topics->search($faq->topic, true) === false ? $topics->count() : $topics->search($faq->topic, true));
        $normalizedSearch = Str::lower(Str::ascii($search));
        $answerOnlyMatches = [];
        $faqs = $allFaqs->filter(function (Faq $faq) use ($topic, $normalizedSearch, &$answerOnlyMatches): bool {
            if ($topic !== '' && $faq->topic !== $topic) {
                return false;
            }
            if ($normalizedSearch === '') {
                return true;
            }
            $translation = $faq->localizedContent();
            $question = Str::lower(Str::ascii($translation['question']));
            $answer = Str::lower(Str::ascii($translation['answer']));
            if (! str_contains($question, $normalizedSearch) && str_contains($answer, $normalizedSearch)) {
                $answerOnlyMatches[$faq->id] = true;
            }

            return str_contains(
                $question."\n".$answer,
                $normalizedSearch,
            );
        });

        $locale = app()->getLocale();
        $isFallback = false;
        if ($locale !== 'en') {
            $allTranslated = $allFaqs->every(fn (Faq $faq): bool => $faq->localizedContent($locale)['locale'] === $locale);
            if (! $allTranslated) {
                $isFallback = true;
            }
        }

        $availableLocales = ['en'];
        foreach (['fr', 'de'] as $loc) {
            $allTranslated = $allFaqs->every(fn (Faq $faq): bool => $faq->localizedContent($loc)['locale'] === $loc);
            if ($allTranslated) {
                $availableLocales[] = $loc;
            }
        }

        return view('public.faq', [
            'faqs' => $faqs,
            'isFallback' => $isFallback,
            'entityLocales' => $availableLocales,
            'title' => 'Frequently Asked Questions',
            'search' => $search,
            'topic' => $topic,
            'topics' => $topics,
            'publishedCount' => $allFaqs->count(),
            'topicHasQuestions' => $topic === '' || $allFaqs->contains(fn (Faq $faq): bool => $faq->topic === $topic),
            'filterErrors' => $filterErrors,
            'answerOnlyMatches' => $answerOnlyMatches,
            'noIndex' => $request->hasAny(['q', 'topic']),
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
                $draftContent = $draftRevision->getAttribute('content');
                $previewFaq->topic = array_key_exists('topic', $draftContent) ? $draftContent['topic'] : $faq->topic;
                $previewFaq->featured = $draftRevision->content['featured'] ?? $faq->featured;

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
        $page = Page::query()
            ->where('slug', 'terms')
            ->where('status', 'published')
            ->with('translations')
            ->first();

        $resolved = $page?->resolveTranslation();
        if (($resolved['is_stale'] ?? false) && app()->getLocale() !== 'en') {
            $resolved = $page->resolveTranslation('en');
            $resolved['is_fallback'] = true;
        }
        $isFallback = $resolved ? $resolved['is_fallback'] : (app()->getLocale() !== 'en');

        return view('public.terms', [
            'title' => $resolved['translation']->title ?? ($page ? $page->title : 'Terms of Service & Booking Policy'),
            'page' => $page,
            'translation' => $resolved['translation'] ?? null,
            'content' => $resolved['translation']->content ?? ($page->content ?? null),
            'isFallback' => $isFallback,
            'entityLocales' => $page ? array_values(array_unique(array_merge(['en'], $page->translations->where('status', 'published')->pluck('locale')->all()))) : ['en'],
        ]);
    }

    public function privacy(): View
    {
        $page = Page::query()
            ->where('slug', 'privacy')
            ->where('status', 'published')
            ->with('translations')
            ->first();

        $resolved = $page?->resolveTranslation();
        $isFallback = $resolved ? $resolved['is_fallback'] : (app()->getLocale() !== 'en');

        return view('public.privacy', [
            'title' => $resolved['translation']->title ?? ($page ? $page->title : 'Privacy Policy & Data Ethics'),
            'page' => $page,
            'translation' => $resolved['translation'] ?? null,
            'content' => $resolved['translation']->content ?? ($page->content ?? null),
            'isFallback' => $isFallback,
            'entityLocales' => $page ? $page->getAvailableLocales() : ['en'],
        ]);
    }

    public function show(string $slug, RichTextSanitizer $sanitizer): View
    {
        $page = Page::query()
            ->where('slug', $slug)
            ->where('status', 'published')
            ->with('translations')
            ->firstOrFail();

        $resolved = $page->resolveTranslation();
        $content = $sanitizer->sanitize((string) ($resolved['translation']->content ?? $page->content));

        return view('public.page', [
            'page' => $page,
            'translation' => $resolved['translation'],
            'content' => $content,
            'isFallback' => $resolved['is_fallback'],
            'entityLocales' => $page->getAvailableLocales(),
            'title' => $resolved['translation']->title ?? $page->title,
        ]);
    }
}

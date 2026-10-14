
@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div lang="{{ $copyLocale ?? 'en' }}" class="mx-auto max-w-6xl space-y-20 px-4 py-12 sm:px-6 sm:py-20 lg:px-8">
    @foreach($homeSections as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        @php
            $legacySectionId = [0 => 'hero', 2 => 'curriculum', 3 => 'pricing', 6 => 'tutor-bio'][$index] ?? null;
        @endphp
        @if($legacySectionId)<div id="{{ $legacySectionId }}" class="scroll-mt-24">@endif
        <section class="space-y-8" id="home-section-{{ $index + 1 }}" @if(in_array($index, [0, 2, 3, 6], true)) data-section-id="{{ [0 => 'hero', 2 => 'curriculum', 3 => 'pricing', 6 => 'tutor-bio'][$index] }}" @endif>
            @if($index === 0)
                <div class="grid items-center gap-10 lg:grid-cols-[1.5fr_1fr]">
                    <div>
                        <x-public-copy-section :section="$section" :level="1" />
                        <div class="mt-8 flex flex-wrap gap-4">
                            <a href="{{ website_link('home', 'booking', localized_url('booking')) }}" data-cta="booking" class="rounded-xl bg-terracotta-500 px-6 py-3 font-bold text-white hover:bg-terracotta-600">{{ website_text('home', 'Book Your Diagnostic') }}</a>
                            <a href="{{ website_link('home', 'pricing', localized_url('pricing')) }}" class="rounded-xl border border-stone-300 bg-white px-6 py-3 font-semibold">{{ website_text('home', 'Explore Lessons & Pricing') }}</a>
                        </div>
                    </div>
                    <div class="rounded-3xl bg-nile-900 p-8 text-white sm:p-10">
                        <p class="text-sm uppercase tracking-widest text-stone-300">{{ website_text('home', 'Your first step') }}</p>
                        <h2 class="mt-5 text-3xl font-bold">{{ website_text('home', 'Diagnostic & Learning Roadmap') }}</h2>
                        <p class="mt-6 text-4xl font-bold">$25 <span class="text-base font-normal text-stone-300">{{ website_text('home', '· 60 minutes') }}</span></p>
                        <p class="mt-6 leading-relaxed text-stone-200">{{ website_text('home', 'A friendly introduction, level assessment and learning consultation, followed by a personalized written roadmap.') }}</p>
                        <p class="mt-6 text-sm text-stone-300">{{ website_text('home', 'No obligation to purchase a package afterward.') }}</p>
                    </div>
                </div>
            @else
                <x-public-copy-section :section="$section" />
            @endif
            @if($index === 1)
                <div class="grid gap-5 sm:grid-cols-3">
                    @foreach(config('public_content.home_expressions') as $expression)
                        <div class="rounded-2xl border border-stone-200 bg-white p-7 text-center">
                            <p class="font-cairo text-4xl text-nile-800"><bdi dir="rtl" lang="ar">{{ $expression['arabic'] }}</bdi></p>
                            <p class="mt-4 font-semibold">{{ $expression['transliteration'] }}</p>
                            <p class="mt-2 text-sm text-stone-500">{{ $expression['meaning'] }}</p>
                        </div>
                    @endforeach
                </div>
                <a href="{{ website_link('home', 'resources', localized_url('resources')) }}" class="font-bold text-terracotta-600 underline underline-offset-4">{{ website_text('home', 'Explore Free Arabic Resources') }}</a>
            @elseif($index === 2)
                <div class="grid gap-6 lg:grid-cols-3">
                    @foreach(config('public_content.home_approach_cards') as $card)
                        <article class="rounded-2xl border border-stone-200 bg-white p-7">
                            <h3 class="text-xl font-bold">{{ $card['title'] }}</h3>
                            @foreach($card['paragraphs'] as $paragraph)<p class="mt-4 leading-relaxed text-stone-600">{{ $paragraph }}</p>@endforeach
                        </article>
                    @endforeach
                </div>
            @elseif($index === 3 || $index === 8)
                <a href="{{ website_link('home', 'booking', localized_url('booking')) }}" data-cta="booking" class="inline-flex rounded-xl bg-terracotta-500 px-6 py-3 font-bold text-white hover:bg-terracotta-600">{{ $index === 8 ? $homeCtaButton : 'Book Your Diagnostic' }}</a>
                @if($index === 3)<a href="{{ website_link('home', 'pricing', localized_url('pricing')) }}" class="ml-4 inline-flex py-3 font-bold text-terracotta-600">{{ website_text('home', 'View Lesson Packages') }}</a>@endif
            @elseif($index === 4)
                @if($featuredResources->isNotEmpty())
                    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($featuredResources as $resource)<x-public-resource-card :resource="$resource" />@endforeach</div>
                @else
                    <p class="rounded-xl border border-stone-200 bg-white p-5 text-stone-600">{{ website_text('home', 'New learning materials will appear here when published.') }}</p>
                @endif
                <a href="{{ website_link('home', 'resources', localized_url('resources')) }}" class="font-bold text-terracotta-600 underline underline-offset-4">{{ website_text('home', 'Explore Free Resources') }}</a>
            @elseif($index === 5)
                <div class="grid gap-6 sm:grid-cols-2">@foreach($featuredGames as $game)<x-public-game-card :game="$game" />@endforeach</div>
                <a href="{{ website_link('home', 'games', localized_url('games')) }}" class="font-bold text-terracotta-600 underline underline-offset-4">{{ website_text('home', 'Explore Arabic Games') }}</a>
            @elseif($index === 6)
                <a href="{{ website_link('home', 'about', localized_url('about')) }}" class="font-bold text-terracotta-600 underline underline-offset-4">{{ website_text('home', 'More About Abdallah') }}</a>
            @elseif($index === 7)
                <div class="space-y-3">@foreach($faqs as $faq)
                    @php $faqTranslation = $faq->liveTranslation(); @endphp
                    <details class="rounded-xl border border-stone-200 bg-white p-5">
                        <summary class="cursor-pointer font-bold" lang="{{ $faqTranslation?->locale ?? 'en' }}">{{ $faqTranslation?->question ?? $faq->question }}</summary>
                        <p class="mt-4 whitespace-pre-line leading-relaxed text-stone-600" lang="{{ $faqTranslation?->locale ?? 'en' }}">{{ $faqTranslation?->answer ?? $faq->answer }}</p>
                    </details>
                @endforeach</div>
                <a href="{{ website_link('home', 'faq', localized_url('faq')) }}" class="font-bold text-terracotta-600 underline underline-offset-4">{{ website_text('home', 'View All Frequently Asked Questions') }}</a>
            @endif
        </section>
        @if($legacySectionId)</div>@endif
    @endforeach
</div>
@endsection

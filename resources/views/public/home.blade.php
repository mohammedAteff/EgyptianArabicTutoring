@extends('layouts.public')
@section('body-class', 'studio-home-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div lang="{{ $copyLocale ?? 'en' }}" class="studio-home">
    @foreach($homeSections as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        @php
            $legacySectionId = [0 => 'hero', 2 => 'curriculum', 3 => 'pricing', 6 => 'tutor-bio'][$index] ?? null;
        @endphp
        @if($legacySectionId)<div id="{{ $legacySectionId }}">@endif
        <section @class(['studio-section', 'studio-hero' => $index === 0, 'studio-expressions' => $index === 1, 'studio-learning' => $index === 2, 'studio-discovery' => in_array($index, [4, 5], true), 'studio-about' => $index === 6, 'studio-faq' => $index === 7, 'studio-invitation' => $index === 8]) id="home-section-{{ $index + 1 }}" @if($legacySectionId) data-section-id="{{ $legacySectionId }}" @endif>
            @if($index === 0)
                <div class="hero-main">
                    <p class="studio-eyebrow">{{ __($section['badge']) }}</p>
                    <h1 class="hero-title">
                        @if($section['title'] === 'Learn Arabic with a plan built around you.')
                            {{ __('Learn Arabic with') }}<br class="hero-title-break">
                            {{ __('a plan') }} <span class="hero-title-accent">{{ __('built around you.') }}</span>
                        @else
                            {{ __($section['title']) }}
                        @endif
                    </h1>
                    <p class="hero-offering">{{ website_text('home', 'Egyptian Arabic · Modern Standard Arabic (MSA)') }}<span>{{ website_text('home', 'Private online lessons') }}</span></p>
                    <div class="hero-lead"><x-public-copy-block :block="$section['blocks'][0]" /></div>
                    <div class="hero-actions">
                        <a href="{{ website_link('home', 'booking', localized_url('booking')) }}" data-cta="booking" class="studio-button hero-booking">{{ website_text('home', 'Book Your Diagnostic') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                        <a href="{{ website_link('home', 'pricing', localized_url('pricing')) }}" class="hero-secondary">{{ website_text('home', 'View Learning Options') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                    </div>
                    <ul class="hero-facts" aria-label="{{ website_text('home', 'Your first step') }}">
                        <li><span>{{ website_text('home', 'Diagnostic & Learning Roadmap') }}</span></li>
                        <li class="hero-fact-value"><strong class="hero-fee"><span class="hero-currency">$</span>25</strong></li>
                        <li class="hero-fact-value hero-duration">
                            <x-public-studio-icon name="stopwatch" />
                            @php $durationLabel = website_text('home', '60 min'); @endphp
                            <strong>@if($durationLabel === '60 min')60 <span class="hero-duration-unit">{{ __('min') }}</span>@else{{ $durationLabel }}@endif</strong>
                        </li>
                        <li><span>{{ website_text('home', 'Personalized first step') }}</span></li>
                    </ul>
                    <details class="hero-context studio-read-more">
                        <summary>{{ __('About your first step') }}</summary>
                        <div class="hero-context-copy">
                            <div><x-public-copy-block :block="$section['blocks'][1]" /></div>
                            <div class="hero-first-step">
                                <x-public-copy-block :block="$section['blocks'][2]" />
                                <p class="hero-reassurance">{{ website_text('home', 'No obligation to purchase a package afterward.') }}</p>
                            </div>
                        </div>
                    </details>
                </div>
                <figure class="hero-artwork">
                    <img src="{{ asset('images/home/cairo-learning-studio-840.webp') }}" srcset="{{ asset('images/home/cairo-learning-studio-560.webp') }} 560w, {{ asset('images/home/cairo-learning-studio-840.webp') }} 840w, {{ asset('images/home/cairo-learning-studio-1122.webp') }} 1122w" sizes="(max-width: 900px) 100vw, 50vw" width="1122" height="1402" alt="{{ website_text('home', 'Cairo-inspired learning-studio artwork with books, a notebook and tea overlooking the Nile.') }}" fetchpriority="high">
                </figure>
                @if(count($section['blocks']) > 3 || !empty($section['extra_content']))
                    <div class="hero-extra article-copy">
                        @foreach(array_slice($section['blocks'], 3) as $block)<x-public-copy-block :block="$block" />@endforeach
                        @if(!empty($section['extra_content'])){!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($section['extra_content']) !!}@endif
                    </div>
                @endif
            @elseif($index === 1)
                <div>
                    <x-public-copy-section :section="$section" class="studio-copy" />
                    <a href="{{ website_link('home', 'resources', localized_url('resources')) }}" class="studio-link">{{ website_text('home', 'Explore Free Arabic Resources') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                </div>
                <div class="studio-expression-list">
                    @foreach(config('public_content.home_expressions') as $expression)
                        <div class="studio-expression">
                            <img src="{{ asset('images/home/expression-'.['mashi', 'yalla', 'begad'][$loop->index].'-640.webp') }}" srcset="{{ asset('images/home/expression-'.['mashi', 'yalla', 'begad'][$loop->index].'-320.webp') }} 320w, {{ asset('images/home/expression-'.['mashi', 'yalla', 'begad'][$loop->index].'-640.webp') }} 640w" sizes="(max-width: 767px) 44vw, (max-width: 900px) 30vw, (min-width: 1440px) 250px, 18vw" width="640" height="427" alt="" loading="lazy" decoding="async">
                            <div class="studio-expression-copy">
                            <bdi dir="rtl" lang="ar">{{ $expression['arabic'] }}</bdi>
                            <p class="transliteration">{{ $expression['transliteration'] }}</p>
                            <p class="meaning">{{ $expression['meaning'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @elseif($index === 2)
                <x-public-copy-section :section="$section" class="studio-copy studio-introduction" />
                <div class="studio-approach-list">
                    @foreach(config('public_content.home_approach_cards') as $card)
                        <article class="studio-approach">
                            <span class="studio-approach-symbol" aria-hidden="true">
                                <x-public-studio-illustration :name="['goals', 'explanations', 'continuity'][$loop->index]" />
                            </span>
                            <h3>{{ $card['title'] }}</h3>
                            <div class="studio-approach-copy">
                                <p>{{ $card['paragraphs'][0] }}</p>
                                @if(count($card['paragraphs']) > 1)
                                    <details class="studio-read-more">
                                        <summary>{{ __(['How your plan adapts', 'Inside your lessons', 'Between lessons'][$loop->index]) }}<span class="sr-only"> — {{ $card['title'] }}</span></summary>
                                        <div>
                                            @foreach(array_slice($card['paragraphs'], 1) as $paragraph)<p>{{ $paragraph }}</p>@endforeach
                                        </div>
                                    </details>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @elseif($index === 3)
                <div class="studio-diagnostic">
                    <div class="studio-diagnostic-intro">
                        <x-public-copy-section :section="array_replace($section, ['blocks' => array_slice($section['blocks'], 0, 2)])" :preview-blocks="1" :details-label="__('About the diagnostic')" class="studio-copy" />
                        <div class="studio-diagnostic-price">
                            <p class="studio-eyebrow">{{ website_text('home', 'Diagnostic & Learning Roadmap') }}</p>
                            <div class="studio-price"><x-public-copy-block :block="$section['blocks'][9]" /></div>
                            <div class="studio-actions">
                                <a href="{{ website_link('home', 'booking', localized_url('booking')) }}" data-cta="booking" class="studio-button">{{ website_text('home', 'Book Your Diagnostic') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                                <a href="{{ website_link('home', 'pricing', localized_url('pricing')) }}" class="studio-link">{{ website_text('home', 'View Lesson Packages') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                            </div>
                        </div>
                    </div>
                    <div class="mt-8"><x-public-copy-block :block="$section['blocks'][2]" /></div>
                    <div class="studio-roadmap-steps">
                        @foreach([3, 5, 7] as $blockIndex)
                            <div class="studio-roadmap-step">
                                <x-public-studio-illustration :name="['conversation', 'assessment', 'roadmap'][$loop->index]" class="studio-roadmap-illustration" />
                                <x-public-copy-block :block="$section['blocks'][$blockIndex]" />
                                <x-public-copy-block :block="$section['blocks'][$blockIndex + 1]" />
                            </div>
                        @endforeach
                    </div>
                    <div class="studio-credit-copy">
                        @foreach(array_slice($section['blocks'], 10) as $block)
                            <x-public-copy-block :block="$block" />
                        @endforeach
                    </div>
                </div>
            @elseif($index === 4)
                <div>
                    <x-public-copy-section :section="$section" :preview-blocks="1" :details-label="__('About the learning materials')" class="studio-copy" />
                    <a href="{{ website_link('home', 'resources', localized_url('resources')) }}" class="studio-link">{{ website_text('home', 'Explore Free Resources') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                </div>
                <div class="studio-discovery-preview">
                    @forelse($featuredResources as $resource)
                        <x-public-resource-card :resource="$resource" />
                    @empty
                        <div class="studio-resource-empty">
                            <figure class="studio-workbook"><img src="{{ asset('images/home/alphabet-workbook-preview.webp') }}" width="540" height="764" alt="{{ website_text('home', 'Preview of an Arabic alphabet workbook from Abdallah’s teaching materials.') }}" loading="lazy" decoding="async"><figcaption>{{ website_text('home', 'From the teaching studio') }}</figcaption></figure>
                            <p>{{ website_text('home', 'New learning materials will appear here when published.') }}</p>
                        </div>
                    @endforelse
                </div>
            @elseif($index === 5)
                <div>
                    <x-public-copy-section :section="$section" :preview-blocks="1" :details-label="__('How to play 6-Word Story')" class="studio-copy" />
                    <a href="{{ website_link('home', 'games', localized_url('games')) }}" class="studio-link">{{ website_text('home', 'Explore Arabic Games') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                </div>
                <div class="studio-discovery-preview">
                    @foreach($featuredGames as $game)<x-public-game-card :game="$game" :compact="true" class="studio-game-preview" />@endforeach
                </div>
            @elseif($index === 6)
                <div>
                    <x-public-copy-section :section="$section" :preview-blocks="3" :details-label="__('My approach to teaching')" class="studio-copy" />
                    <a href="{{ website_link('home', 'about', localized_url('about')) }}" class="studio-link">{{ website_text('home', 'More About Abdallah') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                </div>
                <figure class="studio-about-material">
                    <img src="{{ asset('images/home/alphabet-workbook-preview.webp') }}" width="540" height="764" alt="{{ website_text('home', 'Preview of an Arabic alphabet workbook from Abdallah’s teaching materials.') }}" loading="lazy" decoding="async">
                    <figcaption>{{ website_text('home', 'An Arabic alphabet workbook from my teaching materials.') }}</figcaption>
                </figure>
            @elseif($index === 7)
                <div>
                    <x-public-copy-section :section="$section" :preview-blocks="1" class="studio-copy" />
                    <a href="{{ website_link('home', 'faq', localized_url('faq')) }}" class="studio-link">{{ website_text('home', 'View All Frequently Asked Questions') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                </div>
                <div class="studio-faq-list">
                    @foreach($faqs as $faq)
                        @php $faqTranslation = $faq->localizedContent(); @endphp
                        <details>
                            <summary lang="{{ $faqTranslation['locale'] }}">{{ $faqTranslation['question'] }}</summary>
                            <p lang="{{ $faqTranslation['locale'] }}">{{ $faqTranslation['answer'] }}</p>
                        </details>
                    @endforeach
                </div>
            @else
                <x-public-copy-section :section="array_replace($section, ['blocks' => array_slice($section['blocks'], 0, 3)])" class="studio-copy" />
                <div class="studio-invitation-action">
                    <div class="studio-actions">
                        <a href="{{ website_link('home', 'booking', localized_url('booking')) }}" data-cta="booking" class="studio-button">{{ $homeCtaButton }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                    </div>
                    <div class="studio-invitation-terms">
                        @foreach(array_slice($section['blocks'], 3) as $block)
                            <x-public-copy-block :block="$block" />
                        @endforeach
                    </div>
                </div>
            @endif
        </section>
        @if($legacySectionId)</div>@endif
    @endforeach
</div>
@endsection

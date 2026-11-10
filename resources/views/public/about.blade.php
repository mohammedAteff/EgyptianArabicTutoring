
@extends('layouts.public')
@section('body-class', 'studio-about-profile-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php
    $imageUrl = app(\App\Domains\CMS\Services\AboutImageService::class)->url($imagePath ?? null);
    $visibleSections = array_filter(config('public_content.about'), fn (array $section): bool => $section['_visible'] ?? true);
    $primarySection = isset($visibleSections[0]) ? 0 : array_key_first($visibleSections);
    $compositions = [0 => 'introduction', 1 => 'curiosity', 2 => 'approach', 3 => 'experience', 4 => 'learners', 5 => 'invitation'];
@endphp
<div lang="{{ $copyLocale ?? 'en' }}" class="about-profile">
    @foreach($visibleSections as $identity => $section)
        @php
            $isBiography = $identity === 0 && filled($biography ?? null);
            $isPhilosophy = $identity === 2 && filled($philosophy ?? null);
            $sectionLocale = $isBiography ? ($biographyLocale ?? 'en') : ($copyLocale ?? 'en');
            $heading = $isBiography && filled($biographyHeading ?? null) ? $biographyHeading : $section['title'];
        @endphp
        <section id="about-section-{{ $identity + 1 }}" class="about-profile-section about-profile-{{ $compositions[$identity] ?? 'narrative' }}" aria-labelledby="about-heading-{{ $identity + 1 }}" lang="{{ $sectionLocale }}">
            <div @class(['about-profile-inner', 'about-profile-with-portrait' => $identity === 0 && $imageUrl])>
                <header class="about-profile-heading">
                    <p class="about-profile-eyebrow">{{ __($section['badge']) }}</p>
                    @if($identity === $primarySection)
                        <h1 id="about-heading-{{ $identity + 1 }}">{{ $heading }}</h1>
                    @else
                        <h2 id="about-heading-{{ $identity + 1 }}">{{ $heading }}</h2>
                    @endif
                </header>
                <div class="about-profile-content" lang="{{ $isPhilosophy ? 'en' : $sectionLocale }}">
                    <x-about-editorial-blocks :blocks="$section['blocks']" :body="$isBiography ? $biography : ($isPhilosophy ? $philosophy : null)" :variant="match ($identity) { 0 => 'introduction', 2 => 'principles', 3 => 'experience', 4 => 'contexts', 5 => 'offer', default => 'narrative' }">
                        @if($identity === 5)
                            <div class="about-profile-actions">
                                <a href="{{ website_link('about', 'booking', localized_url('booking')) }}" data-cta="booking" class="studio-button">{{ website_text('about', 'Book Your Diagnostic') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                                <a href="{{ website_link('about', 'pricing', localized_url('pricing')) }}" class="studio-link">{{ website_text('about', 'Explore Lessons & Pricing') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                            </div>
                        @endif
                    </x-about-editorial-blocks>
                </div>
                @if($identity === 0 && $imageUrl)
                    <figure class="about-profile-portrait">
                        <img src="{{ $imageUrl }}" alt="{{ __('Abdallah') }}" width="640" height="800" fetchpriority="high" decoding="async">
                    </figure>
                @endif
                @if(!empty($section['extra_content']))
                    <div class="about-profile-prose about-profile-extra" lang="{{ $copyLocale ?? 'en' }}">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($section['extra_content']) !!}</div>
                @endif
            </div>
        </section>
    @endforeach
</div>
@endsection


@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php
    $imageUrl = app(\App\Domains\CMS\Services\AboutImageService::class)->url($imagePath ?? null);
@endphp
<div lang="{{ $copyLocale ?? 'en' }}" class="mx-auto max-w-4xl space-y-16 px-4 py-12 sm:px-6 lg:px-8">
    @foreach(config('public_content.about') as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        <section>
            @if($index === 0 && !empty($biography))
                @php if ($biographyHeading ?? null) { $section['title'] = $biographyHeading; } @endphp
                <x-public-copy-section :section="$section" :level="1" :show-blocks="false" :locale="$biographyLocale ?? 'en'" />
                <div lang="{{ $index === 0 ? ($biographyLocale ?? 'en') : 'en' }}" class="article-copy mt-6 whitespace-pre-line">{!! preg_match('/<\\/?[a-z][^>]*>/i', $biography) ? app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($biography) : nl2br(e($biography)) !!}</div>
            @elseif($index === 2 && !empty($philosophy))
                <x-public-copy-section :section="$section" :show-blocks="false" />
                <div class="article-copy mt-6 whitespace-pre-line">{!! preg_match('/<\\/?[a-z][^>]*>/i', $philosophy) ? app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($philosophy) : nl2br(e($philosophy)) !!}</div>
            @else
                <x-public-copy-section :section="$section" :level="$index === 0 ? 1 : 2" />
            @endif
            @if($index === 0 && $imageUrl)
                <img src="{{ $imageUrl }}" alt="Abdallah" class="mt-8 max-h-96 rounded-2xl object-cover" loading="lazy">
            @endif
            @if($index === 5)
                <div class="mt-7 flex flex-wrap gap-4">
                    <a href="{{ website_link('about', 'booking', localized_url('booking')) }}" data-cta="booking" class="rounded-xl bg-terracotta-500 px-6 py-3 font-bold text-white hover:bg-terracotta-600">{{ website_text('about', 'Book Your Diagnostic') }}</a>
                    <a href="{{ website_link('about', 'pricing', localized_url('pricing')) }}" class="rounded-xl border border-stone-300 bg-white px-6 py-3 font-semibold">{{ website_text('about', 'Explore Lessons & Pricing') }}</a>
                </div>
            @endif
        </section>
    @endforeach
</div>
@endsection

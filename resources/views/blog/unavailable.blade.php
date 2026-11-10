@extends('layouts.public', ['noIndex' => true])
@section('body-class', 'studio-blogs-page studio-blog-unavailable')
@section('content')
@php($copyLocale = app(\App\Domains\CMS\Services\WebsiteCopyService::class)->document('blogs', app()->getLocale())?->published_content !== null ? app()->getLocale() : 'en')
<section lang="{{ $copyLocale }}" class="blogs-page blogs-unavailable">
    <p class="blogs-eyebrow">{{ website_text('blogs', 'The Arabic Learning Blog') }}</p>
    <h1>{{ __(($translationUnavailable ?? false) ? website_text('blogs', 'Translation Unavailable') : website_text('blogs', 'We Couldn\'t Find That Article.')) }}</h1>
    <p>{{ ($translationUnavailable ?? false) ? website_text('blogs', 'This article hasn\'t been published in the selected language yet.') : website_text('blogs', 'The article may have been moved, unpublished, or removed. You can return to the blog to explore other available articles.') }}</p>
    @if($versions->isNotEmpty())<nav aria-label="{{ website_text('blogs', 'Available published versions') }}" class="blog-versions">@foreach($versions as $version)<a href="{{ $version->publicUrl() }}" hreflang="{{ $version->locale }}">{{ website_text('blogs', 'Read in') }} <span lang="{{ $version->locale }}">{{ ['en'=>'English','fr'=>'Français','de'=>'Deutsch'][$version->locale] }}</span></a>@endforeach</nav>@endif
    <a href="{{ localized_url('blogs') }}" class="studio-button studio-button-primary">{{ website_text('blogs', 'Browse Articles') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
</section>
@endsection

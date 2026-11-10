@extends('layouts.public')
@section('body-class', 'studio-blogs-page studio-blog-article')
@section('content')
<article lang="{{ $blog->locale }}" class="blogs-page blog-article">
    <nav lang="{{ $copyLocale }}" aria-label="{{ website_text('blogs', 'Breadcrumb') }}" class="blog-breadcrumb"><a href="{{ localized_url('blogs') }}">{{ website_text('blogs', 'Blogs') }}</a><span aria-hidden="true">/</span><span lang="{{ $blog->locale }}">{{ __($blog->title ?: website_text('blogs', 'Untitled draft')) }}</span></nav>
    @if($preview ?? false)<p lang="{{ $copyLocale }}" class="blog-preview-notice">{{ website_text('blogs', 'Private preview of the saved article. Unsaved edits are not shown.') }}</p>@endif
    <header class="blog-article-header">
        @if($blog->category)<p class="blogs-eyebrow">{{ $blog->category->name }}</p>@endif
        <h1>{{ __($blog->title ?: website_text('blogs', 'Untitled draft')) }}</h1>
        @if($blog->excerpt)<p class="blog-article-excerpt">{{ $blog->excerpt }}</p>@endif
        <div lang="{{ $copyLocale }}" class="blog-article-meta">
            <span>{{ website_text('blogs', 'Written by: Abdallah') }}</span>
            @if($blog->published_at)<span>{{ website_text('blogs', 'Published:') }} <time datetime="{{ $blog->published_at->toIso8601String() }}">{{ $blog->published_at->copy()->locale($copyLocale)->translatedFormat(localized_date_format('F j, Y')) }}</time></span>@endif
            @if($blog->editorial_updated_at)<span>{{ website_text('blogs', 'Updated:') }} <time datetime="{{ $blog->editorial_updated_at->toIso8601String() }}">{{ $blog->editorial_updated_at->copy()->locale($copyLocale)->translatedFormat(localized_date_format('F j, Y')) }}</time></span>@endif
            <span>{{ website_text('blogs', 'Reading time:') }} {{ $blog->readingMinutes() }} {{ website_text('blogs', 'min') }}</span>
        </div>
        @if($versions->count() > 1)
            <nav lang="{{ $copyLocale }}" aria-label="{{ website_text('blogs', 'Published article languages') }}" class="blog-versions">@foreach($versions as $version)<a href="{{ $version->publicUrl() }}" hreflang="{{ $version->locale }}" lang="{{ $version->locale }}" @if($version->id === $blog->id) aria-current="page" @endif>{{ ['en'=>'English', 'fr'=>'Français', 'de'=>'Deutsch'][$version->locale] }}</a>@endforeach</nav>
        @endif
    </header>
    @if($featuredImage)<figure class="blog-article-media"><img src="{{ $featuredImage }}" alt="{{ $blog->featured_image_alt ?? '' }}" width="{{ $featuredImageDimensions['width'] }}" height="{{ $featuredImageDimensions['height'] }}" decoding="async"></figure>@endif
    <div data-section-id="blog-content" class="article-copy blog-reading">{!! $articleBody !!}</div>
    <div lang="{{ $copyLocale }}" class="blog-article-actions">
        <a href="{{ localized_url('blogs') }}" class="blogs-link blogs-link-back"><x-public-studio-icon name="arrow" class="studio-link-arrow" /> {{ website_text('blogs', 'Back to All Articles') }}</a>
        @unless($preview ?? false)<button type="button" data-copy-text="{{ $blog->publicUrl() }}" data-copy-success="{{ website_text('blogs', 'Link copied.') }}" data-copy-failure="{{ website_text('blogs', 'Copy unavailable') }}" class="blog-copy">{{ website_text('blogs', 'Copy Link') }} <span data-copy-status role="status" aria-atomic="true"></span></button>@endunless
    </div>
    @if($relatedResources->isNotEmpty())
        <section lang="{{ $copyLocale }}" class="blog-related"><p class="blogs-eyebrow">{{ website_text('blogs', 'Keep Exploring') }}</p><h2>{{ website_text('blogs', 'Want to Go a Little Further?') }}</h2><p>{{ website_text('blogs', 'Some topics make more sense when you can revisit an example or put an explanation into practice. If there\'s a related learning resource, you can explore it here.') }}</p><div class="blogs-grid">@foreach($relatedResources as $resource)<x-public-resource-card :resource="$resource" />@endforeach</div></section>
    @endif
    @if($relatedGames->isNotEmpty())
        <section lang="{{ $copyLocale }}" class="blog-related"><h2>{{ website_text('blogs', 'Related Language Games') }}</h2><div class="blogs-grid">@foreach($relatedGames as $game)<x-public-game-card :game="$game" />@endforeach</div></section>
    @endif
    @if($relatedArticles->isNotEmpty())
        <section lang="{{ $copyLocale }}" class="blog-related"><p class="blogs-eyebrow">{{ website_text('blogs', 'You Might Also Enjoy') }}</p><h2>{{ website_text('blogs', 'Keep Following Your Curiosity.') }}</h2><p>{{ website_text('blogs', 'Explore other published articles connected to this topic.') }}</p><div class="blogs-grid">@foreach($relatedArticles as $related)<x-blog-card :blog="$related" :copy-locale="$copyLocale" />@endforeach</div></section>
    @endif
</article>
@endsection

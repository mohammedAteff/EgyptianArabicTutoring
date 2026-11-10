@props(['blog', 'featured' => false, 'wide' => false, 'copyLocale' => 'en'])
@php
    $images = app(\App\Domains\CMS\Services\BlogImageService::class);
    $image = $images->url($blog->featured_image_path);
    $dimensions = $images->dimensions($blog->featured_image_path);
@endphp
<article lang="{{ $blog->locale }}" class="blog-card {{ $wide ? 'blog-card-lead' : '' }} {{ $image ? 'blog-card-with-image' : '' }}">
    @if($image)<div class="blog-card-media"><img src="{{ $image }}" alt="{{ $blog->featured_image_alt ?? '' }}" width="{{ $dimensions['width'] }}" height="{{ $dimensions['height'] }}" loading="lazy" decoding="async"></div>@endif
    <div class="blog-card-content">
        <div class="blog-card-meta">
            @if($featured)<span lang="{{ $copyLocale }}" class="blogs-eyebrow">{{ website_text('blogs', 'Featured Article') }}</span>@endif
            @if($blog->category)<span class="blog-card-category">{{ $blog->category->name }}</span>@endif
            @if($blog->published_at)<time lang="{{ $copyLocale }}" datetime="{{ $blog->published_at->toIso8601String() }}">{{ $blog->published_at->copy()->locale($copyLocale)->translatedFormat(localized_date_format('M j, Y')) }}</time>@endif
            <span lang="{{ $copyLocale }}">{{ $blog->readingMinutes() }} {{ website_text('blogs', 'min read') }}</span>
        </div>
        <h3><a href="{{ $blog->publicUrl() }}">{{ $blog->title }}</a></h3>
        @if($blog->excerpt)<p class="blog-card-excerpt">{{ $blog->excerpt }}</p>@endif
        <a lang="{{ $copyLocale }}" href="{{ $blog->publicUrl() }}" class="blogs-link">{{ website_text('blogs', 'Read Article') }}<span class="sr-only">: {{ $blog->title }}</span> <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
    </div>
</article>

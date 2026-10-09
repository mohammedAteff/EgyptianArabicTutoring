@extends('layouts.public')
@section('content')
<article lang="{{ $blog->locale }}" class="mx-auto max-w-4xl px-4 py-12 sm:px-6">
    <nav lang="en" aria-label="Breadcrumb" class="mb-8 text-sm text-stone-600"><a href="{{ localized_url('blogs') }}" class="underline">Blogs</a> <span aria-hidden="true">/</span> {{ $blog->title ?: 'Untitled draft' }}</nav>
    @if($preview ?? false)<p lang="en" class="mb-6 rounded-lg bg-amber-100 px-4 py-3 text-sm font-semibold text-amber-900">Private preview of the saved article. Unsaved edits are not shown.</p>@endif
    <header class="max-w-3xl">
        @if($blog->category)<p class="text-sm font-semibold text-terracotta-600">{{ $blog->category->name }}</p>@endif
        <h1 class="mt-3 font-serif text-4xl font-semibold leading-tight tracking-tight text-nile-900 sm:text-5xl">{{ $blog->title ?: 'Untitled draft' }}</h1>
        @if($blog->excerpt)<p class="mt-6 text-lg leading-8 text-stone-600">{{ $blog->excerpt }}</p>@endif
        <p lang="en" class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm text-stone-500">
            <span>Written by: Abdallah</span>
            @if($blog->published_at)<span>Published: <time datetime="{{ $blog->published_at->toIso8601String() }}">{{ $blog->published_at->format('F j, Y') }}</time></span>@endif
            @if($blog->editorial_updated_at)<span>Updated: <time datetime="{{ $blog->editorial_updated_at->toIso8601String() }}">{{ $blog->editorial_updated_at->format('F j, Y') }}</time></span>@endif
            <span>Reading time: {{ $blog->readingMinutes() }} min</span>
        </p>
        @if($versions->count() > 1)
            <nav lang="en" aria-label="Published article languages" class="mt-5 flex flex-wrap gap-4 text-sm">@foreach($versions as $version)<a href="{{ $version->publicUrl() }}" hreflang="{{ $version->locale }}" class="font-semibold text-terracotta-600 underline" @if($version->id === $blog->id) aria-current="page" @endif>{{ ['en'=>'English', 'fr'=>'Français', 'de'=>'Deutsch'][$version->locale] }}</a>@endforeach</nav>
        @endif
    </header>
    @if($featuredImage)<img src="{{ $featuredImage }}" alt="{{ $blog->featured_image_alt ?? '' }}" class="mt-8 aspect-video w-full rounded-2xl object-cover">@endif
    <div data-section-id="blog-content" class="article-copy mt-10 max-w-3xl">{!! $articleBody !!}</div>
    @if($relatedResources->isNotEmpty())
        <section lang="en" class="mt-14 border-t border-stone-200 pt-8"><p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">Keep Exploring</p><h2 class="mt-3 text-3xl font-semibold text-nile-900">Want to Go a Little Further?</h2><p class="mt-4 leading-7 text-stone-600">Some topics make more sense when you can revisit an example or put an explanation into practice. If there's a related learning resource, you can explore it here.</p><div class="mt-6 grid gap-5 sm:grid-cols-2">@foreach($relatedResources as $resource)<x-public-resource-card :resource="$resource" />@endforeach</div></section>
    @endif
    @if($relatedGames->isNotEmpty())
        <section lang="en" class="mt-12 border-t border-stone-200 pt-8"><h2 class="text-2xl font-semibold text-nile-900">Related Language Games</h2><div class="mt-6 grid gap-5 sm:grid-cols-2">@foreach($relatedGames as $game)<x-public-game-card :game="$game" />@endforeach</div></section>
    @endif
    @if($relatedArticles->isNotEmpty())
        <section lang="en" class="mt-12 border-t border-stone-200 pt-8"><p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">You Might Also Enjoy</p><h2 class="mt-3 text-3xl font-semibold text-nile-900">Keep Following Your Curiosity.</h2><p class="mt-4 text-stone-600">Explore other published articles connected to this topic.</p><div class="mt-6 grid gap-5 sm:grid-cols-2">@foreach($relatedArticles as $related)<x-blog-card :blog="$related" />@endforeach</div></section>
    @endif
    <div lang="en" class="mt-12 flex flex-wrap items-center gap-5 border-t border-stone-200 pt-6">
        <a href="{{ localized_url('blogs') }}" class="font-semibold text-terracotta-600 underline">Back to All Articles</a>
        @unless($preview ?? false)<button type="button" data-copy-text="{{ $blog->publicUrl() }}" data-copy-success="Link copied." class="rounded-xl border border-stone-300 px-4 py-2 font-semibold">Copy Link <span data-copy-status role="status" class="ml-2 text-xs"></span></button>@endunless
    </div>
</article>
@endsection

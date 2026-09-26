@extends('layouts.public', ['title' => $blog->seo_title ?: $blog->title, 'metaDescription' => $blog->seo_description ?: $blog->excerpt])

@section('content')
<article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
    @if($preview ?? false)<p class="mb-6 rounded-lg bg-amber-100 px-4 py-3 text-sm font-semibold text-amber-900">Draft preview — not publicly published.</p>@endif
    @if($blog->featured_image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($blog->featured_image_path) }}" alt="" class="mb-8 aspect-video w-full rounded-2xl object-cover">@endif
    <header><p class="text-sm text-stone-500">{{ $blog->published_at?->format('F j, Y') }}</p><h1 class="mt-2 text-4xl font-semibold tracking-tight text-nile-900">{{ $blog->title }}</h1><p class="mt-4 text-lg leading-8 text-stone-600">{{ $blog->excerpt }}</p></header>
    <div data-section-id="blog-content" class="prose prose-stone mt-10 max-w-none">{!! $blog->body !!}</div>
</article>
@endsection

@extends('layouts.public', ['title' => $article->seo_title ?: $article->title, 'metaDescription' => $article->seo_description ?: $article->excerpt])

@section('content')
<article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
    @if($preview ?? false)<p class="mb-6 rounded-lg bg-amber-100 px-4 py-3 text-sm font-semibold text-amber-900">Draft preview — not publicly published.</p>@endif
    @if($article->featured_image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($article->featured_image_path) }}" alt="" class="mb-8 aspect-video w-full rounded-2xl object-cover">@endif
    <header><p class="text-sm text-stone-500">{{ $article->published_at?->format('F j, Y') }}</p><h1 class="mt-2 text-4xl font-semibold tracking-tight text-nile-900">{{ $article->title }}</h1><p class="mt-4 text-lg leading-8 text-stone-600">{{ $article->excerpt }}</p></header>
    <div class="prose prose-stone mt-10 max-w-none">{!! $article->body !!}</div>
</article>
@endsection

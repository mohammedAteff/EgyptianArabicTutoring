@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<article lang="{{ $copyLocale ?? 'en' }}" class="mx-auto max-w-4xl px-4 py-12 sm:px-6 lg:px-8">
    <h1 class="text-3xl font-bold sm:text-4xl">{{ config('public_content.policy.title') }}</h1>
    <p class="mt-4 text-sm text-stone-500">{{ config('public_content.policy.version') }}</p>
    <div class="article-copy mt-8 rounded-2xl border border-stone-200 bg-white p-6 sm:p-10">
        @foreach(config('public_content.policy.blocks') as $block)
            @if($block['type'] === 'heading')<h2>{{ $block['text'] }}</h2>@elseif(preg_match('/<\/?[a-z][^>]*>/i', $block['text']))<div>{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($block['text']) !!}</div>@else<p>{{ $block['text'] }}</p>@endif
        @endforeach
    </div>
</article>
@endsection

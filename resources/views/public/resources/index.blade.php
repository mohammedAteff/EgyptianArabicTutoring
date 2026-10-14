@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div lang="{{ $copyLocale ?? 'en' }}" class="mx-auto max-w-6xl space-y-14 px-4 py-12 sm:px-6 lg:px-8">
    @foreach(config('public_content.resources') as $index => $section)
    @if(! ($section['_visible'] ?? true)) @continue @endif
    <section class="space-y-6">
        <x-public-copy-section :section="$section" :level="$index === 0 ? 1 : 2" />
        @if($index === 1)

        @if($categories->isNotEmpty())
            <nav aria-label="Resource categories" class="flex flex-wrap gap-3">
                <a href="{{ website_link('resources', 'resources', localized_url('resources')) }}" class="rounded-full border px-4 py-2 {{ !$selectedCategory ? 'bg-nile-900 text-white' : 'bg-white' }}">{{ website_text('resources', 'All Resources') }}</a>
                @foreach($categories as $category)
                    <a href="{{ localized_url('resources').'?'.http_build_query(['category' => $category->slug]) }}" class="rounded-full border px-4 py-2 {{ $selectedCategory === $category->slug ? 'bg-nile-900 text-white' : 'bg-white' }}">{{ $category->liveTranslation()?->name ?? $category->name }}</a>
                @endforeach
            </nav>
        @endif
        @if($resources->isNotEmpty())
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">@foreach($resources as $resource)<x-public-resource-card :resource="$resource" />@endforeach</div>
            {{ $resources->links() }}
        @else
            <div class="rounded-2xl border border-stone-200 bg-white p-8">
                <h3 class="text-xl font-bold">{{ $selectedCategory ? 'No resources in this category yet' : 'Learning materials are being prepared.' }}</h3>
                <p class="mt-3 text-stone-600">There aren't any published resources {{ $selectedCategory ? 'in this category' : 'in the library' }} at the moment.</p>
                @if($selectedCategory)<a href="{{ website_link('resources', 'resources', localized_url('resources')) }}" class="mt-5 inline-block font-bold text-terracotta-600 underline">{{ website_text('resources', 'Browse All Resources') }}</a>@endif
            </div>
        @endif

        @elseif($index === 4)
        <a href="{{ website_link('resources', 'games', localized_url('games')) }}" class="inline-flex rounded-xl bg-nile-900 px-6 py-3 font-bold text-white">{{ website_text('resources', 'Explore Arabic Games') }}</a>
        @endif
    </section>
    @endforeach
</div>
@endsection
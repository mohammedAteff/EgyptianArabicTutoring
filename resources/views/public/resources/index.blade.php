
@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div lang="en" class="mx-auto max-w-6xl space-y-14 px-4 py-12 sm:px-6 lg:px-8">
    <x-public-copy-section :section="config('public_content.resources.0')" :level="1" />
    <section class="space-y-6">
        <x-public-copy-section :section="config('public_content.resources.1')" />
        @if($categories->isNotEmpty())
            <nav aria-label="Resource categories" class="flex flex-wrap gap-3">
                <a href="{{ localized_url('resources') }}" class="rounded-full border px-4 py-2 {{ !$selectedCategory ? 'bg-nile-900 text-white' : 'bg-white' }}">All Resources</a>
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
                @if($selectedCategory)<a href="{{ localized_url('resources') }}" class="mt-5 inline-block font-bold text-terracotta-600 underline">Browse All Resources</a>@endif
            </div>
        @endif
        <p class="text-sm text-stone-500">The Alphabet Workbook is awaiting its confirmed file and is not available to download yet.</p>
    </section>
    <x-public-copy-section :section="config('public_content.resources.2')" />
    <x-public-copy-section :section="config('public_content.resources.3')" />
    <section class="space-y-6">
        <x-public-copy-section :section="config('public_content.resources.4')" />
        <a href="{{ localized_url('games') }}" class="inline-flex rounded-xl bg-nile-900 px-6 py-3 font-bold text-white">Explore Arabic Games</a>
    </section>
</div>
@endsection


@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div lang="{{ $copyLocale ?? 'en' }}" class="mx-auto max-w-6xl space-y-14 px-4 py-12 sm:px-6 lg:px-8">
    @foreach(config('public_content.games') as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        <section class="space-y-7">
            <x-public-copy-section :section="$section" :level="$index === 0 ? 1 : 2" />
            @if($index === 1)
                @if($games->isNotEmpty())
                    <div class="grid gap-6 sm:grid-cols-2">@foreach($games as $game)<x-public-game-card :game="$game" />@endforeach</div>
                    @if($games->contains(fn ($game) => $game->status === 'available' && str_contains((string) $game->target_url, '/6word')))
                        <div class="rounded-2xl border border-stone-200 bg-white p-7">
                            <h3 class="text-2xl font-bold">{{ config('public_content.game_feature.heading') }}</h3>
                            @foreach(config('public_content.game_feature.paragraphs') as $paragraph)<p class="mt-4 leading-relaxed text-stone-600">{{ $paragraph }}</p>@endforeach
                            <ul class="mt-6 list-disc space-y-3 pl-5 text-stone-600">@foreach(config('public_content.game_feature.features') as $feature)<li>{{ $feature }}</li>@endforeach</ul>
                        </div>
                    @endif
                @else
                    <div class="rounded-2xl border border-stone-200 bg-white p-8"><h3 class="text-xl font-bold">{{ website_text('games', 'New challenges are on their way.') }}</h3><p class="mt-3 text-stone-600">{{ website_text('games', 'There aren\'t any games available at the moment. In the meantime, explore our Arabic learning resources.') }}</p></div>
                @endif
            @elseif($index === 3)
                <a href="{{ website_link('games', 'resources', localized_url('resources')) }}" class="inline-flex rounded-xl bg-nile-900 px-6 py-3 font-bold text-white">{{ website_text('games', 'Explore Arabic Resources') }}</a>
            @endif
        </section>
    @endforeach
</div>
@endsection

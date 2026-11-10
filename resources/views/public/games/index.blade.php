@extends('layouts.public')
@section('body-class', 'studio-games-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
@php($resourcesUrl = website_link('games', 'resources', localized_url('resources')))
<div class="games-page" lang="{{ $copyLocale ?? 'en' }}">
    @foreach(config('public_content.games') as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        <section @class(['games-section', 'games-intro' => $index === 0, 'games-library' => $index === 1, 'games-benefits' => $index === 2, 'games-resources' => $index === 3]) id="games-section-{{ $index }}">
            <x-public-copy-section class="games-copy" :section="$index === 2 ? array_replace($section, ['extra_content' => '']) : $section" :level="$index === 0 ? 1 : 2" :show-blocks="$index !== 2" />
            @if($index === 0 && (config('public_content.games.1._visible') ?? true))
                <a href="#games-section-1" class="studio-link games-jump">{{ website_text('games', 'Jump to games') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
            @endif
            @if($index === 1)
                @unless($hasAvailableGames)
                    <div class="games-empty">
                        <h3>{{ website_text('games', 'New challenges are on their way.') }}</h3>
                        <p>{{ website_text('games', 'There aren\'t any games available at the moment. In the meantime, explore our Arabic learning resources.') }}</p>
                        <a href="{{ $resourcesUrl }}" class="studio-link">{{ website_text('games', 'Explore Arabic Resources') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                    </div>
                @endunless
                @if($games->isNotEmpty())
                    <div class="games-grid">
                        @foreach($games as $game)
                            <x-games-library-game :game="$game" :wide="$game->id === $wideGameId" :spotlight="$game->id === $featuredGameId" :priority="$loop->first" :copy-locale="$copyLocale ?? 'en'" />
                        @endforeach
                    </div>
                @endif
            @elseif($index === 2)
                <x-games-benefits :blocks="$section['blocks']" />
                @if(!empty($section['extra_content']))<div class="games-extra article-copy">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($section['extra_content']) !!}</div>@endif
            @elseif($index === 3)
                <a href="{{ $resourcesUrl }}" class="studio-link">{{ website_text('games', 'Explore Arabic Resources') }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
            @endif
        </section>
    @endforeach
</div>
@endsection

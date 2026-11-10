@props(['game', 'wide' => false, 'spotlight' => false, 'priority' => false, 'copyLocale' => 'en'])
@php
    $localized = $game->localizedContent();
    $gameTitle = $localized['title'];
    $gameDescription = $localized['description'];
    $titleLocale = $localized['title_locale'];
    $descriptionLocale = $localized['description_locale'];
    $badge = $localized['badge'];
    $external = $game->isExternal();
    $destination = $game->launchUrl();
    $canLaunch = $game->isAvailable() && (! $external || \App\Rules\SafeLessonUrl::isSafe($destination));
    $imagePath = $game->coverPath();
    $image = $imagePath !== '' && ! str_contains($imagePath, '..') && ! str_contains($imagePath, '\\') && ! str_contains($imagePath, "\0") && ! str_starts_with($imagePath, '/') ? realpath(public_path($imagePath)) : false;
    $dimensions = $image && str_starts_with(str_replace('\\', '/', $image), str_replace('\\', '/', realpath(public_path())).'/') && is_file($image) ? @getimagesize($image) : false;
    $hasImage = $dimensions !== false && in_array($dimensions[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF], true);
    $isStory = $game->slug === '6-word-story' && $game->isAvailable();
@endphp
<article @class(['games-game', 'games-game-wide' => $wide, 'games-game-spotlight' => $spotlight, 'games-game-with-media' => $hasImage]) data-game-id="{{ $game->id }}" data-featured="{{ $spotlight ? 'true' : 'false' }}" lang="{{ $localized['locale'] }}" dir="ltr">
    <div class="games-game-identity">
        <p class="games-game-meta" lang="{{ $copyLocale }}"><span>{{ website_text('games', $game->isAvailable() ? 'Free to Play' : 'Coming Soon') }}</span>@if(filled($badge))<span aria-hidden="true">·</span><bdi lang="{{ $localized['badge_locale'] }}">{{ $badge }}</bdi>@endif</p>
        <h3><bdi lang="{{ $titleLocale }}">{{ $gameTitle }}</bdi></h3>
        @if($isStory)<p class="games-game-editorial" lang="{{ $copyLocale }}">{{ config('public_content.game_feature.heading') }}</p>@endif
        <p class="games-game-description" lang="{{ $descriptionLocale }}" dir="auto">{{ $gameDescription }}</p>
        @if($canLaunch)
            <a class="studio-button games-play" href="{{ $destination }}" lang="{{ $copyLocale }}" @if($external) target="_blank" rel="noopener noreferrer" aria-describedby="games-external-{{ $game->id }}" onclick="if(window.vaTrack){ window.vaTrack('game_opened', {{ \Illuminate\Support\Js::from(['game_slug' => $game->slug, 'game_title' => $gameTitle, 'target_url' => $game->target_url]) }}); window.vaTrack('outbound_link_clicked', {{ \Illuminate\Support\Js::from(['target' => $game->target_url, 'url' => $game->target_url, 'destination' => $gameTitle, 'text' => $gameTitle, 'placement' => 'game_card']) }}); }" @endif>{{ strtr(website_text('games', 'Play {game}'), ['{game}' => $gameTitle]) }}<x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
            @if($external)
                <div id="games-external-{{ $game->id }}" class="games-external" lang="{{ $copyLocale }}">
                    <p>{{ website_text('games', 'Opens on another website in a new tab.') }}</p>
                    @if($game->slug === '6-word-story')<p>{{ website_text('games', 'Choose a language, then select 6-Word Story.') }}</p>@endif
                </div>
            @endif
        @elseif($game->isAvailable())
            <p class="games-external" lang="{{ $copyLocale }}">{{ website_text('games', 'This game isn\'t available right now.') }}</p>
        @endif
    </div>
    @if($hasImage)
        <div class="games-game-media">
            <img src="{{ asset($imagePath) }}" alt="{{ strtr(website_text('games', 'Preview of {game}'), ['{game}' => $gameTitle]) }}" width="{{ $dimensions[0] }}" height="{{ $dimensions[1] }}" loading="{{ $priority ? 'eager' : 'lazy' }}">
        </div>
    @endif
    @if($isStory)
        <x-games-story-details :feature="config('public_content.game_feature')" :copy-locale="$copyLocale" />
    @endif
</article>

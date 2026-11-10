@props(['game', 'compact' => false])
@php
    $translation = $game->liveTranslation();
    $localized = $game->localizedContent();
    $gameTitle = $localized['title'];
    $badgeLabel = $localized['badge'];
    $cardUrl = $game->launchUrl();
    $imagePath = $game->coverPath();
    $hasImage = $imagePath !== '' && !str_contains($imagePath, '..') && !str_starts_with($imagePath, '/') && file_exists(public_path($imagePath));
@endphp
<article {{ $attributes->class(['flex flex-col rounded-2xl border border-stone-200 bg-white p-6 shadow-sm']) }} lang="{{ $localized['locale'] }}">
    <p class="text-xs font-bold uppercase tracking-wide text-nile-700">{{ __($game->status === 'available' ? 'Free to Play' : 'Coming Soon') }}</p>
    @if($hasImage)<img src="{{ asset($imagePath) }}" alt="{{ __('Preview of :title', ['title' => $gameTitle]) }}" class="mt-4 aspect-video w-full rounded-xl object-cover object-top" loading="lazy">@endif
    @if($badgeLabel && ! $compact)<p class="mt-3 text-xs font-semibold text-nile-700">{{ $badgeLabel }}</p>@endif
    <h3 class="mt-3 text-2xl font-bold">{{ $gameTitle }}</h3>
    @unless($compact)<p class="mt-4 grow leading-relaxed text-stone-600">{{ $localized['description'] }}</p>@endunless
    @if($game->status === 'available')
        <a href="{{ $cardUrl }}" @if($compact && $game->isExternal()) aria-describedby="game-external-{{ $game->id }}" @endif @if($game->isExternal()) target="_blank" rel="noopener noreferrer" onclick="if(window.vaTrack){ window.vaTrack('game_opened', {{ \Illuminate\Support\Js::from(['game_slug' => $game->slug, 'game_title' => $gameTitle, 'target_url' => $game->target_url]) }}); window.vaTrack('outbound_link_clicked', {{ \Illuminate\Support\Js::from(['target' => $game->target_url, 'url' => $game->target_url, 'destination' => $gameTitle, 'text' => $gameTitle, 'placement' => 'game_card']) }}); }" @endif @class(['mt-6', 'studio-button' => $compact, 'font-bold text-terracotta-600 underline underline-offset-4' => ! $compact])>@if($compact){{ __('Play') }} {{ $gameTitle }}@else {{ __('Play Now') }} @endif @if($game->isExternal() && ! $compact)<span class="block pt-2 text-xs font-normal text-stone-500">{{ __('Opens on another website') }}</span>@endif</a>
        @if($compact && $game->isExternal())<p id="game-external-{{ $game->id }}" class="studio-game-external">{{ __('Opens on another website') }}</p>@endif
    @else
        <p class="mt-6 text-sm text-stone-500">{{ __('This game isn\'t available right now.') }}</p>
    @endif
</article>

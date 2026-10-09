@props(['game'])
@php
    $translation = $game->liveTranslation();
    $gameTitle = $translation?->title ?? $game->title;
    $badgeLabel = $translation?->badge ?? $game->badge;
    $cardUrl = $game->target_url ?: localized_url('game.detail', $game->slug);
    $imagePath = (string) $game->thumbnail_path;
    $hasImage = $imagePath !== '' && !str_contains($imagePath, '..') && !str_starts_with($imagePath, '/') && file_exists(public_path($imagePath));
@endphp
<article class="flex flex-col rounded-2xl border border-stone-200 bg-white p-6 shadow-sm" lang="{{ $translation?->locale ?? 'en' }}">
    <p class="text-xs font-bold uppercase tracking-wide text-nile-700">{{ $game->status === 'available' ? 'Free to Play' : 'Coming Soon' }}</p>
    @if($hasImage)<img src="{{ asset($imagePath) }}" alt="Preview of {{ $gameTitle }}" class="mt-4 aspect-video w-full rounded-xl object-cover object-top" loading="lazy">@endif
    @if($badgeLabel)<p class="mt-3 text-xs font-semibold text-nile-700">{{ $badgeLabel }}</p>@endif
    <h3 class="mt-3 text-2xl font-bold">{{ $gameTitle }}</h3>
    <p class="mt-4 grow leading-relaxed text-stone-600">{{ $translation?->description ?? $game->description }}</p>
    @if($game->status === 'available')
        <a href="{{ $cardUrl }}" @if($game->target_url) target="_blank" rel="noopener noreferrer" onclick="if(window.vaTrack){ window.vaTrack('game_opened', {{ \Illuminate\Support\Js::from(['game_slug' => $game->slug, 'game_title' => $gameTitle, 'target_url' => $game->target_url]) }}); window.vaTrack('outbound_link_clicked', {{ \Illuminate\Support\Js::from(['target' => $game->target_url, 'url' => $game->target_url, 'destination' => $gameTitle, 'text' => $gameTitle, 'placement' => 'game_card']) }}); }" @endif class="mt-6 font-bold text-terracotta-600 underline underline-offset-4">Play Now @if($game->target_url)<span class="block pt-2 text-xs font-normal text-stone-500">Opens on another website</span>@endif</a>
    @else
        <p class="mt-6 text-sm text-stone-500">This game isn't available right now.</p>
    @endif
</article>

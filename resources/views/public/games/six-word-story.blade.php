@extends('layouts.public')
@section('body-class', 'studio-games-page')
@push('game-assets')
    @viteReactRefresh
    @vite('resources/js/games/six-word-story.jsx')
@endpush
@section('content')
<div data-local-story-game data-playable="{{ $game->isAvailable() || ($isPreview ?? false) ? 'true' : 'false' }}" data-ui-locale="{{ app()->getLocale() }}" data-back-url="{{ localized_url('games') }}" data-lesson-url="{{ localized_url('booking') }}" data-cover-url="{{ asset($game->coverPath()) }}" data-track-url="{{ route('games.track', $game->slug) }}" data-tracking-enabled="{{ !($isPreview ?? false) && $game->isAvailable() && !app(\App\Domains\Analytics\Services\AnalyticsService::class)->excluded(request()) ? 'true' : 'false' }}">
    <div class="mx-auto max-w-3xl px-6 py-16">
        <h1 class="text-3xl">{{ $game->localizedContent()['title'] }}</h1>
        @if($game->isAvailable() || ($isPreview ?? false))
            <p class="mt-4" role="status">{{ ['fr' => 'Votre histoire se prépare…', 'de' => 'Ihre Geschichte wird vorbereitet…'][app()->getLocale()] ?? 'Getting your story ready…' }}</p>
            <noscript><p class="mt-4">{{ ['fr' => 'Activez JavaScript pour jouer.', 'de' => 'Aktivieren Sie JavaScript, um zu spielen.'][app()->getLocale()] ?? 'Please enable JavaScript to play.' }}</p></noscript>
        @else
            <p class="mt-4">{{ __('Coming Soon') }}</p>
        @endif
    </div>
</div>
@endsection

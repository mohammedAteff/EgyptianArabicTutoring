@extends('layouts.public')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Header -->
    <div class="text-center max-w-3xl mx-auto mb-14">
        <span class="inline-flex items-center gap-1.5 bg-terracotta-50 text-terracotta-700 text-xs font-bold px-3.5 py-1.5 rounded-full uppercase tracking-wider mb-3">
            🎮 {{ __('Interactive Dialect Practice') }}
        </span>
        <h1 class="text-3xl sm:text-5xl font-extrabold text-stone-900 tracking-tight">
            {{ __('Egyptian Arabic Language Games') }}
        </h1>
        <p class="text-stone-600 text-base sm:text-lg mt-3 leading-relaxed">
            {{ __('Gamified speaking drills and dialect challenges designed to build real-time conversation speed, vocabulary recall, and speaking confidence.') }}
        </p>
    </div>

    @php
        $availableGames = $games->filter(fn ($g) => $g->status === 'available');
        $upcomingGames = $games->filter(fn ($g) => $g->status !== 'available');
    @endphp

    <!-- Available Games Section -->
    <div class="space-y-8">
        @if($availableGames->isNotEmpty())
            <div>
                <div class="flex items-center justify-between mb-6 pb-3 border-b border-stone-200">
                    <h2 class="text-xl sm:text-2xl font-bold text-stone-900 flex items-center gap-2.5 font-serif">
                        <span>{{ __('Available Games') }}</span>
                        <span class="text-xs font-bold text-terracotta-600 bg-terracotta-50 px-2.5 py-0.5 rounded-full border border-terracotta-200/60">
                            {{ $availableGames->count() }} {{ \Illuminate\Support\Str::plural('Game', $availableGames->count()) }}
                        </span>
                    </h2>
                    <span class="text-xs text-stone-500 hidden sm:inline-flex items-center gap-1">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 inline-block"></span>
                        Instant browser play &bull; No sign-up required
                    </span>
                </div>

                <!-- Games Grid: If single game, showcased large card; if multiple, responsive grid -->
                <div class="{{ $availableGames->count() === 1 ? 'max-w-3xl mx-auto' : 'grid grid-cols-1 md:grid-cols-2 gap-8' }}">
                    @foreach($availableGames as $game)
                        @php
                            $isExternal = !empty($game->target_url);
                            $cardUrl = $isExternal ? $game->target_url : localized_url('game.detail', $game->slug);
                            $badgeLabel = ($game->liveTranslation()?->badge ?? $game->badge) ?: ($isExternal ? __('Speaking Practice') : __('Dialect Practice'));
                            $gameTitle = $game->liveTranslation()?->title ?? $game->title;
                            $gameDesc = $game->liveTranslation()?->description ?? $game->description;
                        @endphp

                        <a href="{{ $cardUrl }}"
                           @if($isExternal) target="_blank" rel="noopener noreferrer" @endif
                           @if($isExternal) onclick="if(window.vaTrack){ window.vaTrack('game_opened', { game_slug: '{{ $game->slug }}', game_title: '{{ addslashes($gameTitle) }}', target_url: '{{ $game->target_url }}' }); window.vaTrack('outbound_link_clicked', { target: '{{ $game->target_url }}', url: '{{ $game->target_url }}', destination: '{{ addslashes($gameTitle) }}', text: '{{ addslashes($gameTitle) }}', placement: 'game_card' }); }" @endif
                           class="group block bg-white rounded-3xl border border-stone-200/90 shadow-sm hover:shadow-xl hover:border-terracotta-300 transition-all duration-300 hover:-translate-y-1.5 focus:outline-none focus-visible:ring-4 focus-visible:ring-terracotta-500/40 focus-visible:ring-offset-2 overflow-hidden flex flex-col"
                           aria-label="{{ $gameTitle }} - {{ $badgeLabel }}@if($isExternal) (opens in a new tab)@endif">
                            
                            <!-- Thumbnail / Live Preview Image -->
                            <div class="relative w-full aspect-[16/9] bg-stone-950 overflow-hidden border-b border-stone-100">
                                @if($game->thumbnail_path && file_exists(public_path($game->thumbnail_path)))
                                    <img src="{{ asset($game->thumbnail_path) }}"
                                         alt="Preview of {{ $gameTitle }} — {{ $badgeLabel }} game for Egyptian Arabic"
                                         class="w-full h-full object-cover object-top group-hover:scale-[1.02] transition-transform duration-500 ease-out"
                                         loading="lazy">
                                @else
                                    <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-br from-stone-900 to-stone-800 text-stone-200 p-8 text-center">
                                        <div class="w-16 h-16 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-3xl mb-3 border border-amber-500/20">
                                            🎮
                                        </div>
                                        <span class="text-lg font-bold font-serif">{{ $gameTitle }}</span>
                                    </div>
                                @endif

                                <!-- Top overlay badge and external link pill -->
                                <div class="absolute top-4 left-4 right-4 flex items-center justify-between pointer-events-none">
                                    <span class="inline-flex items-center gap-1.5 bg-stone-900/85 backdrop-blur-md text-amber-300 text-xs font-bold px-3 py-1.5 rounded-full shadow-sm border border-stone-700/60">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse"></span>
                                        {{ $badgeLabel }}
                                    </span>

                                    @if($isExternal)
                                        <div class="inline-flex items-center gap-1 bg-stone-900/85 backdrop-blur-md text-stone-200 text-[11px] font-semibold px-2.5 py-1 rounded-full shadow-sm border border-stone-700/60 group-hover:bg-terracotta-600 group-hover:text-white group-hover:border-terracotta-500 transition-colors">
                                            <span>External Game</span>
                                            <svg class="w-3.5 h-3.5 text-stone-400 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Content Area -->
                            <div class="p-6 sm:p-8 flex flex-col flex-grow justify-between bg-white">
                                <div>
                                    <div class="flex items-start justify-between gap-4 mb-2">
                                        <h3 class="text-2xl sm:text-3xl font-bold text-stone-900 group-hover:text-terracotta-600 transition-colors tracking-tight font-serif">
                                            {{ $gameTitle }}
                                        </h3>
                                        <span class="shrink-0 inline-flex items-center gap-1 text-xs font-bold text-terracotta-600 group-hover:translate-x-0.5 transition-transform duration-200 pt-1.5">
                                            <span>{{ __('Launch') }}</span>
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                            </svg>
                                        </span>
                                    </div>

                                    <p class="text-stone-600 text-sm sm:text-base leading-relaxed mt-2.5">
                                        {{ $gameDesc }}
                                    </p>
                                </div>

                                <!-- Card Footer Metadata -->
                                <div class="mt-6 pt-5 border-t border-stone-100 flex flex-wrap items-center justify-between gap-3 text-xs text-stone-500 font-medium">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center gap-1.5 text-emerald-600 font-semibold">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            {{ __('Free to Play') }}
                                        </span>
                                        <span>•</span>
                                        <span>{{ __('Egyptian Arabic Fluency') }}</span>
                                    </div>
                                    @if($isExternal)
                                        <span class="text-stone-400 group-hover:text-stone-600 transition-colors flex items-center gap-1">
                                            <span>{{ parse_url($game->target_url, PHP_URL_HOST) }}</span>
                                            <svg class="w-3 h-3 text-stone-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @else
            <!-- Friendly empty state if all games disabled -->
            <div class="text-center py-16 bg-white rounded-3xl border border-stone-200/80 p-8 shadow-xs max-w-xl mx-auto">
                <div class="w-14 h-14 mx-auto mb-4 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl font-bold">
                    🎮
                </div>
                <h3 class="text-xl font-bold text-stone-900 mb-2">New Games in Development</h3>
                <p class="text-stone-600 text-sm">Interactive language games and speaking drills will be published here shortly.</p>
            </div>
        @endif

        <!-- Upcoming / In Development Games (if any) -->
        @if($upcomingGames->isNotEmpty())
            <div class="pt-8 border-t border-stone-200/70">
                <h3 class="text-lg font-bold text-stone-800 mb-6 flex items-center gap-2">
                    <span>{{ __('Coming Soon') }}</span>
                    <span class="text-xs font-semibold text-stone-400 bg-stone-100 px-2.5 py-0.5 rounded-full">
                        {{ $upcomingGames->count() }} In Development
                    </span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach($upcomingGames as $game)
                        <div class="bg-stone-50 rounded-2xl border border-dashed border-stone-300 p-6 flex flex-col justify-between opacity-85">
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="w-10 h-10 rounded-xl bg-stone-200 text-stone-600 flex items-center justify-center font-bold text-lg">
                                        ⏳
                                    </div>
                                    <span class="text-[11px] font-extrabold uppercase tracking-wider text-stone-500 bg-stone-200/70 px-2.5 py-1 rounded-full">
                                        {{ __('Coming Soon') }}
                                    </span>
                                </div>
                                <h4 class="text-lg font-bold text-stone-800">
                                    {{ $game->liveTranslation()?->title ?? $game->title }}
                                </h4>
                                <p class="text-stone-500 text-xs sm:text-sm mt-2 leading-relaxed">
                                    {{ $game->liveTranslation()?->description ?? $game->description }}
                                </p>
                            </div>
                            <div class="mt-6 pt-4 border-t border-stone-200 text-xs text-stone-400 font-medium">
                                {{ __('Stay tuned for release') }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

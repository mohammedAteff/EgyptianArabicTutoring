<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? $title . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', config('business.site_name')) }}</title>
    <meta name="description" content="{{ $metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', 'Master authentic conversational Egyptian Arabic through structured 1-on-1 private lessons with a native educator in Cairo.') }}">
    @php
        $urlService = app(\App\Domains\CMS\Services\LocalizedUrlService::class);
        $isFallbackMode = $isFallback ?? false;
        $eligibleHreflangLocales = $entityLocales ?? null;
        if (! $eligibleHreflangLocales && isset($entity) && method_exists($entity, 'getAvailableLocales')) {
            $eligibleHreflangLocales = $entity->getAvailableLocales();
        } elseif (! $eligibleHreflangLocales && isset($resource) && method_exists($resource, 'getAvailableLocales')) {
            $eligibleHreflangLocales = $resource->getAvailableLocales();
        } elseif (! $eligibleHreflangLocales && isset($game) && method_exists($game, 'getAvailableLocales')) {
            $eligibleHreflangLocales = $game->getAvailableLocales();
        } elseif (! $eligibleHreflangLocales && isset($page) && method_exists($page, 'getAvailableLocales')) {
            $eligibleHreflangLocales = $page->getAvailableLocales();
        }
        $canonicalUrl = $urlService->getCanonicalUrl(app()->getLocale(), $isFallbackMode);
        $hreflangAlternates = $urlService->getHreflangAlternates($isFallbackMode, $eligibleHreflangLocales);
        $currentLocale = app()->getLocale();
        $availableLocales = [
            'en' => ['label' => 'English', 'code' => 'EN'],
            'fr' => ['label' => 'Français', 'code' => 'FR'],
            'de' => ['label' => 'Deutsch', 'code' => 'DE'],
        ];
    @endphp
    <link rel="canonical" href="{{ isset($blog) && $blog->canonical_url ? $blog->canonical_url : $canonicalUrl }}">
    @foreach($hreflangAlternates as $langCode => $altUrl)
        <link rel="alternate" hreflang="{{ $langCode }}" href="{{ $altUrl }}">
    @endforeach

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ isset($title) ? $title . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', config('business.site_name')) }}">
    <meta property="og:description" content="{{ $metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', 'Master authentic conversational Egyptian Arabic.') }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">

    <!-- Google Fonts Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="analytics-event-url" content="{{ route('analytics.track') }}">
    <meta name="analytics-base-path" content="{{ parse_url(route('home'), PHP_URL_PATH) }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
<body class="min-h-full flex flex-col bg-[#FAF8F5] text-stone-900 font-sans antialiased selection:bg-terracotta-500 selection:text-white"
      x-data="{ mobileNav: false }">

    @if($isPreview ?? false)
        <div class="bg-amber-500 text-slate-950 font-bold px-4 py-2.5 text-center text-xs tracking-wider uppercase shadow-md sticky top-0 z-50 flex items-center justify-center gap-2">
            <svg class="w-4 h-4 text-slate-950 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span>Draft Preview Mode — This content is unpublished and not accessible to the public.</span>
        </div>
    @endif

    <!-- Top Announcement Bar (Subtle & Informative) -->
    <div class="bg-nile-900 text-stone-200 text-xs py-2 px-4 text-center">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <span class="hidden sm:inline font-medium">Authentic Egyptian Colloquial Arabic (ECA) • Private 1-on-1 Mentorship</span>
            <span class="sm:hidden font-medium mx-auto">Private Egyptian Arabic Lessons</span>
            <div class="hidden sm:flex items-center gap-4 text-stone-300">
                <span>📍 Cairo, Egypt</span>
                @php $cairoClock = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay('Africa/Cairo', now('UTC')); @endphp
                <span>🕒 {{ now('Africa/Cairo')->format('g:i A') }} Cairo Time ({{ $cairoClock['utc_offset'] }})</span>
            </div>
        </div>
    </div>

    <!-- Social Proof Engagement Banner (Phase 2) -->
    <x-social-proof-banner />

    <!-- Promotional Offers Banner / Modal (Phase 4) -->
    <x-promotional-banner />

    <!-- Navigation Header -->
    <header class="sticky top-0 z-40 bg-[#FAF8F5]/90 backdrop-blur-md border-b border-stone-200/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Brand Logo / Name -->
                <a href="{{ localized_url('home') }}" class="flex items-center gap-3 group">
                        <div class="w-11 h-11 rounded-xl bg-terracotta-500 text-white flex items-center justify-center font-bold text-xl shadow-sm transition-transform group-hover:scale-105">
                        <span aria-hidden="true">A</span>
                    </div>
                    <div>
                        <div class="font-bold text-lg text-stone-900 leading-snug group-hover:text-terracotta-600 transition-colors">
                            {{ \App\Domains\CMS\Models\Setting::get('site_name', config('business.site_name')) }}
                        </div>
                        <div class="text-xs text-stone-500 font-medium flex items-center gap-1.5">
                            <span>Private Lessons with {{ config('app.tutor_name') }}</span>
                            <span>•</span>
                            <bdi dir="rtl" lang="ar" class="font-cairo text-stone-600">اتعلم مصري صح</bdi>
                        </div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center gap-7">
                    <a href="{{ localized_url('home') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('home*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ __('Home') }}
                    </a>
                    <a href="{{ localized_url('resources') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('resources*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ __('Resources') }}
                    </a>
                    <a href="{{ localized_url('games') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('games*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ __('Games') }}
                    </a>
                    <a href="{{ localized_url('pricing') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('pricing*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ __('Pricing') }}
                    </a>
                    <a href="{{ localized_url('about') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('about*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ __('About') }}
                    </a>
                    <a href="{{ localized_url('faq') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('faq*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ __('FAQ') }}
                    </a>
                    <a href="{{ route('blog.index') }}" class="text-sm font-semibold transition-colors {{ request()->routeIs('blog.*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">Blog</a>

                    <!-- Language Switcher (Desktop) -->
                    <div class="flex items-center bg-stone-100 p-1 rounded-full border border-stone-200/80 text-xs font-semibold" role="group" aria-label="{{ __('Language selector') }}">
                        @foreach($availableLocales as $code => $loc)
                            @if($currentLocale === $code)
                                <span class="px-2.5 py-1 rounded-full bg-terracotta-500 text-white shadow-xs font-bold" aria-current="true">
                                    {{ $loc['code'] }}
                                </span>
                            @else
                                <a href="{{ $urlService->getUrlForLocale($code) }}"
                                    @if(request()->routeIs('booking.*'))
                                        @click.prevent="if (window.Livewire) { const c = document.querySelector('[wire\\:id]'); if (c) { const comp = window.Livewire.find(c.getAttribute('wire:id')); if (comp) { const pending = {}; const firstNameEl = document.getElementById('first_name'); const lastNameEl = document.getElementById('last_name'); const dobEl = document.getElementById('date_of_birth'); const emailEl = document.getElementById('email'); const phoneEl = document.getElementById('phone'); const notesEl = document.getElementById('notes'); if (firstNameEl) pending.first_name = firstNameEl.value; if (lastNameEl) pending.last_name = lastNameEl.value; if (dobEl) pending.date_of_birth = dobEl.value; if (emailEl) pending.email = emailEl.value; if (phoneEl) pending.phone = phoneEl.value; if (notesEl) pending.notes = notesEl.value; comp.switchLanguage('{{ $code }}', pending); return; } } } window.location.href = '{{ $urlService->getUrlForLocale($code) }}';"
                                    @endif
                                   class="px-2.5 py-1 rounded-full text-stone-600 hover:text-stone-900 transition-colors"
                                   title="{{ $loc['label'] }}"
                                   aria-label="{{ $loc['label'] }}">
                                    {{ $loc['code'] }}
                                </a>
                            @endif
                        @endforeach
                    </div>

                    <!-- Prominent Booking CTA -->
                    <a href="{{ localized_url('booking') }}"
                       data-cta="booking"
                       class="inline-flex items-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white text-sm font-semibold px-5 py-2.5 rounded-full shadow-sm hover:shadow transition-all transform hover:-translate-y-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>{{ __('Book a Lesson') }}</span>
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <div class="flex items-center gap-3 lg:hidden">
                    <a href="{{ localized_url('booking') }}"
                       data-cta="booking"
                       class="bg-terracotta-500 text-white text-xs font-semibold px-3.5 py-2 rounded-full shadow-sm">
                        {{ __('Book Now') }}
                    </a>
                    <button type="button"
                            @click="mobileNav = !mobileNav"
                            aria-label="Toggle navigation menu"
                            class="p-2 text-stone-700 hover:text-stone-900 rounded-lg focus:outline-none focus:ring-2 focus:ring-terracotta-500">
                        <svg class="w-6 h-6" x-show="!mobileNav" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg class="w-6 h-6" x-show="mobileNav" x-cloak fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Drawer -->
        <div x-show="mobileNav"
             x-cloak
             @click.away="mobileNav = false"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-2"
             class="lg:hidden bg-white border-b border-stone-200 px-4 pt-2 pb-6 space-y-3 shadow-lg">
            <a href="{{ localized_url('home') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">{{ __('Home') }}</a>
            <a href="{{ localized_url('booking') }}" data-cta="booking" class="block px-3 py-2 text-base font-semibold rounded-lg bg-terracotta-50 text-terracotta-600">📅 {{ __('Book a Lesson') }}</a>
            <a href="{{ localized_url('resources') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">📚 {{ __('Resources') }}</a>
            <a href="{{ localized_url('games') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">🎮 {{ __('Games') }}</a>
            <a href="{{ localized_url('pricing') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">💳 {{ __('Pricing') }}</a>
            <a href="{{ localized_url('about') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">{{ __('About') }}</a>
            <a href="{{ localized_url('faq') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">{{ __('FAQ') }}</a>
            <a href="{{ route('blog.index') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">Blog</a>

            <!-- Mobile Language Switcher -->
            <div class="pt-3 border-t border-stone-100">
                <div class="text-xs font-semibold uppercase tracking-wider text-stone-400 mb-2 px-3">
                    {{ __('Language') }}
                </div>
                <div class="flex items-center gap-2 px-3" role="group" aria-label="{{ __('Language selector') }}">
                    @foreach($availableLocales as $code => $loc)
                        @if($currentLocale === $code)
                            <span class="flex-1 text-center py-2 rounded-xl bg-terracotta-500 text-white font-bold text-xs shadow-xs" aria-current="true">
                                {{ $loc['label'] }} ({{ $loc['code'] }})
                            </span>
                        @else
                            <a href="{{ $urlService->getUrlForLocale($code) }}"
                               @if(request()->routeIs('booking.*'))
                                   @click.prevent="if (window.Livewire) { const c = document.querySelector('[wire\\:id]'); if (c) { const comp = window.Livewire.find(c.getAttribute('wire:id')); if (comp) { const pending = {}; const firstNameEl = document.getElementById('first_name'); const lastNameEl = document.getElementById('last_name'); const dobEl = document.getElementById('date_of_birth'); const emailEl = document.getElementById('email'); const phoneEl = document.getElementById('phone'); const notesEl = document.getElementById('notes'); if (firstNameEl) pending.first_name = firstNameEl.value; if (lastNameEl) pending.last_name = lastNameEl.value; if (dobEl) pending.date_of_birth = dobEl.value; if (emailEl) pending.email = emailEl.value; if (phoneEl) pending.phone = phoneEl.value; if (notesEl) pending.notes = notesEl.value; comp.switchLanguage('{{ $code }}', pending); return; } } } window.location.href = '{{ $urlService->getUrlForLocale($code) }}';"
                               @endif
                               class="flex-1 text-center py-2 rounded-xl bg-stone-100 hover:bg-stone-200 text-stone-700 font-semibold text-xs transition-colors"
                               aria-label="{{ $loc['label'] }}">
                                {{ $loc['label'] }} ({{ $loc['code'] }})
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-grow">
        @if(isset($slot) && ($slot instanceof \Illuminate\Contracts\Support\Htmlable || is_string($slot)))
            {{ $slot }}
        @endif
        @yield('content')
    </main>

    <!-- Public Footer -->
    <footer class="bg-stone-900 text-stone-300 border-t border-stone-800 mt-24">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                <!-- Col 1: Bio & Mission -->
                <div class="md:col-span-2 space-y-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-terracotta-500 text-white flex items-center justify-center font-bold text-lg font-cairo">
                            <span aria-hidden="true">A</span>
                        </div>
                        <span class="font-bold text-white text-lg tracking-tight">
                            {{ \App\Domains\CMS\Models\Setting::get('site_name', config('business.site_name')) }}
                        </span>
                    </div>
                    <p class="text-stone-400 text-sm max-w-md leading-relaxed">
                        {{ \App\Domains\CMS\Models\Setting::get('site_footer_text', 'Dedicated private Egyptian Arabic language instruction for expatriates, travelers, heritage speakers, and passionate learners worldwide. Real conversational mastery from Cairo.') }}
                    </p>
                    <div class="pt-2 flex items-center gap-4 text-xs text-stone-400">
                        <span class="inline-flex items-center gap-1.5 text-emerald-400">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            First-party analytics only
                        </span>
                        <span>•</span>
                        <span>Zero third-party ad trackers</span>
                    </div>
                </div>

                <!-- Col 2: Navigation Links -->
                <div>
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">{{ __('Learn & Practice') }}</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ localized_url('booking') }}" data-cta="booking" class="hover:text-white transition-colors">{{ __('Book a Lesson') }}</a></li>
                        <li><a href="{{ localized_url('pricing') }}" class="hover:text-white transition-colors">{{ __('Pricing') }}</a></li>
                        <li><a href="{{ localized_url('resources') }}" class="hover:text-white transition-colors">{{ __('Resources') }}</a></li>
                        <li><a href="{{ localized_url('games') }}" class="hover:text-white transition-colors">{{ __('Games') }}</a></li>
                        <li><a href="{{ localized_url('about') }}" class="hover:text-white transition-colors">{{ __('About') }}</a></li>
                        <li><a href="{{ localized_url('faq') }}" class="hover:text-white transition-colors">{{ __('FAQ') }}</a></li>
                        <li><a href="{{ localized_url('privacy') }}" class="hover:text-white transition-colors">{{ __('Privacy') }}</a></li>
                        <li><a href="{{ localized_url('terms') }}" class="hover:text-white transition-colors">{{ __('Terms') }}</a></li>
                    </ul>
                </div>

                <!-- Col 3: Direct Connect & Socials -->
                <div>
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">{{ __('Connect Directly') }}</h3>
                    <ul class="space-y-3 text-sm">
                        @php
                            $socialLinks = \App\Domains\CMS\Models\SocialLink::enabled()->get();
                        @endphp
                        @forelse($socialLinks as $social)
                            @php
                                $platform = strtolower($social->platform);
                                $label = $social->label ?: ucfirst($platform);
                                $accessibleLabel = "Visit our {$label} page";
                            @endphp
                            <li>
                                <a href="{{ $social->getFormattedUrl() }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   aria-label="{{ $accessibleLabel }}"
                                   data-social-platform="{{ $platform }}"
                                   class="social-footer-link group inline-flex items-center gap-2.5 text-stone-400 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-terracotta-500 rounded-md py-1 px-1.5 -ml-1.5">
                                    @if($platform === 'instagram')
                                        <svg class="w-4 h-4 shrink-0 text-pink-400 group-hover:text-pink-300 transition-colors" aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/>
                                            <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/>
                                            <line x1="17.5" x2="17.51" y1="6.5" y2="6.5"/>
                                        </svg>
                                    @elseif($platform === 'tiktok')
                                        <svg class="w-4 h-4 shrink-0 text-cyan-400 group-hover:text-cyan-300 transition-colors" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.24 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
                                        </svg>
                                    @elseif($platform === 'youtube')
                                        <svg class="w-4 h-4 shrink-0 text-red-500 group-hover:text-red-400 transition-colors" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                                        </svg>
                                    @elseif($platform === 'telegram')
                                        <svg class="w-4 h-4 shrink-0 text-sky-400 group-hover:text-sky-300 transition-colors" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                                        </svg>
                                    @elseif($platform === 'whatsapp')
                                        <svg class="w-4 h-4 shrink-0 text-emerald-400 group-hover:text-emerald-300 transition-colors" aria-hidden="true" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M.057 24l1.687-6.163c-1.041-1.804-1.588-3.849-1.587-5.946.003-6.556 5.338-11.891 11.893-11.891 3.181.001 6.167 1.24 8.413 3.488 2.245 2.248 3.481 5.236 3.48 8.414-.003 6.557-5.338 11.892-11.893 11.892-1.99-.001-3.951-.5-5.688-1.448l-6.305 1.654zm6.597-3.807c1.676.995 3.276 1.591 5.392 1.592 5.448 0 9.886-4.434 9.889-9.885.002-5.462-4.415-9.89-9.881-9.892-5.452 0-9.887 4.434-9.889 9.884-.001 2.225.651 3.891 1.746 5.634l-.999 3.648 3.742-.981zm11.387-5.464c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.669.149-.198.297-.768.967-.941 1.165-.173.198-.347.223-.644.074-.297-.149-1.255-.462-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.521.151-.172.2-.296.3-.495.099-.198.05-.372-.025-.521-.075-.148-.669-1.611-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414z"/>
                                        </svg>
                                    @else
                                        <svg class="w-4 h-4 shrink-0 text-stone-400 group-hover:text-stone-300 transition-colors" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                                        </svg>
                                    @endif
                                    <span class="font-medium">{{ $label }}</span>
                                    <svg class="w-3 h-3 opacity-40 group-hover:opacity-100 transition-opacity ml-auto" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </li>
                        @empty
                            <li><span class="text-stone-500">Contact directly via email</span></li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <!-- Bottom Legal Bar -->
            <div class="mt-12 pt-8 border-t border-stone-800 flex flex-col sm:flex-row items-center justify-between text-xs text-stone-500 gap-4">
                <div>
                    © {{ date('Y') }} {{ \App\Domains\CMS\Models\Setting::get('site_name', config('business.site_name')) }}. {{ __('All rights reserved.') }}
                </div>
                <div class="flex items-center gap-6">
                    <a href="{{ localized_url('terms') }}" class="hover:text-stone-400 transition-colors">{{ __('Terms') }}</a>
                    <a href="{{ localized_url('privacy') }}" class="hover:text-stone-400 transition-colors">{{ __('Privacy') }}</a>
                    <span class="text-stone-600">Africa/Cairo System Time</span>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts

    <script>
    window.vaTrack = function(eventName, metadata = {}) {
        try {
            const payload = JSON.stringify({
                event_name: eventName,
                page: window.location.href,
                metadata: metadata,
                _token: '{{ csrf_token() }}'
            });
            if (navigator.sendBeacon) {
                const blob = new Blob([payload], { type: 'application/json' });
                navigator.sendBeacon('{{ route('analytics.track') }}', blob);
            } else {
                fetch('{{ route('analytics.track') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: payload,
                    keepalive: true
                }).catch(() => {});
            }
        } catch (e) {}
    };

    document.addEventListener('DOMContentLoaded', () => {
        // Non-blocking social and booking click tracking (Section 31 & Section 87)
        document.querySelectorAll('a[href]').forEach(el => {
            const href = el.getAttribute('href');
            if (!href) return;

            const socialPlatform = (el.getAttribute('data-social-platform') || '').toLowerCase();
            const isWhatsApp = href.includes('wa.me') || href.includes('whatsapp.com') || socialPlatform === 'whatsapp';
            const isTelegram = href.includes('t.me') || href.includes('telegram.me') || socialPlatform === 'telegram';
            const isOtherSocial = Boolean(socialPlatform) || 
                href.includes('youtube.com') || href.includes('instagram.com') || 
                href.includes('facebook.com') || href.includes('twitter.com') || href.includes('x.com') ||
                href.includes('linkedin.com');

            if (isWhatsApp) {
                el.addEventListener('click', () => {
                    window.vaTrack('whatsapp_clicked', { target: href, target_url: href, platform: 'whatsapp', placement: 'footer' });
                });
            } else if (isTelegram) {
                el.addEventListener('click', () => {
                    window.vaTrack('telegram_clicked', { target: href, target_url: href, platform: 'telegram', placement: 'footer' });
                });
            } else if (isOtherSocial) {
                el.addEventListener('click', () => {
                    window.vaTrack('social_link_clicked', { platform: socialPlatform || 'social', target: href, target_url: href, placement: 'footer' });
                });
            } else {
                // Booking CTA check: strictly ignore ICS, reschedule, cancellation, and download actions
                const isActionOrExport = href.includes('.ics') || href.includes('/ics') || href.includes('/reschedule') || href.includes('/cancel') || href.includes('/download');
                const hasBookingCtaAttr = el.getAttribute('data-cta') === 'booking';
                const isBookingPath = href === '#booking-section' ||
                    href === '/booking' || href.startsWith('/booking?') ||
                    href === '/fr/reservation' || href.startsWith('/fr/reservation?') ||
                    href === '/de/buchen' || href.startsWith('/de/buchen?');

                if (!isActionOrExport && (hasBookingCtaAttr || isBookingPath)) {
                    el.addEventListener('click', () => {
                        window.vaTrack('booking_cta_clicked', {
                            label: el.innerText.trim().slice(0, 100),
                            button_text: el.innerText.trim().slice(0, 100),
                            cta_location: el.closest('header') ? 'header' : (el.closest('footer') ? 'footer' : 'page'),
                            target: href
                        });
                    });
                }
            }
        });
    });
    </script>
</body>
</html>

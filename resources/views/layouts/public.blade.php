<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $contentLocale ?? (($isFallback ?? false) ? 'en' : app()->getLocale())) }}" dir="ltr" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? $title . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', config('public_content.site_name')) }}</title>
    <meta name="description" content="{{ $metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', config('public_content.home.0.blocks.0.text', 'Personalized Arabic lessons and practical learning resources.')) }}">
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
        $canonicalUrl = $canonicalOverride ?? $urlService->getCanonicalUrl(app()->getLocale(), $isFallbackMode);
        $hreflangAlternates = $articleAlternates ?? $urlService->getHreflangAlternates($isFallbackMode, $eligibleHreflangLocales);
        $currentLocale = app()->getLocale();
        $availableLocales = [
            'en' => ['label' => 'English', 'code' => 'EN'],
            'fr' => ['label' => 'Français', 'code' => 'FR'],
            'de' => ['label' => 'Deutsch', 'code' => 'DE'],
        ];
    @endphp
    <link rel="canonical" href="{{ $canonicalUrl }}">
    @foreach($hreflangAlternates as $langCode => $altUrl)
        <link rel="alternate" hreflang="{{ $langCode }}" href="{{ $altUrl }}">
    @endforeach

    <!-- Open Graph -->
    <meta property="og:type" content="{{ $ogType ?? 'website' }}">
    <meta property="og:title" content="{{ isset($title) ? $title . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', config('public_content.site_name')) }}">
    <meta property="og:description" content="{{ $metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', 'Personalized Arabic tutoring, practical learning resources, and creative ways to keep your Arabic active.') }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">

    <meta name="twitter:card" content="{{ !empty($ogImage) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $title ?? config('public_content.site_name') }}">
    <meta name="twitter:description" content="{{ $metaDescription ?? 'Personalized Arabic lessons and practical learning resources.' }}">
    @if(!empty($ogImage))
        <meta property="og:image" content="{{ url($ogImage) }}">
        <meta property="og:image:alt" content="{{ $blog->featured_image_alt ?? '' }}">
        <meta name="twitter:image" content="{{ url($ogImage) }}">
    @endif
    @if(isset($blog))
        <meta property="og:locale" content="{{ ['en'=>'en_US','fr'=>'fr_FR','de'=>'de_DE'][$blog->locale] }}">
        @if($blog->published_at)<meta property="article:published_time" content="{{ $blog->published_at->toIso8601String() }}">@endif
        @if($blog->editorial_updated_at)<meta property="article:modified_time" content="{{ $blog->editorial_updated_at->toIso8601String() }}">@endif
    @endif
    @if(($isPreview ?? false) || ($noIndex ?? false))<meta name="robots" content="noindex, nofollow, noarchive">@endif
    @if(!empty($articleSchema))
        <script type="application/ld+json">{!! json_encode($articleSchema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
    @endif

    <!-- Google Fonts Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="analytics-disabled" content="{{ app(\App\Domains\Analytics\Services\AnalyticsService::class)->excluded(request()) ? '1' : '0' }}">
    <meta name="analytics-event-url" content="{{ route('analytics.track') }}">
    <meta name="analytics-base-path" content="{{ parse_url(route('home'), PHP_URL_PATH) }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-full flex flex-col bg-[#FAF8F5] text-stone-900 font-sans antialiased selection:bg-terracotta-500 selection:text-white"
      x-data="{ mobileNav: false }">

    @if($isPreview ?? false)
        <div class="bg-amber-500 text-slate-950 font-bold px-4 py-2.5 text-center text-xs tracking-wider uppercase shadow-md sticky top-0 z-50 flex items-center justify-center gap-2">
            <svg class="w-4 h-4 text-slate-950 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span>{{ website_text('shared', 'Draft Preview Mode — This content is unpublished and not accessible to the public.') }}</span>
        </div>
    @endif

    <x-announcement-banner :announcement="$siteAnnouncement" />

    <!-- Top Announcement Bar (Subtle & Informative) -->
    <div class="bg-nile-900 text-stone-200 text-xs py-2 px-4 text-center">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-center gap-x-5 gap-y-2 sm:justify-between">
            <span class="hidden sm:inline font-medium">{{ website_text('shared', 'Egyptian Arabic · Modern Standard Arabic · Personalized 1-on-1 Lessons') }}</span>
            <span class="sm:hidden font-medium">{{ website_text('shared', 'Personalized Arabic Lessons') }}</span>
            <div class="flex flex-wrap items-center justify-center gap-3 sm:ml-auto sm:justify-end text-stone-300">
                <span class="hidden lg:inline">{{ website_text('shared', '📍 Cairo, Egypt') }}</span>
                @php $cairoClock = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay('Africa/Cairo', now('UTC')); @endphp
                <span class="hidden lg:inline">🕒 {{ now('Africa/Cairo')->format('g:i A') }} Cairo Time ({{ $cairoClock['utc_offset'] }})</span>
                @if($socialBannerEnabled && $publicSocialLinks->where('show_in_banner', true)->isNotEmpty())
                    <nav aria-label="{{ __('Social channels in top banner') }}" class="flex flex-wrap items-center justify-center gap-1" data-social-banner>
                        @foreach($publicSocialLinks->where('show_in_banner', true) as $social)
                            <a href="{{ $social->getFormattedUrl() }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('Visit our :platform page', ['platform' => $social->label ?: ucfirst($social->platform)]) }}" title="{{ $social->label }}" data-social-platform="{{ $social->platform }}" data-social-placement="top_banner" class="group inline-flex min-h-8 min-w-8 items-center justify-center rounded-md hover:bg-white/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">
                                <x-social-platform-icon :platform="$social->platform" />
                            </a>
                        @endforeach
                    </nav>
                @endif
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
                        <span aria-hidden="true">{{ website_text('shared', 'A') }}</span>
                    </div>
                    <div>
                        <div class="font-bold text-lg text-stone-900 leading-snug group-hover:text-terracotta-600 transition-colors">
                            {{ \App\Domains\CMS\Models\Setting::get('site_name', config('public_content.site_name')) }}
                        </div>
                        <div class="text-xs text-stone-500 font-medium flex items-center gap-1.5">
                            <span>Private Lessons with {{ config('app.tutor_name') }}</span>
                            <span>•</span>
                            <bdi dir="rtl" lang="ar" class="font-cairo text-stone-600">العربية</bdi>
                        </div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden lg:flex items-center gap-7">
                    <a href="{{ localized_url('home') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('home*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ website_label('Home') }}
                    </a>
                    <a href="{{ website_link('shared', 'resources', localized_url('resources')) }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('resources*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ website_label('Resources') }}
                    </a><a href="{{ website_link('shared', 'student', route('student.login')) }}" class="text-sm font-semibold text-nile-800 hover:underline">{{ ['en'=>'Student Portal','fr'=>'Espace étudiant','de'=>'Schülerportal'][app()->getLocale()] ?? 'Student Portal' }}</a>
                    <a href="{{ website_link('shared', 'games', localized_url('games')) }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('games*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ website_label('Games') }}
                    </a>
                    <a href="{{ website_link('shared', 'pricing', localized_url('pricing')) }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('pricing*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ website_label('Pricing') }}
                    </a>
                    <a href="{{ website_link('shared', 'about', localized_url('about')) }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('about*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ website_label('About') }}
                    </a>
                    <a href="{{ website_link('shared', 'faq', localized_url('faq')) }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('faq*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        {{ website_label('FAQ') }}
                    </a>
                    <a href="{{ localized_url('blogs') }}" class="text-sm font-semibold transition-colors {{ request()->routeIs('blog.*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">Blogs</a>

                    <!-- Language Switcher (Desktop) -->
                    <div class="flex items-center bg-stone-100 p-1 rounded-full border border-stone-200/80 text-xs font-semibold" role="group" aria-label="{{ website_label('Language selector') }}">
                        @foreach($availableLocales as $code => $loc)
                            @if($currentLocale === $code)
                                <span class="px-2.5 py-1 rounded-full bg-terracotta-500 text-white shadow-xs font-bold" aria-current="true">
                                    {{ $loc['code'] }}
                                </span>
                            @else
                                <a href="{{ $urlService->getUrlForLocale($code).(request()->getQueryString() ? '&' : '?').'lang='.$code }}"
                                    @if(request()->routeIs('booking.*'))
                                        @click.prevent="if (window.Livewire) { const c = document.querySelector('[wire\\:id]'); if (c) { const comp = window.Livewire.find(c.getAttribute('wire:id')); if (comp) { const pending = {}; const firstNameEl = document.getElementById('first_name'); const lastNameEl = document.getElementById('last_name'); const dobEl = document.getElementById('date_of_birth'); const emailEl = document.getElementById('email'); const phoneEl = document.getElementById('phone'); const notesEl = document.getElementById('notes'); if (firstNameEl) pending.first_name = firstNameEl.value; if (lastNameEl) pending.last_name = lastNameEl.value; if (dobEl) pending.date_of_birth = dobEl.value; if (emailEl) pending.email = emailEl.value; if (phoneEl) pending.phone = phoneEl.value; if (notesEl) pending.notes = notesEl.value; comp.switchLanguage('{{ $code }}', pending); return; } } } window.location.href = '{{ $urlService->getUrlForLocale($code).(request()->getQueryString() ? '&' : '?').'lang='.$code }}';"
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
                    <a href="{{ website_link('shared', 'booking', localized_url('booking')) }}"
                       data-cta="booking"
                       class="inline-flex items-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white text-sm font-semibold px-5 py-2.5 rounded-full shadow-sm hover:shadow transition-all transform hover:-translate-y-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>{{ website_label('Book a Lesson') }}</span>
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <div class="flex items-center gap-3 lg:hidden">
                    <a href="{{ website_link('shared', 'booking', localized_url('booking')) }}"
                       data-cta="booking"
                       class="bg-terracotta-500 text-white text-xs font-semibold px-3.5 py-2 rounded-full shadow-sm">
                        {{ website_label('Book Now') }}
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
            <a href="{{ localized_url('home') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">{{ website_label('Home') }}</a>
            <a href="{{ website_link('shared', 'booking', localized_url('booking')) }}" data-cta="booking" class="block px-3 py-2 text-base font-semibold rounded-lg bg-terracotta-50 text-terracotta-600">📅 {{ website_label('Book a Lesson') }}</a>
            <a href="{{ website_link('shared', 'student', route('student.login')) }}" class="block rounded-lg px-3 py-2 text-base font-semibold text-nile-800">{{ ['en'=>'Student Portal','fr'=>'Espace étudiant','de'=>'Schülerportal'][app()->getLocale()] ?? 'Student Portal' }}</a>
            <a href="{{ website_link('shared', 'resources', localized_url('resources')) }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">📚 {{ website_label('Resources') }}</a>
            <a href="{{ website_link('shared', 'games', localized_url('games')) }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">🎮 {{ website_label('Games') }}</a>
            <a href="{{ website_link('shared', 'pricing', localized_url('pricing')) }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">💳 {{ website_label('Pricing') }}</a>
            <a href="{{ website_link('shared', 'about', localized_url('about')) }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">{{ website_label('About') }}</a>
            <a href="{{ website_link('shared', 'faq', localized_url('faq')) }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">{{ website_label('FAQ') }}</a>
            <a href="{{ localized_url('blogs') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">{{ website_text('shared', 'Blogs') }}</a>

            <!-- Mobile Language Switcher -->
            <div class="pt-3 border-t border-stone-100">
                <div class="text-xs font-semibold uppercase tracking-wider text-stone-400 mb-2 px-3">
                    {{ website_label('Language') }}
                </div>
                <div class="flex items-center gap-2 px-3" role="group" aria-label="{{ website_label('Language selector') }}">
                    @foreach($availableLocales as $code => $loc)
                        @if($currentLocale === $code)
                            <span class="flex-1 text-center py-2 rounded-xl bg-terracotta-500 text-white font-bold text-xs shadow-xs" aria-current="true">
                                {{ $loc['label'] }} ({{ $loc['code'] }})
                            </span>
                        @else
                            <a href="{{ $urlService->getUrlForLocale($code).(request()->getQueryString() ? '&' : '?').'lang='.$code }}"
                               @if(request()->routeIs('booking.*'))
                                   @click.prevent="if (window.Livewire) { const c = document.querySelector('[wire\\:id]'); if (c) { const comp = window.Livewire.find(c.getAttribute('wire:id')); if (comp) { const pending = {}; const firstNameEl = document.getElementById('first_name'); const lastNameEl = document.getElementById('last_name'); const dobEl = document.getElementById('date_of_birth'); const emailEl = document.getElementById('email'); const phoneEl = document.getElementById('phone'); const notesEl = document.getElementById('notes'); if (firstNameEl) pending.first_name = firstNameEl.value; if (lastNameEl) pending.last_name = lastNameEl.value; if (dobEl) pending.date_of_birth = dobEl.value; if (emailEl) pending.email = emailEl.value; if (phoneEl) pending.phone = phoneEl.value; if (notesEl) pending.notes = notesEl.value; comp.switchLanguage('{{ $code }}', pending); return; } } } window.location.href = '{{ $urlService->getUrlForLocale($code).(request()->getQueryString() ? '&' : '?').'lang='.$code }}';"
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
        @if($isPreview ?? false)<div role="status" lang="{{ $copyLocale ?? 'en' }}" class="border-b border-amber-200 bg-amber-50 px-4 py-4 text-center text-sm text-amber-900"><strong>{{ website_text('shared', 'Administrator Preview Mode') }}</strong> — This is a private preview. Changes appear publicly only after you publish.</div>@endif
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
                            <span aria-hidden="true">{{ website_text('shared', 'A') }}</span>
                        </div>
                        <span class="font-bold text-white text-lg tracking-tight">
                            {{ \App\Domains\CMS\Models\Setting::get('site_name', config('public_content.site_name')) }}
                        </span>
                    </div>
                    <p class="text-stone-400 text-sm max-w-md leading-relaxed">
                        {{ app(\App\Domains\CMS\Services\WebsiteCopyService::class)->value('shared', 'footer', \App\Domains\CMS\Models\Setting::get('site_footer_text', config('public_content.footer'))) }}
                    </p>
                </div>

                <!-- Col 2: Navigation Links -->
                <div>
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">{{ website_label('Learn & Practice') }}</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ website_link('shared', 'booking', localized_url('booking')) }}" data-cta="booking" class="hover:text-white transition-colors">{{ website_label('Book a Lesson') }}</a></li>
                        <li><a href="{{ website_link('shared', 'pricing', localized_url('pricing')) }}" class="hover:text-white transition-colors">{{ website_label('Pricing') }}</a></li>
                        <li><a href="{{ website_link('shared', 'resources', localized_url('resources')) }}" class="hover:text-white transition-colors">{{ website_label('Resources') }}</a></li>
                        <li><a href="{{ website_link('shared', 'games', localized_url('games')) }}" class="hover:text-white transition-colors">{{ website_label('Games') }}</a></li>
                        <li><a href="{{ website_link('shared', 'about', localized_url('about')) }}" class="hover:text-white transition-colors">{{ website_label('About') }}</a></li>
                        <li><a href="{{ website_link('shared', 'faq', localized_url('faq')) }}" class="hover:text-white transition-colors">{{ website_label('FAQ') }}</a></li>
                        <li><a href="{{ localized_url('blogs') }}" class="hover:text-white transition-colors">{{ website_text('shared', 'Blogs') }}</a></li>
                        <li><a href="{{ website_link('shared', 'student', route('student.login')) }}" class="hover:text-white transition-colors">{{ website_text('shared', 'Student Portal') }}</a></li>
                        <li><a href="{{ website_link('shared', 'policy', route('policy')) }}" class="hover:text-white transition-colors">{{ website_text('shared', 'Booking & Refund Policy') }}</a></li>
                        <li><a href="{{ localized_url('privacy') }}" class="hover:text-white transition-colors">{{ website_label('Privacy') }}</a></li>
                        <li><a href="{{ localized_url('terms') }}" class="hover:text-white transition-colors">{{ website_label('Terms') }}</a></li>
                    </ul>
                </div>

                <!-- Col 3: Direct Connect & Socials -->
                <div>
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">{{ website_label('Connect Directly') }}</h3>
                    <ul class="space-y-3 text-sm">
                        @forelse($publicSocialLinks->where('show_in_footer', true) as $social)
                            @php
                                $platform = strtolower($social->platform);
                                $label = __($social->label ?: ucfirst($platform));
                                $accessibleLabel = __('Visit our :platform page', ['platform' => $label]);
                            @endphp
                            <li>
                                <a href="{{ $social->getFormattedUrl() }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   aria-label="{{ $accessibleLabel }}"
                                   data-social-platform="{{ $platform }}" data-social-placement="footer_social"
                                   class="social-footer-link group inline-flex items-center gap-2.5 text-stone-400 hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-terracotta-500 rounded-md py-1 px-1.5 -ml-1.5">
                                    <x-social-platform-icon :platform="$platform" />
                                    <span class="font-medium">{{ $label }}</span>
                                    <svg class="w-3 h-3 opacity-40 group-hover:opacity-100 transition-opacity ml-auto" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </li>
                        @empty
                            <li><span class="text-stone-500">{{ website_text('shared', 'Public contact details will be added once confirmed.') }}</span></li>
                        @endforelse
                    </ul>
                </div>
            </div>

            <!-- Bottom Legal Bar -->
            <div class="mt-12 pt-8 border-t border-stone-800 flex flex-col sm:flex-row items-center justify-between text-xs text-stone-500 gap-4">
                <div>
                    © {{ date('Y') }} {{ \App\Domains\CMS\Models\Setting::get('site_name', config('public_content.site_name')) }}. {{ website_label('All rights reserved.') }}
                </div>
                <div class="flex items-center gap-6">
                    <a href="{{ localized_url('terms') }}" class="hover:text-stone-400 transition-colors">{{ website_label('Terms') }}</a>
                    <a href="{{ localized_url('privacy') }}" class="hover:text-stone-400 transition-colors">{{ website_label('Privacy') }}</a>
                </div>
            </div>
        </div>
    </footer>

    @livewireScripts

    <script>
    window.vaTrack = function(eventName, metadata = {}) {
        if (document.querySelector('meta[name="analytics-disabled"]')?.content === '1') return;
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
            if (socialPlatform || el.hasAttribute('data-whatsapp-cta')) return;
            const isWhatsApp = href.includes('wa.me') || href.includes('whatsapp.com') || socialPlatform === 'whatsapp';
            const isTelegram = href.includes('t.me') || href.includes('telegram.me') || socialPlatform === 'telegram';
            const isOtherSocial = Boolean(socialPlatform) || 
                href.includes('youtube.com') || href.includes('instagram.com') || 
                href.includes('facebook.com') || href.includes('twitter.com') || href.includes('x.com') ||
                href.includes('linkedin.com');

            if (isWhatsApp && !el.hasAttribute('data-whatsapp-cta')) {
                el.addEventListener('click', () => {
                    window.vaTrack('whatsapp_clicked', { target: href, target_url: href, platform: 'whatsapp', placement: 'footer_social', context: 'public', language: @js(app()->getLocale()) });
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
<x-whatsapp-cta context="public" />
</body>
</html>

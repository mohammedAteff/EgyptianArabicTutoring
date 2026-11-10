<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $contentLocale ?? (($isFallback ?? false) ? 'en' : app()->getLocale())) }}" data-ui-locale="{{ app()->getLocale() }}" dir="ltr" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? __($title) . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', config('public_content.site_name')) }}</title>
    <meta name="description" content="{{ __($metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', config('public_content.home.0.blocks.0.text', 'Personalized Arabic lessons and practical learning resources.'))) }}">
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
    <meta property="og:title" content="{{ isset($title) ? __($title) . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', config('public_content.site_name')) }}">
    <meta property="og:description" content="{{ __($metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', 'Personalized Arabic tutoring, practical learning resources, and creative ways to keep your Arabic active.')) }}">
    <meta property="og:url" content="{{ $canonicalUrl }}">

    <meta name="twitter:card" content="{{ !empty($ogImage) ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ __($title ?? config('public_content.site_name')) }}">
    <meta name="twitter:description" content="{{ __($metaDescription ?? 'Personalized Arabic lessons and practical learning resources.') }}">
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

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/brand/favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="48x48" href="{{ asset('assets/brand/favicon-48.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/fonts/studio-fonts.css') }}">
    <link rel="preload" href="{{ asset('assets/fonts/newsreader-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('assets/fonts/instrument-sans-latin-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <x-ui-translations />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('game-assets')
    @livewireStyles
</head>
<body class="min-h-full flex flex-col bg-[#FAF8F5] text-stone-900 font-sans antialiased selection:bg-terracotta-500 selection:text-white @yield('body-class')">

    @if($isPreview ?? false)
        <div class="bg-amber-500 text-slate-950 font-bold px-4 py-2.5 text-center text-xs tracking-wider uppercase shadow-md sticky top-0 z-50 flex items-center justify-center gap-2">
            <svg class="w-4 h-4 text-slate-950 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span>{{ website_text('shared', 'Draft Preview Mode — This content is unpublished and not accessible to the public.') }}</span>
        </div>
    @endif

    <x-announcement-banner :announcement="$siteAnnouncement" />

    <!-- Social Proof Engagement Banner (Phase 2) -->
    <x-social-proof-banner />

    <!-- Promotional Offers Banner / Modal (Phase 4) -->
    <x-promotional-banner />

    <a href="#main-content" class="public-skip-link">{{ __('Skip to content') }}</a>
    <x-public-header :available-locales="$availableLocales" :current-locale="$currentLocale" :url-service="$urlService" :social-banner-enabled="$socialBannerEnabled" :public-social-links="$publicSocialLinks" />

    <!-- Main Content Area -->
    <main id="main-content" tabindex="-1" class="flex-grow">
        @if($isPreview ?? false)<div role="status" lang="{{ $copyLocale ?? 'en' }}" class="border-b border-amber-200 bg-amber-50 px-4 py-4 text-center text-sm text-amber-900"><strong>{{ website_text('shared', 'Administrator Preview Mode') }}</strong> {{ __('— This is a private preview. Changes appear publicly only after you publish.') }}</div>@endif
        @if(isset($slot) && ($slot instanceof \Illuminate\Contracts\Support\Htmlable || is_string($slot)))
            {{ $slot }}
        @endif
        @yield('content')
    </main>

    <!-- Public Footer -->
    <footer class="public-footer bg-stone-900 text-stone-300 border-t border-stone-800 mt-24">
        <div class="public-footer-inner max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="public-footer-columns grid grid-cols-1 md:grid-cols-4 gap-12">
                <!-- Col 1: Bio & Mission -->
                <div class="public-footer-brand md:col-span-2 space-y-4">
                    <a href="{{ localized_url('home') }}" class="public-brand-footer inline-block" aria-label="Arabic with Abdallah — {{ website_label('Home') }}">
                        <img src="{{ asset('assets/brand/wordmark-cream-520.webp') }}" srcset="{{ asset('assets/brand/wordmark-cream-520.webp') }} 520w, {{ asset('assets/brand/wordmark-cream-780.webp') }} 780w" sizes="260px" width="520" height="82" alt="Arabic with Abdallah" class="w-64 h-auto" loading="lazy">
                    </a>
                    <p class="text-stone-400 text-sm max-w-md leading-relaxed">
                        {{ app(\App\Domains\CMS\Services\WebsiteCopyService::class)->value('shared', 'footer', \App\Domains\CMS\Models\Setting::get('site_footer_text', config('public_content.footer'))) }}
                    </p>
                    @php $cairoClock = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay('Africa/Cairo', now('UTC')); @endphp
                    <p class="text-stone-400 text-sm">{{ website_text('shared', '📍 Cairo, Egypt') }} · {{ localized_date(now('Africa/Cairo'), 'g:i A') }} ({{ $cairoClock['utc_offset'] }})</p>
                </div>

                <!-- Col 2: Navigation Links -->
                <div>
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">{{ website_label('Learn & Practice') }}</h3>
                    <ul class="public-footer-links space-y-2.5 text-sm">
                        <li><a href="{{ website_link('shared', 'booking', localized_url('booking')) }}" data-cta="booking" class="hover:text-white transition-colors">{{ website_label('Book a Lesson') }}</a></li>
                        <li><a href="{{ website_link('shared', 'pricing', localized_url('pricing')) }}" class="hover:text-white transition-colors">{{ website_label('Pricing') }}</a></li>
                        <li><a href="{{ website_link('shared', 'resources', localized_url('resources')) }}" class="hover:text-white transition-colors">{{ website_label('Resources') }}</a></li>
                        <li><a href="{{ website_link('shared', 'games', localized_url('games')) }}" class="hover:text-white transition-colors">{{ website_label('Games') }}</a></li>
                        <li><a href="{{ website_link('shared', 'about', localized_url('about')) }}" class="hover:text-white transition-colors">{{ website_label('About') }}</a></li>
                        <li><a href="{{ website_link('shared', 'faq', localized_url('faq')) }}" class="hover:text-white transition-colors">{{ website_label('FAQ') }}</a></li>
                        <li><a href="{{ localized_url('blogs') }}" class="hover:text-white transition-colors">{{ website_text('shared', 'Blogs') }}</a></li>
                        <li><a href="{{ website_link('shared', 'student', route('student.login')) }}" class="hover:text-white transition-colors">{{ website_text('shared', 'Student Portal') }}</a></li>
                        <li><a href="{{ website_link('shared', 'policy', localized_url('policy')) }}" class="hover:text-white transition-colors">{{ website_text('shared', 'Booking & Refund Policy') }}</a></li>
                        <li><a href="{{ localized_url('privacy') }}" class="hover:text-white transition-colors">{{ website_label('Privacy') }}</a></li>
                        <li><a href="{{ localized_url('terms') }}" class="hover:text-white transition-colors">{{ website_label('Terms') }}</a></li>
                    </ul>
                </div>

                <!-- Col 3: Direct Connect & Socials -->
                @if($publicSocialLinks->where('show_in_footer', true)->isNotEmpty())
                <div>
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">{{ website_label('Connect Directly') }}</h3>
                    <ul class="space-y-3 text-sm">
                        @foreach($publicSocialLinks->where('show_in_footer', true) as $social)
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
                                    <span class="font-medium">{{ __($label) }}</span>
                                    <svg class="w-3 h-3 opacity-40 group-hover:opacity-100 transition-opacity ml-auto" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            <!-- Bottom Legal Bar -->
            <div class="public-footer-legal mt-12 pt-8 border-t border-stone-800 flex flex-col sm:flex-row items-center justify-between text-xs text-stone-500 gap-4">
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
<x-floating-social :links="$publicSocialLinks->where('show_in_floating', true)" />
<x-whatsapp-cta context="public" />
</body>
</html>

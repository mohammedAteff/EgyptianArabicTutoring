<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ isset($title) ? $title . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', 'Egyptian Arabic Tutoring') }}</title>
    <meta name="description" content="{{ $metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', 'Master authentic conversational Egyptian Arabic through structured 1-on-1 private lessons with a native educator in Cairo.') }}">
    <link rel="canonical" href="{{ url()->current() }}">

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ isset($title) ? $title . ' — ' : '' }}{{ \App\Domains\CMS\Models\Setting::get('site_name', 'Egyptian Arabic Tutoring') }}">
    <meta property="og:description" content="{{ $metaDescription ?? \App\Domains\CMS\Models\Setting::get('hero_subtitle', 'Master authentic conversational Egyptian Arabic.') }}">
    <meta property="og:url" content="{{ url()->current() }}">

    <!-- Google Fonts Preconnect -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

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
                <span>🕒 {{ now('Africa/Cairo')->format('g:i A') }} Cairo Time</span>
            </div>
        </div>
    </div>

    <!-- Navigation Header -->
    <header class="sticky top-0 z-40 bg-[#FAF8F5]/90 backdrop-blur-md border-b border-stone-200/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Brand Logo / Name -->
                <a href="{{ route('home') }}" class="flex items-center gap-3 group">
                    <div class="w-11 h-11 rounded-xl bg-terracotta-500 text-white flex items-center justify-center font-bold text-xl shadow-sm transition-transform group-hover:scale-105">
                        <span class="font-cairo">م</span>
                    </div>
                    <div>
                        <div class="font-bold text-lg text-stone-900 leading-snug group-hover:text-terracotta-600 transition-colors">
                            {{ \App\Domains\CMS\Models\Setting::get('site_name', 'Egyptian Arabic Tutoring') }}
                        </div>
                        <div class="text-xs text-stone-500 font-medium flex items-center gap-1.5">
                            <span>Private Lessons</span>
                            <span>•</span>
                            <span class="font-cairo text-stone-600">اتعلم مصري صح</span>
                        </div>
                    </div>
                </a>

                <!-- Desktop Navigation Links -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="{{ route('home') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('home') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        Home
                    </a>
                    <a href="{{ route('resources.index') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('resources.*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        Free Resources
                    </a>
                    <a href="{{ route('games.index') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('games.*') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        Games
                    </a>
                    <a href="{{ route('about') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('about') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        About & Method
                    </a>
                    <a href="{{ route('faq') }}"
                       class="text-sm font-semibold transition-colors {{ request()->routeIs('faq') ? 'text-terracotta-600' : 'text-stone-700 hover:text-stone-900' }}">
                        FAQ
                    </a>

                    <!-- Prominent Booking CTA -->
                    <a href="{{ route('booking.index') }}"
                       class="inline-flex items-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white text-sm font-semibold px-5 py-2.5 rounded-full shadow-sm hover:shadow transition-all transform hover:-translate-y-0.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Book a Session</span>
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <div class="flex items-center gap-3 md:hidden">
                    <a href="{{ route('booking.index') }}"
                       class="bg-terracotta-500 text-white text-xs font-semibold px-3.5 py-2 rounded-full shadow-sm">
                        Book Now
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
             class="md:hidden bg-white border-b border-stone-200 px-4 pt-2 pb-6 space-y-3 shadow-lg">
            <a href="{{ route('home') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">Home</a>
            <a href="{{ route('booking.index') }}" class="block px-3 py-2 text-base font-semibold rounded-lg bg-terracotta-50 text-terracotta-600">📅 Book a Session</a>
            <a href="{{ route('resources.index') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">📚 Free Resources & Workbooks</a>
            <a href="{{ route('games.index') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">🎮 Learning Games</a>
            <a href="{{ route('about') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">Teacher & Methodology</a>
            <a href="{{ route('faq') }}" class="block px-3 py-2 text-base font-medium rounded-lg text-stone-800 hover:bg-stone-50">Frequently Asked Questions</a>
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
                            م
                        </div>
                        <span class="font-bold text-white text-lg tracking-tight">
                            {{ \App\Domains\CMS\Models\Setting::get('site_name', 'Egyptian Arabic Tutoring') }}
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
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">Learn & Practice</h3>
                    <ul class="space-y-2.5 text-sm">
                        <li><a href="{{ route('booking.index') }}" class="hover:text-white transition-colors">Book a Lesson</a></li>
                        <li><a href="{{ route('resources.index') }}" class="hover:text-white transition-colors">Free Workbooks</a></li>
                        <li><a href="{{ route('games.index') }}" class="hover:text-white transition-colors">Language Games</a></li>
                        <li><a href="{{ route('about') }}" class="hover:text-white transition-colors">Teaching Method</a></li>
                        <li><a href="{{ route('faq') }}" class="hover:text-white transition-colors">Booking FAQ</a></li>
                    </ul>
                </div>

                <!-- Col 3: Direct Connect & Socials -->
                <div>
                    <h3 class="text-xs font-semibold text-stone-200 uppercase tracking-wider mb-4">Connect Directly</h3>
                    <ul class="space-y-2.5 text-sm">
                        @php
                            $socialLinks = \App\Domains\CMS\Models\SocialLink::enabled()->get();
                        @endphp
                        @forelse($socialLinks as $social)
                            <li>
                                <a href="{{ $social->getFormattedUrl() }}" target="_blank" rel="noopener noreferrer"
                                   class="hover:text-white transition-colors inline-flex items-center gap-2">
                                    <span>{{ $social->label ?? ucfirst($social->platform) }}</span>
                                    <svg class="w-3.5 h-3.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                            </li>
                        @empty
                            <li><span class="text-stone-500">Contact directly via email</span></li>
                        @endforelse
                        <li class="pt-2">
                            <a href="/admin/login" class="text-xs text-stone-600 hover:text-stone-400 transition-colors">Tutor Admin Portal</a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Bottom Legal Bar -->
            <div class="mt-12 pt-8 border-t border-stone-800 flex flex-col sm:flex-row items-center justify-between text-xs text-stone-500 gap-4">
                <div>
                    © {{ date('Y') }} {{ \App\Domains\CMS\Models\Setting::get('site_name', 'Egyptian Arabic Tutoring') }}. All rights reserved.
                </div>
                <div class="flex items-center gap-6">
                    <a href="{{ route('terms') }}" class="hover:text-stone-400 transition-colors">Terms of Service</a>
                    <a href="{{ route('privacy') }}" class="hover:text-stone-400 transition-colors">Privacy Policy</a>
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
        document.querySelectorAll('a[href]').forEach(el => {
            const href = el.getAttribute('href');
            if (!href) return;
            if (href.includes('wa.me') || href.includes('whatsapp.com')) {
                el.addEventListener('click', () => window.vaTrack('whatsapp_clicked', { target: href }));
            } else if (href.includes('t.me') || href.includes('telegram.me')) {
                el.addEventListener('click', () => window.vaTrack('telegram_clicked', { target: href }));
            } else if (href.includes('/book') || href === '#booking-section') {
                el.addEventListener('click', () => window.vaTrack('booking_cta_clicked', { label: el.innerText.trim().slice(0, 100) }));
            }
        });
    });
    </script>
</body>
</html>

@props(['availableLocales', 'currentLocale', 'urlService', 'socialBannerEnabled', 'publicSocialLinks'])
<header class="public-header"
        x-data="{
            mobileNav: false,
            fitMenu() {
                if (this.mobileNav) {
                    this.$refs.navigation.style.setProperty('--public-menu-available-height', Math.max(0, window.innerHeight - this.$refs.navigation.getBoundingClientRect().top - 16) + 'px');
                }
            },
            openMenu() {
                this.mobileNav = true;
                this.$nextTick(() => { this.$refs.navigation.querySelector('a').focus(); this.fitMenu(); });
            },
            closeMenu(restoreFocus = false) {
                this.mobileNav = false;
                if (restoreFocus) this.$refs.menuToggle.focus();
            }
        }"
        @keydown.escape.window="if (mobileNav) closeMenu(true)"
        @click.outside="if (mobileNav) closeMenu()"
        @resize.window="window.innerWidth >= 1280 ? closeMenu() : fitMenu()"
        @scroll.window.throttle.50ms="fitMenu()"
        @focusout="if (mobileNav && $event.relatedTarget && !$el.contains($event.relatedTarget)) closeMenu()">
    <div class="public-utility">
        <div class="public-utility-inner">
            <img src="{{ asset('assets/flags/4x3/eg.svg') }}" width="18" height="12" alt="" aria-hidden="true">
            <span>{{ __('Cairo, Egypt') }}</span><span class="public-utility-divider" aria-hidden="true">|</span>
            @php $cairoNow = now('Africa/Cairo'); @endphp
            <time data-cairo-clock datetime="{{ $cairoNow->toIso8601String() }}">{{ localized_date($cairoNow, 'g:i A') }}</time>
            @if($socialBannerEnabled && $publicSocialLinks->where('show_in_banner', true)->isNotEmpty())
                <nav class="public-utility-social" aria-label="{{ __('Social channels in top banner') }}" data-social-banner>
                    @foreach($publicSocialLinks->where('show_in_banner', true) as $social)
                        <a href="{{ $social->getFormattedUrl() }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('Visit our :platform page', ['platform' => $social->label ?: ucfirst($social->platform)]) }}" title="{{ $social->label }}" data-social-platform="{{ $social->platform }}" data-social-placement="top_banner">
                            <x-social-platform-icon :platform="$social->platform" />
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </div>
    <div class="public-header-inner">
        <a href="{{ localized_url('home') }}" class="public-brand" aria-label="Arabic with Abdallah — {{ website_label('Home') }}">
            <img src="{{ asset('assets/brand/wordmark-ink-520.webp') }}" srcset="{{ asset('assets/brand/wordmark-ink-520.webp') }} 520w, {{ asset('assets/brand/wordmark-ink-780.webp') }} 780w" sizes="(min-width: 1280px) 260px, (min-width: 768px) 230px, 164px" width="520" height="82" alt="Arabic with Abdallah">
        </a>

        <a href="{{ website_link('shared', 'booking', localized_url('booking')) }}" data-cta="booking" class="studio-button public-mobile-booking">{{ website_label('Book a Lesson') }}</a>

        <button type="button" class="public-menu-toggle" x-ref="menuToggle" aria-controls="public-navigation" aria-expanded="false" :aria-expanded="mobileNav.toString()"
                @click="mobileNav ? closeMenu(true) : openMenu()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path x-show="!mobileNav" d="M4 7h16M4 12h16M4 17h16" />
                <path x-show="mobileNav" x-cloak d="m6 6 12 12M6 18 18 6" />
            </svg>
            <span x-text="mobileNav ? @js(__('Close')) : @js(__('Menu'))">{{ __('Menu') }}</span>
        </button>

        <nav id="public-navigation" class="public-navigation" :class="{ 'is-open': mobileNav }" x-ref="navigation" aria-label="{{ __('Main navigation') }}">
            <div class="public-navigation-links">
                <a href="{{ website_link('shared', 'student', route('student.login')) }}" class="public-portal-link">{{ __(['en' => 'Student Portal', 'fr' => 'Espace étudiant', 'de' => 'Schülerportal'][$currentLocale] ?? 'Student Portal') }}</a>
                @foreach(['home' => 'Home', 'resources' => 'Resources', 'games' => 'Games', 'pricing' => 'Pricing', 'about' => 'About', 'faq' => 'FAQ', 'blogs' => 'Blogs'] as $destination => $label)
                    @php
                        $navigationUrl = in_array($destination, ['home', 'blogs'], true) ? localized_url($destination) : website_link('shared', $destination, localized_url($destination));
                        $isCurrent = $destination === 'blogs' ? request()->routeIs('blog.*') : request()->routeIs($destination, $destination.'.*');
                    @endphp
                    <a href="{{ $navigationUrl }}" @if($isCurrent) aria-current="page" @endif>{{ website_label($label) }}</a>
                @endforeach
            </div>
            <div class="public-header-language">
                <p class="public-language-label">{{ __('Language') }}</p>
                <x-public-language-switcher :available-locales="$availableLocales" :current-locale="$currentLocale" :url-service="$urlService" />
            </div>
            <div class="public-navigation-actions">
                <a href="{{ website_link('shared', 'booking', localized_url('booking')) }}" data-cta="booking" @if(request()->routeIs('booking.*')) aria-current="page" @endif class="studio-button">{{ website_label('Book a Lesson') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
            </div>
        </nav>
    </div>
</header>

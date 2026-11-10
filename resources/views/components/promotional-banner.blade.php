@props(['promotion' => null, 'preview' => false])

@php
    $promo = $promotion ?? app(\App\Domains\Marketing\Services\PromotionService::class)->getActivePromotion();
    $nowUtc = \Illuminate\Support\Carbon::now('UTC');

    // Strict final verification: never render an inactive or expired promotion outside preview mode
    if (! $promo) {
        return;
    }

    if (! $preview) {
        if (! $promo->is_active) {
            return;
        }
        if ($promo->starts_at && $promo->starts_at->isAfter($nowUtc)) {
            return;
        }
        if ($promo->ends_at && $promo->ends_at->isBefore($nowUtc)) {
            return;
        }
    }

    $ctaValue = (string) $promo->cta_url;
    $isLocalCta = str_starts_with($ctaValue, '/') && ! str_starts_with($ctaValue, '//')
        && ! preg_match('/[\x00-\x1F\x7F\\\\]/', $ctaValue);
    $isHttpsCta = filter_var($ctaValue, FILTER_VALIDATE_URL)
        && strtolower((string) parse_url($ctaValue, PHP_URL_SCHEME)) === 'https';
    if (! $isLocalCta && ! $isHttpsCta) {
        return;
    }
    $ctaHref = $isLocalCta ? rtrim(route('home'), '/').$ctaValue : $ctaValue;

    $endsAtIso = $promo->ends_at ? $promo->ends_at->toISOString() : null;
@endphp

<div x-data="{
    open: true,
    hasCountdown: {{ $promo->has_countdown && $endsAtIso ? 'true' : 'false' }},
    targetDate: '{{ $endsAtIso }}',
    days: 0,
    hours: 0,
    minutes: 0,
    seconds: 0,
    expired: false,
    init() {
        if (this.hasCountdown && this.targetDate) {
            this.updateCountdown();
            setInterval(() => this.updateCountdown(), 1000);
        }
    },
    updateCountdown() {
        const diff = new Date(this.targetDate) - new Date();
        if (diff <= 0) {
            this.expired = true;
            this.days = this.hours = this.minutes = this.seconds = 0;
            return;
        }
        this.days = Math.floor(diff / (1000 * 60 * 60 * 24));
        this.hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
        this.minutes = Math.floor((diff / 1000 / 60) % 60);
        this.seconds = Math.floor((diff / 1000) % 60);
    }
}" x-show="open && !expired" class="studio-promotion relative z-50">

    @if($promo->display_type === 'top_bar')
        <!-- Top Bar Format -->
        <aside aria-label="{{ __('Promotional Announcement') }}" class="studio-promotion-bar py-2.5 px-4 shadow-sm border-b">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-center sm:text-left">
                    @if($promo->banner_image_path)
                        <img src="{{ asset('storage/' . $promo->banner_image_path) }}" alt="" class="studio-promotion-image w-8 h-8 rounded-lg object-cover border shadow-xs hidden sm:inline-block">
                    @endif
                    <div>
                        <span class="studio-promotion-label font-extrabold tracking-wide uppercase text-[10px] px-2 py-0.5 rounded-full mr-1.5 border">{{ __('Offer') }}</span>
                        <strong class="studio-promotion-heading font-bold text-xs sm:text-sm">{{ $promo->headline }}</strong>
                        @if($promo->subheadline)
                            <span class="studio-promotion-copy hidden md:inline ml-1.5">— {{ $promo->subheadline }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex flex-wrap justify-center items-center gap-4">
                    <template x-if="hasCountdown">
                        <div class="studio-promotion-countdown flex items-center gap-1 font-mono font-bold px-2.5 py-1 rounded-lg text-[11px] shadow-xs">
                            <span x-text="String(days).padStart(2, '0')">00</span>{{ __('d :') }}
                            <span x-text="String(hours).padStart(2, '0')">00</span>{{ __('h :') }}
                            <span x-text="String(minutes).padStart(2, '0')">00</span>{{ __('m :') }}
                            <span x-text="String(seconds).padStart(2, '0')">00</span>{{ __('s') }}
                        </div>
                    </template>

                    <a href="{{ $ctaHref }}" class="studio-promotion-cta inline-flex items-center gap-1.5 px-3.5 py-1.5 font-bold rounded-xl shadow-xs transition-colors whitespace-nowrap text-xs">
                        <span>{{ $promo->cta_text }}</span>
                        <span>{{ __('→') }}</span>
                    </a>

                    <button type="button" @click="open = false" aria-label="{{ __('Close Announcement') }}" class="studio-promotion-close p-1 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

    @elseif($promo->display_type === 'floating_modal')
        <!-- Floating Attention Modal -->
        <div class="studio-promotion-overlay fixed inset-0 z-50 flex items-center justify-center p-4 backdrop-blur-xs" @click.self="open = false">
            <div class="studio-promotion-dialog rounded-3xl p-6 sm:p-8 max-w-lg w-full border shadow-2xl space-y-5 text-center relative animate-fade-in" role="dialog" aria-modal="true" aria-labelledby="promo-modal-title">
                <button type="button" @click="open = false" aria-label="{{ __('Close dialog') }}" class="studio-promotion-close absolute top-4 right-4 p-2 rounded-full">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                @if($promo->banner_image_path)
                    <img src="{{ asset('storage/' . $promo->banner_image_path) }}" alt="" class="studio-promotion-image w-full h-44 rounded-2xl object-cover border">
                @endif

                <div class="space-y-2">
                    <div class="studio-promotion-label inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border">
                        {{ __('Special Promotion') }}
                    </div>
                    <h2 id="promo-modal-title" class="studio-promotion-heading text-xl font-bold tracking-tight">{{ $promo->headline }}</h2>
                    @if($promo->subheadline)
                        <p class="studio-promotion-copy text-xs leading-relaxed max-w-md mx-auto">{{ $promo->subheadline }}</p>
                    @endif
                </div>

                <template x-if="hasCountdown">
                    <div class="studio-promotion-countdown flex-wrap justify-center p-3 rounded-2xl border inline-flex items-center gap-3">
                        <span class="text-xs font-semibold">{{ __('Ending in:') }}</span>
                        <div class="studio-promotion-countdown-value flex items-center gap-1 font-mono font-bold text-sm">
                            <span x-text="days">0</span>{{ __('d :') }}
                            <span x-text="hours">0</span>{{ __('h :') }}
                            <span x-text="minutes">0</span>{{ __('m :') }}
                            <span x-text="seconds">0</span>{{ __('s') }}
                        </div>
                    </div>
                </template>

                <div class="pt-2">
                    <a href="{{ $ctaHref }}" class="studio-promotion-cta inline-flex items-center justify-center w-full py-3 px-6 rounded-xl text-sm font-bold shadow-md transition-colors">
                        <span>{{ $promo->cta_text }}</span>
                    </a>
                </div>
            </div>
        </div>

    @else
        <!-- Inline Announcement Card -->
        <div class="studio-promotion-card rounded-3xl p-6 sm:p-8 border shadow-xs relative overflow-hidden">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                @if($promo->banner_image_path)
                    <img src="{{ asset('storage/' . $promo->banner_image_path) }}" alt="" class="studio-promotion-image w-24 h-24 rounded-2xl object-cover border shrink-0">
                @endif
                <div class="space-y-1.5 text-center sm:text-left flex-1">
                    <span class="studio-promotion-label text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full">{{ __('Featured Offer') }}</span>
                    <h3 class="studio-promotion-heading text-lg font-bold">{{ $promo->headline }}</h3>
                    @if($promo->subheadline)
                        <p class="studio-promotion-copy text-xs leading-relaxed">{{ $promo->subheadline }}</p>
                    @endif

                    <template x-if="hasCountdown">
                        <div class="studio-promotion-countdown pt-1 flex flex-wrap items-center justify-center sm:justify-start gap-2 font-mono text-xs font-bold">
                            <span>{{ __('Time Left:') }}</span>
                            <span x-text="days">0</span>{{ __('d') }}
                            <span x-text="hours">0</span>{{ __('h') }}
                            <span x-text="minutes">0</span>{{ __('m') }}
                            <span x-text="seconds">0</span>{{ __('s') }}
                        </div>
                    </template>
                </div>
                <div class="shrink-0">
                    <a href="{{ $ctaHref }}" class="studio-promotion-cta inline-flex items-center gap-2 px-5 py-2.5 rounded-xl text-xs font-bold shadow-xs transition-colors">
                        <span>{{ $promo->cta_text }}</span>
                        <span>{{ __('→') }}</span>
                    </a>
                </div>
            </div>
        </div>
    @endif

</div>

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
}" x-show="open && !expired" class="relative z-50">

    @if($promo->display_type === 'top_bar')
        <!-- Top Bar Format -->
        <aside aria-label="Promotional Announcement" class="bg-gradient-to-r from-amber-600 via-amber-500 to-amber-700 text-white py-2.5 px-4 shadow-sm border-b border-amber-600">
            <div class="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-3 text-center sm:text-left">
                    @if($promo->banner_image_path)
                        <img src="{{ asset('storage/' . $promo->banner_image_path) }}" alt="" class="w-8 h-8 rounded-lg object-cover border border-amber-300 shadow-xs hidden sm:inline-block">
                    @endif
                    <div>
                        <span class="font-extrabold tracking-wide uppercase text-[10px] bg-amber-800/60 px-2 py-0.5 rounded-full mr-1.5 border border-amber-400/30">Offer</span>
                        <strong class="font-bold text-white text-xs sm:text-sm">{{ $promo->headline }}</strong>
                        @if($promo->subheadline)
                            <span class="hidden md:inline text-amber-100 ml-1.5">— {{ $promo->subheadline }}</span>
                        @endif
                    </div>
                </div>

                <div class="flex items-center gap-4 shrink-0">
                    <template x-if="hasCountdown">
                        <div class="flex items-center gap-1 font-mono font-bold text-amber-950 bg-amber-100/90 px-2.5 py-1 rounded-lg text-[11px] shadow-xs">
                            <span x-text="String(days).padStart(2, '0')">00</span>d :
                            <span x-text="String(hours).padStart(2, '0')">00</span>h :
                            <span x-text="String(minutes).padStart(2, '0')">00</span>m :
                            <span x-text="String(seconds).padStart(2, '0')">00</span>s
                        </div>
                    </template>

                    <a href="{{ $ctaHref }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-slate-900 hover:bg-slate-800 text-amber-300 font-bold rounded-xl shadow-xs transition-colors whitespace-nowrap text-xs">
                        <span>{{ $promo->cta_text }}</span>
                        <span>&rarr;</span>
                    </a>

                    <button type="button" @click="open = false" aria-label="Close Announcement" class="text-amber-200 hover:text-white p-1 rounded-lg hover:bg-amber-700/50 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        </aside>

    @elseif($promo->display_type === 'floating_modal')
        <!-- Floating Attention Modal -->
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs" @click.self="open = false">
            <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full border border-slate-200 shadow-2xl space-y-5 text-center relative animate-fade-in" role="dialog" aria-modal="true" aria-labelledby="promo-modal-title">
                <button type="button" @click="open = false" aria-label="Close dialog" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 p-2 rounded-full hover:bg-slate-100">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>

                @if($promo->banner_image_path)
                    <img src="{{ asset('storage/' . $promo->banner_image_path) }}" alt="" class="w-full h-44 rounded-2xl object-cover border border-slate-100">
                @endif

                <div class="space-y-2">
                    <div class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-200">
                        Special Promotion
                    </div>
                    <h2 id="promo-modal-title" class="text-xl font-bold font-serif text-slate-900 tracking-tight">{{ $promo->headline }}</h2>
                    @if($promo->subheadline)
                        <p class="text-xs text-slate-500 leading-relaxed max-w-md mx-auto">{{ $promo->subheadline }}</p>
                    @endif
                </div>

                <template x-if="hasCountdown">
                    <div class="p-3 bg-amber-50 rounded-2xl border border-amber-200 inline-flex items-center gap-3">
                        <span class="text-xs font-semibold text-amber-800">Ending in:</span>
                        <div class="flex items-center gap-1 font-mono font-bold text-amber-900 text-sm">
                            <span x-text="days">0</span>d :
                            <span x-text="hours">0</span>h :
                            <span x-text="minutes">0</span>m :
                            <span x-text="seconds">0</span>s
                        </div>
                    </div>
                </template>

                <div class="pt-2">
                    <a href="{{ $ctaHref }}" class="inline-flex items-center justify-center w-full py-3 px-6 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold shadow-md transition-colors">
                        <span>{{ $promo->cta_text }}</span>
                    </a>
                </div>
            </div>
        </div>

    @else
        <!-- Inline Announcement Card -->
        <div class="bg-gradient-to-br from-amber-50 via-white to-amber-100/40 rounded-3xl p-6 sm:p-8 border border-amber-200 shadow-xs relative overflow-hidden">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-6">
                @if($promo->banner_image_path)
                    <img src="{{ asset('storage/' . $promo->banner_image_path) }}" alt="" class="w-24 h-24 rounded-2xl object-cover border border-amber-200 shrink-0">
                @endif
                <div class="space-y-1.5 text-center sm:text-left flex-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-200/80 text-amber-900">Featured Offer</span>
                    <h3 class="text-lg font-bold font-serif text-slate-900">{{ $promo->headline }}</h3>
                    @if($promo->subheadline)
                        <p class="text-xs text-slate-600 leading-relaxed">{{ $promo->subheadline }}</p>
                    @endif

                    <template x-if="hasCountdown">
                        <div class="pt-1 flex items-center justify-center sm:justify-start gap-2 font-mono text-xs text-amber-800 font-bold">
                            <span>Time Left:</span>
                            <span x-text="days">0</span>d
                            <span x-text="hours">0</span>h
                            <span x-text="minutes">0</span>m
                            <span x-text="seconds">0</span>s
                        </div>
                    </template>
                </div>
                <div class="shrink-0">
                    <a href="{{ $ctaHref }}" class="inline-flex items-center gap-2 px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                        <span>{{ $promo->cta_text }}</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>
        </div>
    @endif

</div>

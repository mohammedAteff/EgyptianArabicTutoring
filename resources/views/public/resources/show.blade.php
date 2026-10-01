@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <!-- Breadcrumb -->
    <nav class="flex items-center gap-2 text-xs font-medium text-stone-500 mb-8">
        <a href="{{ localized_url('home') }}" class="hover:text-stone-800">{{ __('Home') }}</a>
        <span>/</span>
        <a href="{{ localized_url('resources') }}" class="hover:text-stone-800">{{ __('Resources') }}</a>
        <span>/</span>
        <span class="text-stone-800 font-bold truncate">{{ $translation->title ?? $resource->title }}</span>
    </nav>

    @if(session('success'))
        <div class="mb-8 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-2xl text-sm text-emerald-800 font-medium shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($isFallback ?? false)
        <div class="mb-8 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-2xl text-sm text-amber-900 font-medium shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('content_fallback_banner') }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
        <!-- Main Description (7 cols) -->
        <div @if($isFallback ?? false) lang="en" dir="ltr" @endif data-section-id="resource-preview" class="lg:col-span-7 space-y-6">
            <div class="flex items-center gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3 py-1 rounded-full">
                    {{ $resource->category?->liveTranslation()?->name ?? $resource->category?->name ?? 'Free Resource' }}
                </span>
                <span class="text-xs font-semibold text-stone-500 bg-stone-100 px-2.5 py-0.5 rounded-full">
                    {{ strtoupper($resource->file_type ?? 'PDF') }}
                </span>
            </div>

            @if($resource->cover_image_path)
                <div class="rounded-3xl overflow-hidden shadow-sm border border-stone-200/80 mb-6">
                    <img src="{{ str_starts_with($resource->cover_image_path, 'http') ? $resource->cover_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($resource->cover_image_path) }}"
                         alt="{{ $translation->title ?? $resource->title }}" class="w-full max-h-96 object-cover">
                </div>
            @endif

            <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-900 tracking-tight leading-snug">
                {{ $translation->title ?? $resource->title }}
            </h1>

            <div class="prose prose-stone text-stone-600 leading-relaxed text-base space-y-4">
                <p class="font-medium text-stone-800 text-lg leading-relaxed">
                    {{ $translation->short_description ?? $resource->short_description }}
                </p>
                <div class="pt-2">
                    {!! nl2br(e($translation->full_description ?? ($resource->full_description ?? ($translation->short_description ?? $resource->short_description)))) !!}
                </div>
            </div>

            <!-- What's inside box -->
            <div class="p-6 rounded-2xl bg-stone-50 border border-stone-200/80 space-y-3">
                <h3 class="font-bold text-stone-900 text-sm">Included in this Guide:</h3>
                <ul class="space-y-2 text-xs sm:text-sm text-stone-600">
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Authentic Egyptian colloquial Arabic (Amiya) phonetic spelling & Arabic script</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Real-world conversational contexts & cultural nuances</span>
                    </li>
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                        <span>Printable and mobile-friendly reference sheet</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Download Gate Card (5 cols) -->
        <div class="lg:col-span-5" x-data="{
            state: '{{ (! $resource->is_gated || session('access_granted')) ? 'unlocked' : 'idle' }}',
            name: @js(old('name', '')),
            email: @js(old('email', '')),
            pin: '',
            challenge: '',
            downloadUrl: '{{ session('download_token') ? route(app()->getLocale() === 'fr' ? 'resources.download.fr' : (app()->getLocale() === 'de' ? 'resources.download.de' : 'resources.download'), array_filter(['slug' => $resource->slug, 'token' => session('download_token')])) : '' }}',
            errorMessage: '',
            attempts: 0,
            requestUrl: '{{ route(app()->getLocale() === 'fr' ? 'resources.request.fr' : (app()->getLocale() === 'de' ? 'resources.request.de' : 'resources.request'), $resource->slug) }}',
            verifyUrl: '{{ route(app()->getLocale() === 'fr' ? 'resources.verify-pin.fr' : (app()->getLocale() === 'de' ? 'resources.verify-pin.de' : 'resources.verify-pin'), $resource->slug) }}',
            submitEmail() {
                if (!this.email) return;
                this.state = 'submitting';
                this.errorMessage = '';
                fetch(this.requestUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        name: this.name,
                        email: this.email
                    })
                })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        if (res.status === 429) {
                            this.state = 'rate_limited';
                            this.errorMessage = 'Too many requests. Please try again later.';
                            return;
                        }
                        this.state = 'idle';
                        this.errorMessage = data.message || 'Unable to request access. Please check your email.';
                        return;
                    }
                    if (data.requires_pin) {
                        this.challenge = data.challenge;
                        this.attempts = 0;
                        this.state = 'pin_required';
                    } else if (data.download_url) {
                        this.downloadUrl = data.download_url;
                        this.state = 'unlocked';
                        window.location.href = data.download_url;
                    }
                })
                .catch(() => {
                    this.state = 'idle';
                    this.errorMessage = 'Network error. Please try again.';
                });
            },
            submitPin() {
                if (this.pin.length !== 6) return;
                this.state = 'verifying';
                this.errorMessage = '';
                fetch(this.verifyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        challenge: this.challenge,
                        pin: this.pin
                    })
                })
                .then(async res => {
                    const data = await res.json().catch(() => ({}));
                    if (res.status === 200 && data.download_url) {
                        this.downloadUrl = data.download_url;
                        this.state = 'unlocked';
                        window.location.href = data.download_url;
                    } else if (res.status === 422) {
                        this.attempts++;
                        this.state = 'pin_required';
                        this.errorMessage = 'Invalid verification code. (Attempt ' + this.attempts + ' of 5)';
                    } else if (res.status === 429) {
                        this.state = 'rate_limited';
                        this.errorMessage = 'Too many failed verification attempts. Please request a new code.';
                    } else if (res.status === 403) {
                        this.state = 'pin_required';
                        this.errorMessage = 'Session authorization mismatch. Please refresh and try again.';
                    } else if (res.status === 409) {
                        this.state = 'pin_required';
                        this.errorMessage = 'This verification code has already been used. Please request a new code.';
                    } else {
                        this.state = 'pin_required';
                        this.errorMessage = data.message || 'Verification failed. Please retry.';
                    }
                })
                .catch(() => {
                    this.state = 'pin_required';
                    this.errorMessage = 'Network error. Please check your connection and retry.';
                });
            }
        }"
        @if(!($isPreview ?? false) && !auth()->guard('web')->check())
            data-analytics-event="resource_gate_viewed"
            data-analytics-metadata="{{ json_encode(['resource_slug' => $resource->slug], JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_HEX_TAG) }}"
        @endif
        >
            <div class="bg-white rounded-3xl border border-stone-200/90 p-6 sm:p-8 shadow-xl sticky top-28">
                <!-- Unlocked / Ready State -->
                <div x-show="state === 'unlocked'" class="text-center space-y-4 py-4" style="{{ (! $resource->is_gated || session('access_granted')) ? '' : 'display: none;' }}">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-stone-900">Your File Is Ready!</h2>
                    <p class="text-xs text-stone-500 leading-relaxed">
                        Click below to save the file directly to your device.
                    </p>
                    <div class="pt-2">
                        <a :href="downloadUrl || '{{ route(app()->getLocale() === 'fr' ? 'resources.download.fr' : (app()->getLocale() === 'de' ? 'resources.download.de' : 'resources.download'), array_filter(['slug' => $resource->slug, 'token' => session('download_token')])) }}'"
                            class="w-full inline-flex items-center justify-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-base px-6 py-4 rounded-full shadow-md hover:shadow-lg transition-all">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            <span>{{ __('Download Resource') }}</span>
                        </a>
                    </div>
                </div>

                <!-- Idle / Submitting State (Email Input) -->
                <div x-show="state === 'idle' || state === 'submitting'" style="{{ (! $resource->is_gated || session('access_granted')) ? 'display: none;' : '' }}">
                    <div class="flex items-center gap-2 text-xs font-bold text-emerald-600 uppercase tracking-wider mb-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span>Free Instant Access</span>
                    </div>
                    <h2 class="text-xl font-bold text-stone-900">Get This Resource</h2>
                    <p class="text-stone-500 text-xs mt-1 mb-6 leading-relaxed">
                        Instant access provided right on this screen. No waiting for email links or promotional spam.
                    </p>

                    <div x-show="errorMessage" class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-600 font-medium" x-text="errorMessage"></div>

                    <form @submit.prevent="submitEmail" method="POST" action="{{ route(app()->getLocale() === 'fr' ? 'resources.request.fr' : (app()->getLocale() === 'de' ? 'resources.request.de' : 'resources.request'), $resource->slug) }}" class="space-y-4">
                        @csrf
                        <div>
                            <label for="name" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-1">
                                Your Name <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   x-model="name"
                                   name="name"
                                   id="name"
                                   required
                                   placeholder="e.g. David"
                                   class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-sm focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-100 outline-none">
                        </div>

                        <div>
                            <label for="email" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-1">
                                Email Address <span class="text-rose-500">*</span>
                            </label>
                            <input type="email"
                                   x-model="email"
                                   name="email"
                                   id="email"
                                   required
                                   placeholder="david@example.com"
                                   class="w-full px-4 py-2.5 rounded-xl border border-stone-200 text-sm focus:border-terracotta-500 focus:ring-2 focus:ring-terracotta-100 outline-none">
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                    :disabled="state === 'submitting'"
                                    class="w-full inline-flex items-center justify-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 disabled:opacity-50 text-white font-bold text-sm px-6 py-3.5 rounded-full shadow-md hover:shadow transition-all">
                                <span x-show="state !== 'submitting'">{{ __('Request Free Access') }}</span>
                                <span x-show="state === 'submitting'">Requesting Access...</span>
                                <svg x-show="state !== 'submitting'" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                                </svg>
                            </button>
                        </div>

                        <p class="text-[11px] text-stone-400 text-center leading-tight">
                            We respect your privacy. Resource access does not trigger unsolicited marketing emails.
                        </p>
                    </form>
                </div>

                <!-- PIN Required / Verifying State -->
                <div x-show="state === 'pin_required' || state === 'verifying'" style="display: none;">
                    <div class="flex items-center gap-2 text-xs font-bold text-amber-600 uppercase tracking-wider mb-2">
                        <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>Verification Required</span>
                    </div>
                    <h2 class="text-xl font-bold text-stone-900">Check Your Email</h2>
                    <p class="text-stone-500 text-xs mt-1 mb-6 leading-relaxed">
                        We sent a 6-digit verification code to <strong class="text-stone-800" x-text="email"></strong>. Please enter it below to unlock your download.
                    </p>

                    <div x-show="errorMessage" class="mb-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-600 font-medium" x-text="errorMessage"></div>

                    <form @submit.prevent="submitPin" class="space-y-4">
                        <div>
                            <label for="pin" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-1">
                                6-Digit Verification Code <span class="text-rose-500">*</span>
                            </label>
                            <input type="text"
                                   x-model="pin"
                                   id="pin"
                                   required
                                   maxlength="6"
                                   pattern="[0-9]{6}"
                                   placeholder="123456"
                                   autocomplete="one-time-code"
                                   class="w-full text-center tracking-[0.3em] font-mono font-bold text-lg px-4 py-3 rounded-xl border border-stone-200 focus:border-amber-500 focus:ring-2 focus:ring-amber-100 outline-none">
                        </div>

                        <div class="pt-2">
                            <button type="submit"
                                    :disabled="state === 'verifying' || pin.length !== 6"
                                    class="w-full inline-flex items-center justify-center gap-2 bg-amber-600 hover:bg-amber-700 disabled:opacity-50 text-white font-bold text-sm px-6 py-3.5 rounded-full shadow-md hover:shadow transition-all">
                                <span x-show="state !== 'verifying'">Verify & Download</span>
                                <span x-show="state === 'verifying'">Verifying Code...</span>
                            </button>
                        </div>

                        <div class="text-center pt-2">
                            <button type="button" @click="state = 'idle'; pin = ''; errorMessage = '';" class="text-xs text-stone-500 hover:text-stone-800 underline">
                                Entered wrong email? Try again
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Rate Limited State -->
                <div x-show="state === 'rate_limited'" style="display: none;" class="text-center space-y-4 py-4">
                    <div class="w-16 h-16 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center mx-auto">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <h2 class="text-xl font-bold text-stone-900">Access Restricted</h2>
                    <p class="text-xs text-stone-600 leading-relaxed" x-text="errorMessage || 'Too many attempts. Please try again later.'"></p>
                    <div class="pt-2">
                        <button type="button" @click="state = 'idle'; pin = ''; errorMessage = ''; attempts = 0;" class="text-xs font-semibold text-terracotta-600 hover:text-terracotta-700 underline">
                            Return to Form
                        </button>
                    </div>
                </div>

                <!-- Cross-promote Lesson -->
                <div class="mt-8 pt-6 border-t border-stone-100 text-center">
                    <div class="text-xs font-bold text-stone-900 mb-1">Want to practice speaking?</div>
                    <p class="text-xs text-stone-500 mb-3">Practice these exact phrases in a private 1-on-1 session.</p>
                    <a href="{{ localized_url('booking') }}" class="text-xs font-bold text-terracotta-600 hover:text-terracotta-700 underline">
                        Book a Lesson with Abdallah →
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

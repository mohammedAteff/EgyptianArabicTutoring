@extends('layouts.public')

@section('content')
<!-- Hero Section -->
<section class="relative overflow-hidden pt-12 pb-20 lg:pt-20 lg:pb-28">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            <!-- Left Hero Content (7 cols) -->
            <div class="lg:col-span-7 space-y-6 text-center lg:text-left">
                <div class="inline-flex items-center gap-2 bg-stone-100/90 border border-stone-200/80 px-3.5 py-1.5 rounded-full text-xs font-semibold text-stone-700">
                    <span class="w-2 h-2 rounded-full bg-terracotta-500"></span>
                    <span>1-on-1 Native Egyptian Arabic Tutoring</span>
                    <span class="text-stone-300">•</span>
                    <span class="font-cairo text-stone-600">اتكلم مصري</span>
                </div>

                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-stone-900 tracking-tight leading-[1.15]">
                    {{ $heroTitle }}
                </h1>

                <p class="text-stone-600 text-lg sm:text-xl max-w-2xl mx-auto lg:mx-0 leading-relaxed">
                    {{ $heroSubtitle }}
                </p>

                <!-- Action CTAs -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                    <a href="{{ route('booking.index') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2.5 bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-base px-8 py-4 rounded-full shadow-md hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Book a Private Lesson</span>
                    </a>

                    <a href="{{ route('resources.index') }}"
                       class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-white hover:bg-stone-50 border border-stone-300 text-stone-800 font-semibold text-base px-6 py-4 rounded-full shadow-sm hover:shadow transition-all">
                        <svg class="w-5 h-5 text-stone-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                        </svg>
                        <span>Free Workbooks</span>
                    </a>
                </div>

                <!-- Quick trust indicators -->
                <div class="pt-6 flex flex-wrap items-center justify-center lg:justify-start gap-6 text-xs text-stone-500 font-medium">
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Native Egyptian Speaker (Cairo)</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Instant Timezone Booking</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Tailored Lesson Materials</span>
                    </div>
                </div>
            </div>

            <!-- Right Hero Card: Language Interactive Card (5 cols) -->
            <div class="lg:col-span-5">
                <div class="bg-white rounded-3xl border border-stone-200/90 p-6 sm:p-8 shadow-xl relative overflow-hidden">
                    <div class="absolute -right-8 -top-8 w-32 h-32 bg-terracotta-50 rounded-full blur-2xl -z-0"></div>

                    <div class="flex items-center justify-between border-b border-stone-100 pb-4 mb-5">
                        <div>
                            <span class="text-xs font-bold uppercase tracking-wider text-terracotta-600">Daily Egyptian Arabic</span>
                            <h3 class="font-extrabold text-stone-900 text-lg">Everyday Expressions</h3>
                        </div>
                        <span class="bg-nile-50 text-nile-700 text-xs font-bold px-2.5 py-1 rounded-full">Cairo Dialect</span>
                    </div>

                    <div class="space-y-3.5">
                        <!-- Phrase 1 -->
                        <div class="p-4 rounded-2xl bg-stone-50 border border-stone-100 transition-all hover:bg-terracotta-50/40 hover:border-terracotta-200">
                            <div class="flex items-baseline justify-between">
                                <span class="font-cairo font-bold text-xl text-stone-900" dir="rtl">إزيك؟ عامل إيه؟</span>
                                <span class="text-xs font-mono text-stone-400">Greeting</span>
                            </div>
                            <div class="text-sm font-semibold text-terracotta-600 mt-1">Ezzayak? Aamel eih?</div>
                            <div class="text-xs text-stone-600 mt-0.5">How are you? How's everything going?</div>
                        </div>

                        <!-- Phrase 2 -->
                        <div class="p-4 rounded-2xl bg-stone-50 border border-stone-100 transition-all hover:bg-terracotta-50/40 hover:border-terracotta-200">
                            <div class="flex items-baseline justify-between">
                                <span class="font-cairo font-bold text-xl text-stone-900" dir="rtl">صباح الفل والياسمين</span>
                                <span class="text-xs font-mono text-stone-400">Warm Morning</span>
                            </div>
                            <div class="text-sm font-semibold text-terracotta-600 mt-1">Sabah el fol wel yasmeen</div>
                            <div class="text-xs text-stone-600 mt-0.5">A morning full of jasmine (super friendly Cairo greeting)</div>
                        </div>

                        <!-- Phrase 3 -->
                        <div class="p-4 rounded-2xl bg-stone-50 border border-stone-100 transition-all hover:bg-terracotta-50/40 hover:border-terracotta-200">
                            <div class="flex items-baseline justify-between">
                                <span class="font-cairo font-bold text-xl text-stone-900" dir="rtl">على راسي من فوق</span>
                                <span class="text-xs font-mono text-stone-400">Courtesy</span>
                            </div>
                            <div class="text-sm font-semibold text-terracotta-600 mt-1">Ala rasi men fooq</div>
                            <div class="text-xs text-stone-600 mt-0.5">With utmost pleasure / It's my honor (literally: on top of my head)</div>
                        </div>
                    </div>

                    <!-- Card CTA -->
                    <div class="mt-6 pt-4 border-t border-stone-100 flex items-center justify-between text-xs">
                        <span class="text-stone-500">Ready to speak with natural fluency?</span>
                        <a href="{{ route('booking.index') }}" class="font-bold text-terracotta-600 hover:text-terracotta-700">
                            Start Learning →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Teaching Approach / Value Pillars -->
<section class="py-20 bg-white border-y border-stone-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto mb-16">
            <span class="text-xs font-extrabold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3.5 py-1.5 rounded-full">
                {{ $homeApproachBadge ?? 'The Practical Method' }}
            </span>
            <h2 class="text-3xl sm:text-4xl font-extrabold text-stone-900 mt-4 tracking-tight">
                {{ $homeApproachTitle ?? 'Why Learn Egyptian Arabic 1-on-1?' }}
            </h2>
            <p class="text-stone-600 text-base mt-3 leading-relaxed">
                {{ $homeApproachIntro ?? "Most Arabic courses teach Modern Standard Arabic (MSA), which native speakers don't speak at home or on the streets. We teach you authentic spoken Egyptian Arabic as it is used today." }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Pillar 1 -->
            <div class="p-8 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-terracotta-500 text-white flex items-center justify-center font-bold text-xl shadow-sm">
                    🗣
                </div>
                <h3 class="text-xl font-bold text-stone-900">Conversational From Day 1</h3>
                <p class="text-stone-600 text-sm leading-relaxed">
                    No dry grammar drills. You will start speaking real phrases from your very first session, learning how Egyptians actually communicate in cafes, markets, and gatherings.
                </p>
            </div>

            <!-- Pillar 2 -->
            <div class="p-8 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-nile-600 text-white flex items-center justify-center font-bold text-xl shadow-sm">
                    🎯
                </div>
                <h3 class="text-xl font-bold text-stone-900">Personalized Pacing</h3>
                <p class="text-stone-600 text-sm leading-relaxed">
                    Every lesson is customized to your exact objectives—whether you're traveling to Egypt, communicating with family or in-laws, conducting business, or exploring cultural heritage.
                </p>
            </div>

            <!-- Pillar 3 -->
            <div class="p-8 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 space-y-4">
                <div class="w-12 h-12 rounded-2xl bg-amber-500 text-white flex items-center justify-center font-bold text-xl shadow-sm">
                    📖
                </div>
                <h3 class="text-xl font-bold text-stone-900">Dedicated Study Materials</h3>
                <p class="text-stone-600 text-sm leading-relaxed">
                    Receive clean PDF summaries, audio pronunciation notes, and custom vocabulary lists after every session. Plus, access our growing free library of workbooks and games.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Featured Session Type Booking Preview -->
@if($sessionType)
<section class="py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-gradient-to-br from-nile-900 to-nile-800 rounded-3xl p-8 sm:p-12 text-white shadow-xl relative overflow-hidden">
            <div class="max-w-2xl space-y-6 relative z-10">
                <span class="inline-flex items-center gap-1.5 bg-white/10 text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
                    Private Lesson Format
                </span>
                <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
                    {{ $sessionType->title }}
                </h2>
                <p class="text-stone-300 text-base leading-relaxed">
                    {{ $sessionType->description ?? 'A focused, 1-on-1 private lesson via Zoom or Google Meet. Tailored to your speaking level with interactive exercises, cultural notes, and real-time corrections.' }}
                </p>

                <div class="flex flex-wrap items-center gap-6 text-sm font-semibold">
                    <div class="flex items-center gap-2">
                        <span class="text-terracotta-400">⏱</span>
                        <span>{{ $sessionType->duration_minutes }} Minutes</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-terracotta-400">💵</span>
                        <span class="text-xl font-extrabold">${{ number_format($sessionType->price, 2) }} {{ $sessionType->currency }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-terracotta-400">📅</span>
                        <span>Instant Calendar Confirmation</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="{{ route('booking.index') }}"
                       class="inline-flex items-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-base px-8 py-4 rounded-full shadow-lg transition-all transform hover:-translate-y-0.5">
                        <span>Check Tutor Availability & Book</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
@endif

<!-- Featured Resources Section -->
@if($featuredResources->isNotEmpty())
<section class="py-20 bg-white border-y border-stone-200/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-12 gap-4">
            <div>
                <span class="text-xs font-extrabold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3.5 py-1.5 rounded-full">
                    {{ $homeResourcesBadge ?? 'Free Workbooks & Cheatsheets' }}
                </span>
                <h2 class="text-3xl font-extrabold text-stone-900 mt-3 tracking-tight">
                    {{ $homeResourcesTitle ?? 'Grow Your Vocabulary' }}
                </h2>
                <p class="text-stone-600 text-sm mt-1">{{ $homeResourcesSubtitle ?? 'Download free structured guides prepared specifically for Egyptian Arabic learners.' }}</p>
            </div>
            <a href="{{ route('resources.index') }}" class="font-bold text-sm text-terracotta-600 hover:text-terracotta-700 flex items-center gap-1.5">
                <span>View Full Library</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($featuredResources as $resource)
                <div class="bg-[#FAF8F5] rounded-3xl border border-stone-200/80 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-bold text-terracotta-600 bg-terracotta-50 px-3 py-1 rounded-full">
                                {{ $resource->category?->name ?? 'Workbook' }}
                            </span>
                            <span class="text-xs text-stone-400 font-medium">
                                {{ strtoupper($resource->file_type ?? 'PDF') }}
                            </span>
                        </div>
                        <h3 class="text-lg font-bold text-stone-900 leading-snug">
                            {{ $resource->title }}
                        </h3>
                        <p class="text-stone-600 text-sm mt-2 line-clamp-3 leading-relaxed">
                            {{ $resource->short_description }}
                        </p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-stone-200/70 flex items-center justify-between">
                        <span class="text-xs font-medium text-emerald-600 flex items-center gap-1">
                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                            Free Download
                        </span>
                        <a href="{{ route('resources.show', $resource->slug) }}"
                           class="text-xs font-bold text-terracotta-600 hover:text-terracotta-700 flex items-center gap-1">
                            <span>Get Resource</span>
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Interactive Games Preview -->
@if($featuredGames->isNotEmpty())
<section class="py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-12 gap-4">
            <div>
                <span class="text-xs font-extrabold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3.5 py-1.5 rounded-full">
                    {{ $homeGamesBadge ?? 'Interactive Practice' }}
                </span>
                <h2 class="text-3xl font-extrabold text-stone-900 mt-3 tracking-tight">
                    {{ $homeGamesTitle ?? 'Play Egyptian Arabic Games' }}
                </h2>
                <p class="text-stone-600 text-sm mt-1">{{ $homeGamesSubtitle ?? 'Reinforce your memory with engaging numbers, food, and street phrase exercises.' }}</p>
            </div>
            <a href="{{ route('games.index') }}" class="font-bold text-sm text-terracotta-600 hover:text-terracotta-700 flex items-center gap-1.5">
                <span>View All Games</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($featuredGames as $game)
                <div class="bg-white rounded-3xl border border-stone-200/80 p-6 flex flex-col justify-between hover:shadow-md transition-shadow">
                    <div>
                        <div class="w-12 h-12 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center font-bold text-xl mb-4">
                            🎮
                        </div>
                        <h3 class="text-lg font-bold text-stone-900">{{ $game->title }}</h3>
                        <p class="text-stone-600 text-sm mt-2 leading-relaxed">{{ $game->description }}</p>
                    </div>

                    <div class="mt-6 pt-4 border-t border-stone-100 flex items-center justify-between">
                        @if($game->status === 'available')
                            <span class="text-xs font-semibold text-emerald-600 bg-emerald-50 px-2.5 py-1 rounded-full">Available to Play</span>
                            <a href="{{ route('games.show', $game->slug) }}" class="text-xs font-bold text-terracotta-600 hover:text-terracotta-700">Play Now →</a>
                        @else
                            <span class="text-xs font-semibold text-stone-500 bg-stone-100 px-2.5 py-1 rounded-full">Coming Soon</span>
                            <span class="text-xs text-stone-400">In Development</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- FAQs Section -->
@if($faqs->isNotEmpty())
<section class="py-20 bg-white border-t border-stone-200/80">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-12">
            <span class="text-xs font-extrabold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3.5 py-1.5 rounded-full">
                Clear Answers
            </span>
            <h2 class="text-3xl font-extrabold text-stone-900 mt-3 tracking-tight">
                Frequently Asked Questions
            </h2>
        </div>

        <div class="space-y-4" x-data="{ openFaq: null }">
            @foreach($faqs as $index => $faq)
                <div class="border border-stone-200/80 rounded-2xl overflow-hidden bg-[#FAF8F5]">
                    <button type="button"
                            @click="openFaq = (openFaq === {{ $index }} ? null : {{ $index }})"
                            class="w-full text-left p-5 flex items-center justify-between font-bold text-base text-stone-900 hover:text-terracotta-600 transition-colors">
                        <span>{{ $faq->question }}</span>
                        <svg class="w-5 h-5 text-stone-400 transition-transform duration-200 shrink-0 ml-4"
                             :class="{ 'rotate-180 text-terracotta-600': openFaq === {{ $index }} }"
                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div x-show="openFaq === {{ $index }}"
                         x-cloak
                         x-collapse
                         class="px-5 pb-5 text-sm text-stone-600 leading-relaxed border-t border-stone-200/50 pt-3">
                        {{ $faq->answer }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<!-- Final Call to Action -->
<section class="py-20 bg-stone-900 text-white text-center">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="w-14 h-14 rounded-2xl bg-terracotta-500 text-white flex items-center justify-center font-bold text-2xl font-cairo mx-auto">
            م
        </div>
        <h2 class="text-3xl sm:text-4xl font-extrabold tracking-tight">
            {{ $homeCtaTitle ?? 'Ready to Speak Authentic Egyptian Arabic?' }}
        </h2>
        <p class="text-stone-300 text-base max-w-xl mx-auto leading-relaxed">
            {{ $homeCtaSubtitle ?? 'Reserve your first private lesson in minutes. Choose your local timezone and get instant confirmation with your calendar invitation.' }}
        </p>
        <div class="pt-4">
            <a href="{{ route('booking.index') }}"
               class="inline-flex items-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-base px-9 py-4 rounded-full shadow-lg hover:shadow-xl transition-all transform hover:-translate-y-0.5">
                <span>{{ $homeCtaButton ?? 'Book Your Session Now' }}</span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/>
                </svg>
            </a>
        </div>
    </div>
</section>
@endsection

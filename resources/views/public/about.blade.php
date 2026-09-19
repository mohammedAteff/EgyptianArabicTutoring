@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    @if($isFallback ?? false)
        <div class="mb-8 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-2xl text-sm text-amber-900 font-medium shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('content_fallback_banner') }}</span>
        </div>
    @endif

    <!-- Header -->
    <div class="text-center max-w-2xl mx-auto mb-16 space-y-4">
        <span class="inline-flex items-center gap-1.5 bg-terracotta-50 text-terracotta-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
            {{ __('Native Arabic Educator') }}
        </span>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-stone-900 tracking-tight">
            {{ $translation->title ?? ($page ? $page->title : __('Meet Your Tutor, Ahmad')) }}
        </h1>
        <p class="text-stone-600 text-lg leading-relaxed">
            Born and raised in Cairo, teaching real, living Egyptian Arabic to students around the globe.
        </p>
    </div>

    <!-- Story & Bio Section -->
    <div @if($isFallback ?? false) lang="en" dir="ltr" @endif class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 shadow-sm mb-12 space-y-8">
        @if(!empty($imagePath))
            <div class="mb-6 flex justify-center">
                <img src="{{ str_starts_with($imagePath, 'http') ? $imagePath : \Illuminate\Support\Facades\Storage::disk('public')->url($imagePath) }}"
                     alt="{{ $title }}" class="rounded-3xl shadow-md max-h-80 object-cover">
            </div>
        @endif

        <div class="prose prose-stone max-w-none text-stone-700 leading-relaxed space-y-6 text-base sm:text-lg">
            @if(!empty($biography))
                {!! nl2br(e($biography)) !!}
            @else
                <p>
                    <strong>Ahlan wa sahlan!</strong> Welcome. My name is Ahmad, and I am an independent Egyptian Arabic language educator based in Cairo, Egypt. Over the past several years, I have helped hundreds of international students—from complete beginners to advanced diplomats and researchers—master the language of Egypt.
                </p>
                <p>
                    Arabic has two distinct worlds: <em>Modern Standard Arabic (Fusha)</em>, which is written in newspapers and formal legal texts, and <em>Egyptian Colloquial Arabic (Amiya)</em>, which is spoken by over 105 million Egyptians in daily life, television, movies, and music across the entire Arab world.
                </p>
                <p>
                    If your goal is to hold genuine conversations, navigate Cairo's streets, laugh at Egyptian humor, and connect deeply with Arabic speakers, you need <strong>Egyptian Arabic</strong>.
                </p>
            @endif
        </div>

        <!-- Tutor Credentials / Stats -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 pt-6 border-t border-stone-100 text-center">
            <div>
                <div class="text-3xl font-extrabold text-terracotta-600 font-mono">100%</div>
                <div class="text-xs text-stone-500 font-medium mt-1">Native Egyptian</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-terracotta-600 font-mono">1,500+</div>
                <div class="text-xs text-stone-500 font-medium mt-1">Lessons Delivered</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-terracotta-600 font-mono">35+</div>
                <div class="text-xs text-stone-500 font-medium mt-1">Countries Represented</div>
            </div>
            <div>
                <div class="text-3xl font-extrabold text-terracotta-600 font-mono">1-on-1</div>
                <div class="text-xs text-stone-500 font-medium mt-1">Private Sessions Only</div>
            </div>
        </div>
    </div>

    <!-- The 4 Pillars of the Method -->
    <div class="mb-16 space-y-6">
        <h2 class="text-2xl sm:text-3xl font-bold text-stone-900 text-center">{{ __('The Teaching Philosophy') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-2xl border border-stone-200/80 p-6 space-y-3">
                <div class="w-10 h-10 rounded-xl bg-terracotta-50 text-terracotta-600 flex items-center justify-center font-bold text-lg">
                    1
                </div>
                <h3 class="font-bold text-stone-900 text-lg">Speaking Priority</h3>
                <p class="text-stone-600 text-sm leading-relaxed">
                    You spend at least 70% of each session actively speaking. Real language acquisition happens when you produce sentences, not when you passively listen to lectures.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200/80 p-6 space-y-3">
                <div class="w-10 h-10 rounded-xl bg-nile-50 text-nile-700 flex items-center justify-center font-bold text-lg">
                    2
                </div>
                <h3 class="font-bold text-stone-900 text-lg">Authentic Street Arabic</h3>
                <p class="text-stone-600 text-sm leading-relaxed">
                    We learn phrases as they are used right now in Egyptian streets, cafes, and homes. Learn authentic slang, polite idioms, and cultural subtext that textbooks miss.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200/80 p-6 space-y-3">
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-lg">
                    3
                </div>
                <h3 class="font-bold text-stone-900 text-lg">Zero Fear of Mistakes</h3>
                <p class="text-stone-600 text-sm leading-relaxed">
                    Language learning is vulnerable. Our sessions provide an encouraging, low-pressure environment where every mistake is treated as valuable learning data.
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-stone-200/80 p-6 space-y-3">
                <div class="w-10 h-10 rounded-xl bg-stone-100 text-stone-700 flex items-center justify-center font-bold text-lg">
                    4
                </div>
                <h3 class="font-bold text-stone-900 text-lg">Audio & Text Follow-Up</h3>
                <p class="text-stone-600 text-sm leading-relaxed">
                    After every lesson, you receive tailored notes with Egyptian Arabic script, phonetics, and audio recordings for any tricky pronunciations practiced during the hour.
                </p>
            </div>
        </div>
    </div>

    <!-- CTA Section -->
    <div class="text-center bg-stone-900 text-white rounded-3xl p-8 sm:p-12 space-y-6">
        <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight">{{ __('Ready to Speak Authentic Egyptian Arabic?') }}</h2>
        <p class="text-stone-300 text-sm sm:text-base max-w-xl mx-auto leading-relaxed">
            Check the live tutor schedule, pick your local timezone, and book your first lesson today.
        </p>
        <div>
            <a href="{{ localized_url('booking') }}" class="inline-flex items-center gap-2 bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-sm px-8 py-3.5 rounded-full shadow-md transition-all">
                <span>{{ __('Book a Private Lesson') }}</span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
            </a>
        </div>
    </div>
</div>
@endsection

@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    @if($isPreview ?? false)
        <div class="mb-8 p-4 bg-amber-500 text-white rounded-2xl text-center text-xs font-bold shadow-xs">
            Administrator Preview Mode &bull; Showing all FAQs including hidden and drafts.
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

    <!-- Header -->
    <div class="text-center max-w-2xl mx-auto mb-16 space-y-4">
        <span class="inline-flex items-center gap-1.5 bg-terracotta-50 text-terracotta-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
            {{ __('Help & Information') }}
        </span>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-stone-900 tracking-tight">
            {{ __('Frequently Asked Questions') }}
        </h1>
        <p class="text-stone-600 text-base sm:text-lg leading-relaxed">
            {{ __('Everything you need to know about lesson booking, timezone conversion, study materials, and the Egyptian dialect.') }}
        </p>
    </div>

    <!-- FAQ Accordion List -->
    <div @if($isFallback ?? false) lang="en" dir="ltr" @endif class="space-y-4" x-data="{ openFaq: null }">
        @forelse($faqs as $index => $faq)
            @php
                $faqTranslation = app()->getLocale() !== 'en' ? $faq->liveTranslation() : null;
                $isItemFallback = app()->getLocale() !== 'en' && ! $faqTranslation;
                $itemQuestion = $faqTranslation?->question ?? $faq->question;
                $itemAnswer = $faqTranslation?->answer ?? $faq->answer;
            @endphp
            <div class="border border-stone-200/80 rounded-2xl overflow-hidden bg-white shadow-xs"
                 @if($isItemFallback) lang="en" dir="ltr" @endif>
                <button type="button"
                        @click="openFaq = (openFaq === {{ $index }} ? null : {{ $index }})"
                        class="w-full text-left p-6 flex items-center justify-between font-bold text-lg text-stone-900 hover:text-terracotta-600 transition-colors">
                    <span class="flex items-center gap-3">
                        <span>{{ $itemQuestion }}</span>
                        @if($isItemFallback && !($isFallback ?? false))
                            <span class="inline-flex items-center text-xs bg-amber-100 text-amber-800 px-2.5 py-0.5 rounded-full font-medium shrink-0">
                                {{ __('English Fallback') }}
                            </span>
                        @endif
                    </span>
                    <svg class="w-5 h-5 text-stone-400 transition-transform duration-200 shrink-0 ml-4"
                         :class="{ 'rotate-180 text-terracotta-600': openFaq === {{ $index }} }"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="openFaq === {{ $index }}"
                     x-cloak
                     x-collapse
                     class="px-6 pb-6 text-stone-600 text-base leading-relaxed border-t border-stone-100 pt-4">
                    {!! nl2br(e($itemAnswer)) !!}
                </div>
            </div>
        @empty
            <div class="text-center py-12 bg-white rounded-3xl border border-stone-200">
                <p class="text-stone-500">FAQ items are currently being loaded.</p>
            </div>
        @endforelse
    </div>

    <!-- Still have questions? -->
    <div class="mt-16 text-center bg-[#FAF8F5] rounded-3xl border border-stone-200/80 p-8 space-y-4">
        <h3 class="text-xl font-bold text-stone-900">{{ __('Still have a question?') }}</h3>
        <p class="text-stone-600 text-sm max-w-md mx-auto">
            {{ __('Connect directly via WhatsApp or Telegram for quick questions about lesson availability and learning goals.') }}
        </p>
        <div class="pt-2 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ localized_url('booking') }}" class="bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-sm px-6 py-3 rounded-full shadow-sm">
                {{ __('Book a Session Now') }}
            </a>
            <a href="{{ localized_url('about') }}" class="bg-white border border-stone-300 hover:bg-stone-50 text-stone-700 font-semibold text-sm px-6 py-3 rounded-full">
                {{ __('Learn More About Abdallah') }}
            </a>
        </div>
    </div>
</div>
@endsection

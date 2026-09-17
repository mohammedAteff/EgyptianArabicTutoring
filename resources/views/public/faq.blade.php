@extends('layouts.public')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <!-- Header -->
    <div class="text-center max-w-2xl mx-auto mb-16 space-y-4">
        <span class="inline-flex items-center gap-1.5 bg-terracotta-50 text-terracotta-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider">
            Help & Information
        </span>
        <h1 class="text-4xl sm:text-5xl font-extrabold text-stone-900 tracking-tight">
            Frequently Asked Questions
        </h1>
        <p class="text-stone-600 text-base sm:text-lg leading-relaxed">
            Everything you need to know about lesson booking, timezone conversion, study materials, and the Egyptian dialect.
        </p>
    </div>

    <!-- FAQ Accordion List -->
    <div class="space-y-4" x-data="{ openFaq: null }">
        @forelse($faqs as $index => $faq)
            <div class="border border-stone-200/80 rounded-2xl overflow-hidden bg-white shadow-xs">
                <button type="button"
                        @click="openFaq = (openFaq === {{ $index }} ? null : {{ $index }})"
                        class="w-full text-left p-6 flex items-center justify-between font-bold text-lg text-stone-900 hover:text-terracotta-600 transition-colors">
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
                     class="px-6 pb-6 text-stone-600 text-base leading-relaxed border-t border-stone-100 pt-4">
                    {!! nl2br(e($faq->answer)) !!}
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
        <h3 class="text-xl font-bold text-stone-900">Still have a question?</h3>
        <p class="text-stone-600 text-sm max-w-md mx-auto">
            Connect directly via WhatsApp or Telegram for quick questions about lesson availability and learning goals.
        </p>
        <div class="pt-2 flex flex-wrap items-center justify-center gap-4">
            <a href="{{ route('booking.index') }}" class="bg-terracotta-500 hover:bg-terracotta-600 text-white font-bold text-sm px-6 py-3 rounded-full shadow-sm">
                Book a Session Now
            </a>
            <a href="{{ route('about') }}" class="bg-white border border-stone-300 hover:bg-stone-50 text-stone-700 font-semibold text-sm px-6 py-3 rounded-full">
                Learn More About Ahmad
            </a>
        </div>
    </div>
</div>
@endsection

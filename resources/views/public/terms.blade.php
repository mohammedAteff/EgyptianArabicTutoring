@extends('layouts.public')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    @if($isFallback ?? false)
        <div class="mb-8 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-2xl text-sm text-amber-900 font-medium shadow-sm flex items-center gap-3">
            <svg class="w-5 h-5 text-amber-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span>{{ __('content_fallback_banner') }}</span>
        </div>
    @endif

    <div class="mb-12">
        <span class="text-xs font-bold uppercase tracking-wider text-terracotta-600 bg-terracotta-50 px-3 py-1 rounded-full">
            Policies & Agreement
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-900 mt-3 tracking-tight">
            {{ $title ?? 'Terms of Service & Booking Policy' }}
        </h1>
        <p class="text-stone-500 text-sm mt-1">Last updated: September 2026</p>
    </div>

    <div @if($isFallback ?? false) lang="en" dir="ltr" @endif class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 shadow-sm space-y-8 text-stone-700 leading-relaxed text-sm sm:text-base">
        @if(!empty($content))
            <div class="prose prose-stone max-w-none text-stone-700 leading-relaxed space-y-6 text-base">
                {!! nl2br(e($content)) !!}
            </div>
        @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-amber-900">
            <h2 class="text-lg font-bold mb-2">Authoritative terms unavailable</h2>
            <p>The published terms and booking policy record is not available yet. Please contact Abdallah directly for the current terms before booking.</p>
        </div>
        @endif
    </div>
</div>
@endsection

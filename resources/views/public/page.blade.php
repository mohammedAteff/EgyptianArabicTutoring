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

    <div @if($isFallback ?? false) lang="en" dir="ltr" @endif class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 shadow-sm space-y-6">
        <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-900 tracking-tight">
            {{ $translation->title ?? $page->title }}
        </h1>

        <div class="prose prose-stone max-w-none text-stone-700 leading-relaxed text-base sm:text-lg">
            {!! $translation->content ?? $page->content !!}
        </div>
    </div>
</div>
@endsection

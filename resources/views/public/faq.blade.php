
@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div class="mx-auto max-w-4xl space-y-10 px-4 py-12 sm:px-6 lg:px-8">
    <x-public-copy-section :section="config('public_content.faq_intro')" :level="1" />
    @if(!($isPreview ?? false))
        <form action="{{ localized_url('faq') }}" method="GET" class="grid gap-4 rounded-2xl border border-stone-200 bg-white p-5 sm:grid-cols-[1fr_1fr_auto]">
            <label class="text-sm font-semibold">Search questions<input type="search" name="q" value="{{ $search ?? '' }}" maxlength="200" placeholder="What would you like to know?" class="mt-2 block w-full rounded-lg border border-stone-300 p-3"></label>
            <label class="text-sm font-semibold">Browse by Topic<select name="topic" class="mt-2 block w-full rounded-lg border border-stone-300 p-3"><option value="">{{ website_text('faq', 'All Questions') }}</option>@foreach($topics ?? collect() as $item)<option value="{{ $item }}" @selected(($topic ?? '') === $item)>{{ $item }}</option>@endforeach</select></label>
            <button class="self-end rounded-lg bg-nile-900 px-6 py-3 font-bold text-white">{{ website_text('faq', 'Search') }}</button>
        </form>
    @endif
    <div class="space-y-7">
        @forelse($faqs->groupBy(fn ($faq) => $faq->topic ?: 'General') as $group => $questions)
            <section class="space-y-3">
                <h2 class="mb-4 text-xl font-bold">{{ $group }}</h2>
                @foreach($questions as $faq)
                    @php $translation = $faq->liveTranslation(); @endphp
                    <details class="rounded-xl border border-stone-200 bg-white p-5" lang="{{ $translation?->locale ?? 'en' }}">
                        <summary class="cursor-pointer text-lg font-bold">{{ $translation?->question ?? $faq->question }}@if(app()->getLocale() !== 'en' && !$translation)<span lang="{{ $copyLocale ?? 'en' }}" class="mt-2 block text-xs font-semibold text-amber-700">{{ website_text('faq', 'English Fallback') }}</span>@endif</summary>
                        <div class="mt-4 whitespace-pre-line leading-relaxed text-stone-600">{{ $translation?->answer ?? $faq->answer }}</div>
                    </details>
                @endforeach
            </section>
        @empty
            <div role="status" class="rounded-2xl border border-stone-200 bg-white p-8">
                <h2 class="text-xl font-bold">{{ ($search ?? '') !== '' ? 'No questions match your search.' : 'There are no published questions in this category yet.' }}</h2>
                <p class="mt-3 text-stone-600">{{ website_text('faq', 'Try another word or browse all questions.') }}</p>
                <a href="{{ website_link('faq', 'faq', localized_url('faq')) }}" class="mt-5 inline-block font-bold text-terracotta-600 underline">{{ website_text('faq', 'Clear Search & Filters') }}</a>
            </div>
        @endforelse
    </div>
    <section lang="{{ $copyLocale ?? 'en' }}" class="rounded-2xl bg-nile-50 p-7">
        <h2 class="text-2xl font-bold">{{ website_text('faq', 'Still Have Something on Your Mind?') }}</h2>
        <p class="mt-4 text-stone-600">{{ website_text('faq', 'Public contact links are awaiting confirmation. If you\'ve already booked, reply through your existing conversation with Abdallah and include your booking reference. Please don\'t share passwords or sensitive account credentials.') }}</p>
        <a href="{{ website_link('faq', 'pricing', localized_url('pricing')) }}" class="mt-6 inline-block font-bold text-terracotta-600 underline">{{ website_text('faq', 'Explore Lessons & Pricing') }}</a>
    </section>
</div>
@endsection

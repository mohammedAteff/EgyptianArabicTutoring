
@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div lang="{{ $copyLocale ?? 'en' }}" class="mx-auto max-w-6xl space-y-16 px-4 py-12 sm:px-6 lg:px-8">
    @foreach(config('public_content.pricing') as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        <section class="space-y-7">
            @if(in_array($index, [5, 6], true))
                <details class="rounded-2xl border border-stone-200 bg-white p-6 sm:p-8">
                    <summary class="cursor-pointer text-2xl font-bold">{{ $section['title'] }}</summary>
                    <x-public-copy-section :section="$section" class="mt-6" />
                    <a href="{{ website_link('pricing', 'policy', route('policy')) }}" class="mt-6 inline-block font-bold text-terracotta-600 underline">{{ website_text('pricing', 'Read the Full Booking, Cancellation & Refund Policy') }}</a>
                </details>
            @else
                <x-public-copy-section :section="$section" :level="$index === 0 ? 1 : 2" />
            @endif
            @if($index === 2)
                <div class="grid gap-7 md:grid-cols-2">
                    @foreach(config('public_content.tracks') as $track)
                        <article class="flex flex-col rounded-3xl border border-stone-200 bg-white p-7 shadow-sm sm:p-9">
                            <h3 class="text-2xl font-bold">{{ $track['title'] }}</h3>
                            <p class="mt-2 font-semibold text-nile-700">{{ $track['subtitle'] }}</p>
                            @foreach($track['intro'] as $paragraph)<p class="mt-4 leading-relaxed text-stone-600">{{ $paragraph }}</p>@endforeach
                            <p class="mt-7 text-4xl font-bold">${{ $track['price'] }} <span class="text-sm font-normal text-stone-500">{{ website_text('pricing', 'total package price') }}</span></p>
                            <dl class="mt-5 space-y-3 rounded-xl bg-stone-50 p-4 text-sm">
                                <div class="flex justify-between gap-3"><dt>{{ website_text('pricing', 'Eligible diagnostic credit') }}</dt><dd>−${{ $track['credit'] }}</dd></div>
                                <div class="flex justify-between gap-3"><dt>{{ website_text('pricing', 'Remaining payment when eligible') }}</dt><dd class="font-bold">${{ $track['remaining'] }}</dd></div>
                            </dl>
                            <p class="mt-4 text-sm text-stone-600">${{ $track['session_rate'] }} per session · ${{ $track['hourly_rate'] }} per hour</p>
                            <p class="mt-3 text-sm text-stone-600">Validity: {{ $track['validity_days'] }} calendar days from the first payment, including a partial payment.</p>
                            <ul class="my-6 grow list-disc space-y-3 pl-5 text-sm leading-relaxed text-stone-600">@foreach($track['benefits'] as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
                            <a href="{{ website_link('pricing', 'booking', $bookingUrl) }}" data-cta="booking" class="rounded-xl bg-terracotta-500 px-6 py-3 text-center font-bold text-white hover:bg-terracotta-600">{{ website_text('pricing', 'Start with the Diagnostic') }}</a>
                        </article>
                    @endforeach
                </div>
                <p class="text-sm text-stone-500">The diagnostic credit applies to an eligible package purchased within 48 hours after diagnostic completion. The full standard prices remain $280 and $390.</p>
            @elseif($index === 1 || $index === 7)
                <a href="{{ website_link('pricing', 'booking', $bookingUrl) }}" data-cta="booking" @if($index === 1) data-primary-cta="diagnostic" @endif class="inline-flex rounded-xl bg-terracotta-500 px-6 py-3 font-bold text-white hover:bg-terracotta-600">{{ website_text('pricing', 'Book Your Diagnostic') }}</a>
            @elseif($index === 4)
                <p class="text-sm text-stone-500">{{ website_text('pricing', 'Individual sessions are arranged through your existing conversation with Abdallah. Public contact links are awaiting confirmation.') }}</p>
            @endif
        </section>
    @endforeach
</div>
@endsection

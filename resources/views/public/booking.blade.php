@extends('layouts.public')
@section('body-class', 'studio-booking-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div class="booking-page">
    <header lang="{{ $copyLocale ?? 'en' }}" class="booking-intro">
        <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">{{ website_text('booking', 'Private Arabic Sessions') }}</p>
        <h1 class="mt-3 font-serif text-3xl font-semibold text-nile-900 sm:text-4xl">{{ website_text('booking', 'Let\'s Find a Time That Works for You.') }}</h1>
        <p class="mt-4 leading-8 text-stone-600">{{ website_text('booking', 'Whether you\'re beginning your Arabic journey or returning for another lesson, booking your session should be simple.') }}</p>
        <details class="studio-read-more booking-intro-details">
            <summary>{{ __('About booking your first session') }}</summary>
            <div>
                <p>{{ website_text('booking', 'Choose your session, find a convenient time, and share a few details. You\'ll be able to review everything before confirming your appointment.') }}</p>
                <p>{{ website_text('booking', 'New here? Your first step is the Diagnostic & Learning Roadmap—a friendly introduction, level assessment, and learning consultation for Egyptian Arabic, Modern Standard Arabic (MSA), or both.') }}</p>
            </div>
        </details>
    </header>
    <livewire:booking-wizard />
    <aside lang="{{ $copyLocale ?? 'en' }}" class="booking-returning">
        <h2 class="text-xl font-semibold text-nile-900">{{ website_text('booking', 'Returning Student Options') }}</h2><p>{{ website_text('booking', 'Already have an active coaching package? Use an available session credit to book your next lesson through your student portal.') }}</p>
        <a href="{{ website_link('booking', 'student', route('student.login')) }}" class="mt-4 inline-block font-semibold text-terracotta-600 underline">{{ website_text('booking', 'Go to Student Portal') }}</a>
        <details class="studio-read-more">
            <summary>{{ website_text('booking', 'Standalone lessons and first appointments') }}</summary>
            <p>{{ website_text('booking', 'Standalone lessons are available to eligible returning students and advanced learners by arrangement. First-time students begin with the diagnostic before booking regular private lessons.') }}</p>
        </details>
    </aside>
</div>
@endsection

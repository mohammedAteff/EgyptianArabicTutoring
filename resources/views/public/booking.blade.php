@extends('layouts.public')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
    <header lang="en" class="mx-auto mb-8 max-w-3xl text-center">
        <p class="text-xs font-semibold uppercase tracking-widest text-terracotta-600">Private Arabic Sessions</p>
        <h1 class="mt-3 font-serif text-3xl font-semibold text-nile-900 sm:text-4xl">Let's Find a Time That Works for You.</h1>
        <p class="mt-4 leading-8 text-stone-600">Whether you're beginning your Arabic journey or returning for another lesson, booking your session should be simple.</p>
        <p class="mt-3 leading-8 text-stone-600">Choose your session, find a convenient time, and share a few details. You'll be able to review everything before confirming your appointment.</p>
        <p class="mt-3 leading-8 text-stone-600">New here? Your first step is the $25 Diagnostic &amp; Learning Roadmap—a friendly introduction, level assessment, and learning consultation.</p>
    </header>
    <livewire:booking-wizard />
    <aside lang="en" class="mx-auto mt-10 max-w-3xl rounded-2xl border border-stone-200 bg-white p-6">
        <h2 class="text-xl font-semibold text-nile-900">Returning Student Options</h2><p class="mt-3 leading-7 text-stone-600">Already have an active coaching package? Use an available session credit to book your next lesson through your student portal.</p>
        <a href="{{ route('student.login') }}" class="mt-4 inline-block font-semibold text-terracotta-600 underline">Go to Student Portal</a>
        <p class="mt-4 leading-7 text-stone-600">Standalone lessons are available to eligible returning students and advanced learners by arrangement. First-time students begin with the diagnostic before booking regular private lessons.</p>
    </aside>
</div>
@endsection

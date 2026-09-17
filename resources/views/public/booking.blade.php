@extends('layouts.public')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="text-center max-w-2xl mx-auto mb-6">
        <span class="inline-flex items-center gap-1.5 bg-terracotta-50 text-terracotta-700 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-3">
            📅 Direct Calendar Booking
        </span>
        <h1 class="text-3xl sm:text-4xl font-extrabold text-stone-900 tracking-tight">
            Book Your Private Egyptian Arabic Lesson
        </h1>
        <p class="text-stone-600 text-base mt-2 leading-relaxed">
            Select a date and time in your local timezone. Instant confirmation with calendar download and lesson links.
        </p>
    </div>

    <livewire:booking-wizard />
</div>
@endsection

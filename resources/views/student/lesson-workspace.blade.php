@extends('layouts.student', ['title' => 'Lesson materials'])
@section('content')
<a href="{{ route('student.dashboard') }}" class="inline-flex min-h-11 items-center font-semibold text-nile-800 underline">{{ __('← Your sessions') }}</a>
<h1 class="mt-3 text-3xl font-semibold text-nile-900">{{ __($booking->sessionType?->name ?? 'Private lesson') }}</h1>
<p class="mt-2 text-stone-600">{{ $booking->studentStatusLabel() }}</p>
<div class="mt-5 grid gap-4 sm:grid-cols-2">
    <div class="rounded-xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">{{ __('Your time') }}</h2><p class="mt-2">{{ localized_date($booking->start_at_utc->copy()->setTimezone($timezone), 'D, M j, Y · g:i A') }}</p><p class="text-sm text-stone-500">{{ $timezone }}</p></div>
    <div class="rounded-xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">{{ __('Tutor time') }}</h2><p class="mt-2">{{ localized_date($booking->start_at_utc->copy()->setTimezone($businessTimezone), 'D, M j, Y · g:i A') }}</p><p class="text-sm text-stone-500">{{ $businessTimezone }}</p></div>
</div>
<x-lesson-materials :booking="$booking" />
@endsection

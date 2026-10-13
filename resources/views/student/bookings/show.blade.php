@extends('layouts.student', ['title' => 'Session Details'])
@section('content')
<a href="{{ route('student.dashboard') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">← Back to Sessions</a>
<h1 class="mt-3 text-3xl font-semibold text-nile-900">Your Session</h1>
@if(session('success'))<p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
<x-student-timezone :timezone="$timezone" :student-view="true" />
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
    <h2 class="text-xl font-semibold"><bdi>{{ $booking->sessionType?->title ?? 'Lesson' }}</bdi></h2>
    <p class="mt-3">{{ $booking->start_at_utc->copy()->timezone($timezone)->format('l, j F Y') }}</p>
    <p class="mt-1 font-semibold">{{ $booking->start_at_utc->copy()->timezone($timezone)->format('g:i A') }} – {{ $booking->end_at_utc->copy()->timezone($timezone)->format('g:i A') }}</p>
    <p class="mt-1 text-sm text-stone-500">{{ $timezone }} · {{ $statusLabel }}</p>
    @if($booking->status === 'completed')<p class="mt-4 text-stone-600">This session has been completed.</p>@endif
    @if($booking->status === 'cancelled')<p class="mt-4 text-stone-600">This appointment has been cancelled.</p>@endif
</section>
@if($booking->status === 'confirmed')
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6" aria-labelledby="meeting-title">
    <h2 id="meeting-title" class="text-xl font-semibold">Your Lesson Link</h2>
    @if($meetingUrl)
        <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">Join Lesson</a>
    @elseif($booking->end_at_utc->isPast())
        <p class="mt-3 text-sm text-stone-600">The meeting window for this appointment has ended.</p>
    @elseif($booking->meeting_room_id)
        <p class="mt-3 text-sm text-stone-600">Available closer to your lesson time. Your meeting link opens {{ $meetingRevealMinutes }} minutes before the lesson.</p>
    @else
        <p class="mt-3 text-sm text-stone-600">Your meeting link will appear here when it's available.</p>
    @endif
</section>
@endif
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6" aria-labelledby="payment-title">
    <h2 id="payment-title" class="text-xl font-semibold">Payment</h2>
    @if($creditEntry && $creditEntry->student_package_id)
        <p class="mt-3 font-medium">{{ $creditUsed ? 'Lesson credit used' : 'Credit returned' }}</p>
        <a href="{{ route('student.statements.show', $creditEntry->student_package_id) }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">View Package Statement</a>
    @elseif($booking->funding_mode === 'package')
        <p class="mt-3 text-sm text-stone-600">This appointment uses lesson credits. Your package statement shows the recorded credit balance.</p>
        <a href="{{ route('student.statements.index') }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">Statements &amp; Receipts</a>
    @else
        <p class="mt-3 font-medium">To be arranged</p>
        <p class="mt-2 text-sm text-stone-600">Payment is handled separately from this appointment.</p>
    @endif
</section>
<div class="mt-6 flex flex-wrap gap-4">
    @if($canAddToCalendar)<a href="{{ route('booking.ics', $booking->confirmation_token) }}" class="inline-flex min-h-11 items-center rounded-xl border border-stone-300 bg-white px-5 text-sm font-semibold">Add to Calendar</a>@endif
    @if($canViewMaterials)<a href="{{ route('student.lessons.show', $booking->id) }}" class="inline-flex min-h-11 items-center rounded-xl border border-stone-300 bg-white px-5 text-sm font-semibold">View Learning Materials</a>@endif
</div>
@if($booking->status === 'confirmed')
<section class="mt-6 rounded-2xl border border-stone-200 bg-white p-6">
    <h2 class="text-xl font-semibold">Need to Change Your Time?</h2>
    @if($canReschedule)
        <p class="mt-3 text-sm text-stone-600">This lesson is eligible for online rescheduling. You can choose another available time.</p>
        <a href="{{ route('student.bookings.reschedule', $booking->id) }}" class="mt-3 inline-flex min-h-11 items-center rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">Reschedule Lesson</a>
    @else
        <p class="mt-3 text-sm text-stone-600">This lesson can no longer be changed through the portal. Contact Abdallah if you need help.</p>
    @endif
</section>
@endif
@endsection

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
            Terms of Service & Booking Policy
        </h1>
        <p class="text-stone-500 text-sm mt-1">Last updated: September 2026</p>
    </div>

    <div @if($isFallback ?? false) lang="en" dir="ltr" @endif class="bg-white rounded-3xl border border-stone-200/80 p-8 sm:p-12 shadow-sm space-y-8 text-stone-700 leading-relaxed text-sm sm:text-base">
        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">1. Booking & Session Confirmation</h2>
            <p>
                All private Egyptian Arabic tutoring sessions are booked directly through our website. Upon selecting an available time slot and providing your details, an authoritative calendar hold and instant booking record are generated. You will receive an immediate confirmation screen with your unique booking reference and a downloadable RFC 5545 compliant calendar file (.ics).
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">2. Rescheduling & Cancellation Policy</h2>
            <p>
                We understand that life and travel schedules change. Rescheduling or cancellation is permitted free of charge up to <strong>24 hours prior</strong> to the scheduled start time of your session.
            </p>
            <p class="mt-2">
                Cancellations made within less than 24 hours of the appointment start time are non-refundable and cannot be rescheduled, as that calendar time was reserved exclusively for you and cannot be reallocated to another student.
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">3. Punctuality & No-Show Policy</h2>
            <p>
                Please join the video meeting link at your scheduled start time. If a student is more than 15 minutes late without prior communication, the session will be considered a no-show, and the tutor's obligation for that session will be satisfied.
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">4. Learning Resources & Workbooks</h2>
            <p>
                All PDF workbooks, vocabulary cheat-sheets, audio recordings, and interactive materials provided through this website are for your personal language study only. Redistribution, public re-uploading, or commercial resale without express written permission is strictly prohibited.
            </p>
        </div>

        <div>
            <h2 class="text-lg font-bold text-stone-900 mb-2">5. Technical Requirements</h2>
            <p>
                Students are responsible for having a reliable internet connection, a functioning microphone, and a device capable of running Zoom or Google Meet with video.
            </p>
        </div>
    </div>
</div>
@endsection

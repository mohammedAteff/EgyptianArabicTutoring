@extends('layouts.public')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    @if(session('success'))
        <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl text-sm text-emerald-800 font-medium shadow-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('info'))
        <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl text-sm text-amber-800 font-medium shadow-sm">
            {{ session('info') }}
        </div>
    @endif

    <!-- Status Card -->
    <div class="bg-white rounded-3xl border border-stone-200/80 p-8 shadow-sm text-center mb-8">
        @php
            $statusLabel = $booking->studentStatusLabel();
            $isScheduled = $booking->status === 'confirmed';
            $isDelivered = $booking->status === 'completed';
            $statusDescription = match ($booking->status) {
                'completed' => 'Your lesson has been delivered. Review your learning profile or schedule another session in your student portal.',
                'cancelled' => 'This booking was canceled. Its time slot has been released.',
                'no_show', 'no-show' => 'This session was forfeited. Contact your tutor if you have questions.',
                'confirmed' => $booking->reschedules_exists ? 'Your session has been rescheduled. Your updated lesson details are below.' : 'Your booking is confirmed. Your calendar invitation and meeting details are below.',
                'pending' => 'Your booking is awaiting confirmation.',
                'held' => 'This time is reserved temporarily. Complete your booking to confirm it.',
                default => 'We could not determine the status of this booking. Please contact your tutor.',
            };
        @endphp
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full {{ $isScheduled || $isDelivered ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}" aria-hidden="true"><span class="text-3xl">{{ $isScheduled || $isDelivered ? '✓' : '!' }}</span></div>
        <span class="mb-2 inline-flex rounded-full bg-stone-100 px-3 py-1 text-xs font-bold uppercase">{{ __($statusLabel) }}</span>
        <h1 class="text-3xl font-extrabold tracking-tight text-stone-900">{{ __($statusLabel) }}</h1>
        <p class="mx-auto mt-2 max-w-md text-sm text-stone-600">{{ __($statusDescription) }}</p>
        @unless($isScheduled)<a href="{{ localized_url('booking') }}" class="mt-6 inline-flex rounded-full bg-terracotta-500 px-6 py-2.5 text-sm font-semibold text-white">{{ __('Book a New Time') }}</a>@endunless

        <div class="mt-6 pt-6 border-t border-stone-100 flex flex-wrap items-center justify-center gap-6 text-xs text-stone-500 font-mono">
            <div>
                <span class="text-stone-400">{{ __('Reference:') }}</span>
                <span class="font-bold text-stone-800">{{ substr($booking->confirmation_token, 0, 12) }}</span>
            </div>
            <div>
                <span class="text-stone-400">{{ __('Student:') }}</span>
                <span class="font-semibold text-stone-800">{{ $booking->contact?->name ?? 'Student' }}</span>
            </div>
        </div>
    </div>

    <!-- Dual Timezone Breakdown Card -->
    @php
        $confirmationInstant = \Carbon\CarbonImmutable::instance($booking->start_at_utc);
        $customerStart = $booking->customer_start;
        $customerEnd = $booking->end_at_utc->copy()->setTimezone($customerStart->timezoneName);
        $customerTimezoneDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)
            ->formatSlotForDisplay($customerStart->timezoneName, $confirmationInstant);
        $businessStart = $booking->business_start;
        $businessTimezoneDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)
            ->formatSlotForDisplay($businessStart->timezoneName, $confirmationInstant);
    @endphp
    <div class="bg-white rounded-3xl border border-stone-200/80 p-8 shadow-sm mb-8 space-y-6">
        <h2 class="text-lg font-bold text-stone-900 border-b border-stone-100 pb-3">{{ __('Lesson Schedule') }}</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Customer Timezone Representation -->
            <div class="p-5 rounded-2xl bg-stone-50 border border-stone-200">
                <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                    <x-timezone-flag :display="$customerTimezoneDisplay" :alt="__('Flag for :country', ['country' => $customerTimezoneDisplay['timezone_country_name']])" />
                    {{ __('Your Local Time') }}
                </div>
                <div class="text-lg font-extrabold text-stone-900">
                    {{ $customerStart->format('l, F j, Y') }}
                </div>
                <div class="text-xl font-extrabold text-terracotta-600 mt-1">
                    {{ $customerStart->format('g:i A') }}
                    -
                    {{ $customerEnd->format('g:i A') }}
                </div>
                <div class="text-xs text-stone-500 mt-1">
                    {{ $customerTimezoneDisplay['city'] }} · {{ $booking->customer_timezone }} (UTC{{ $booking->customer_utc_offset_at_booking }})
                </div>
            </div>

            <!-- Tutor Timezone Representation -->
            <div class="p-5 rounded-2xl bg-nile-50/50 border border-nile-100">
                <div class="text-xs font-bold text-nile-600 uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                    <x-timezone-flag :display="$businessTimezoneDisplay" :alt="__('Tutor timezone: :city', ['city' => $businessTimezoneDisplay['city']])" />
                    {{ $businessStart->timezoneName === 'Africa/Cairo' ? __("Tutor's Time (Cairo)") : __("Tutor's Time").' ('.$businessStart->timezoneName.')' }}
                </div>
                <div class="text-lg font-extrabold text-stone-900">
                    {{ $businessStart->format('l, F j, Y') }}
                </div>
                <div class="text-xl font-extrabold text-nile-700 mt-1">
                    {{ $businessStart->format('g:i A') }}
                    -
                    {{ $booking->end_at_utc->copy()->setTimezone($businessStart->timezoneName)->format('g:i A') }}
                </div>
                <div class="text-xs text-stone-500 mt-1">
                    {{ $businessTimezoneDisplay['city'] }} · {{ $businessStart->timezoneName }} (UTC{{ $businessStart->format('P') }})
                </div>
            </div>
        </div>

        <!-- Session Details Row -->
        <div class="pt-4 border-t border-stone-100 flex flex-wrap items-center justify-between text-sm">
            <div>
                <span class="text-stone-500">{{ __('Session:') }}</span>
                <span class="font-bold text-stone-900 ml-1">{{ $booking->sessionType?->title }}</span>
                <span class="text-stone-400 ml-1">({{ $booking->sessionType?->duration_minutes }} {{ __('min') }})</span>
            </div>
            <div>
                <span class="text-stone-500">{{ __('Tuition:') }}</span>
                <span class="font-bold text-terracotta-600 ml-1">${{ number_format($booking->sessionType?->price, 2) }} {{ $booking->sessionType?->currency }}</span>
            </div>
        </div>

        @if($booking->status === 'confirmed')
            <!-- Calendar Actions -->
            <div class="pt-6 border-t border-stone-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="{{ route('booking.ics', ['token' => $booking->confirmation_token]) }}"
                   class="w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-stone-900 hover:bg-black text-white font-semibold text-sm px-6 py-3 rounded-xl shadow-sm hover:shadow transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span>{{ __('Add to Calendar (.ics)') }}</span>
                </a>

                <div class="text-xs text-stone-400">
                    {{ __('Works with Apple Calendar, Google Calendar, Outlook') }}
                </div>
            </div>
        @endif
    </div>

    <!-- Next Steps & Instructions -->
    <div class="bg-white rounded-3xl border border-stone-200/80 p-8 shadow-sm mb-8 space-y-4">
        <h2 class="text-lg font-bold text-stone-900">{{ __('What Happens Next?') }}</h2>
        <div class="space-y-3 text-sm text-stone-600">
            @if($isScheduled)
                <p>{{ __('Save this page and add your lesson to your calendar using the button above.') }}</p>
                @if($meetingUrl)<p>{{ __('At lesson time, connect via your private classroom:') }} <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="font-bold text-terracotta-600 underline">{{ __('Join Video Classroom →') }}</a></p>
                @else<p>{{ __('Your tutor will share the private video meeting link before the lesson.') }}</p>@endif
                <p>{{ __('If this is your first session, your student portal access becomes active once your profile has been verified.') }}</p>
            @else<p>{{ __('This session has no active meeting link. Review your sessions or book another time in your student portal.') }}</p>@endif
            <p>{{ __('To sign in, use your Date of Birth plus any two registered identifiers: your name, email address, or phone number.') }}</p>
            <a href="{{ route('student.login') }}" class="inline-block font-semibold text-nile-800 underline">{{ __('Student Portal') }}</a>
        </div>
    </div>

    <!-- Cancellation and direct-contact rescheduling (if confirmed and eligible) -->
    @if($booking->status === 'confirmed')
        @php
            $cutoffHours = (int) \App\Domains\CMS\Models\Setting::get('booking_cancellation_cutoff_hours', 4);
            $nowUtc = now('UTC');
            $isFuture = $booking->start_at_utc > $nowUtc;
            $isOutsideCutoff = $booking->start_at_utc >= $nowUtc->copy()->addHours($cutoffHours);
            $canMutate = $isFuture && $isOutsideCutoff;
        @endphp

        @if($canMutate)
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-2">
                <p class="max-w-md text-center text-xs text-stone-500">
                    {{ __('Need a different time? Contact Abdallah directly by WhatsApp, Telegram, or email at least 24 hours before the lesson. The tutor will confirm a new available slot.') }}
                </p>

                <div x-data="{ showCancelModal: false }">
                    <button type="button"
                            @click="showCancelModal = true"
                            class="text-xs font-semibold text-stone-400 hover:text-rose-600 transition-colors underline py-2">
                        {{ __('Need to cancel this lesson?') }}
                    </button>

                    <!-- Cancel Modal -->
                    <div x-show="showCancelModal"
                         x-cloak
                         class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="bg-white rounded-3xl p-6 max-w-md w-full text-left shadow-2xl space-y-4"
                             @click.away="showCancelModal = false">
                            <h3 class="text-lg font-bold text-stone-900">{{ __('Cancel Lesson') }}</h3>
                            <p class="text-sm text-stone-600 leading-relaxed">
                                {{ __('Are you sure you want to cancel your session scheduled for :date? The slot will be released back to other students.', ['date' => $customerStart->format('M j, Y \a\t g:i A')]) }}
                            </p>

                            <form method="POST" action="{{ route('booking.cancel', ['token' => $booking->confirmation_token]) }}" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="reason" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-1">{{ __('Reason (Optional)') }}</label>
                                    <input type="text" name="reason" id="reason" placeholder="e.g. Work conflict, unexpected illness" class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:border-terracotta-500 outline-none">
                                </div>

                                <div class="flex items-center justify-end gap-3 pt-2">
                                    <button type="button" @click="showCancelModal = false" class="px-4 py-2 text-sm font-semibold text-stone-600 hover:text-stone-900 rounded-xl">
                                        {{ __('Keep Lesson') }}
                                    </button>
                                    <button type="submit" class="px-4 py-2 text-sm font-semibold bg-rose-600 hover:bg-rose-700 text-white rounded-xl shadow-sm">
                                        {{ __('Confirm Cancellation') }}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="text-center pt-2">
                <p class="text-xs text-stone-400">
                    @if(! $isFuture)
                        {{ __('This session has passed and cannot be cancelled or rescheduled.') }}
                    @else
                        {{ __('This session is within the :hours-hour policy cutoff window and cannot be rescheduled or cancelled online. Please contact the tutor directly for assistance.', ['hours' => $cutoffHours]) }}
                    @endif
                </p>
            </div>
        @endif
    @endif
</div>
@endsection

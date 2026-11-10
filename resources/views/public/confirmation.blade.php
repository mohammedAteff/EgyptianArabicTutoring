@extends('layouts.public')
@section('body-class', 'studio-booking-page')

@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<x-student-timezone :timezone="$timezone" />
<div class="booking-confirmation">
    @if(session('success'))
        <div class="mb-6 bg-emerald-50 border-l-4 border-emerald-500 p-4 rounded-r-xl text-sm text-emerald-800 font-medium shadow-sm">
            {{ __(session('success')) }}
        </div>
    @endif
    @if(session('error'))
        <div role="alert" class="booking-error">{{ __(session('error')) }}</div>
    @endif
    @if(session('info'))
        <div class="mb-6 bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl text-sm text-amber-800 font-medium shadow-sm">
            {{ __(session('info')) }}
        </div>
    @endif

    <!-- Status Card -->
    <div class="booking-panel text-center mb-8">
        @php
            $statusLabel = $booking->studentStatusLabel();
            $isScheduled = $booking->status === 'confirmed';
            $isDelivered = $booking->status === 'completed';
            $isDiagnostic = $booking->sessionType?->slug === 'diagnostic-session';
            $statusDescription = match ($booking->status) {
                'completed' => 'Your lesson has been delivered. Review your learning profile or schedule another session in your student portal.',
                'cancelled' => 'This booking was canceled. Its time slot has been released.',
                'no_show', 'no-show' => 'This session was forfeited. Contact your tutor if you have questions.',
                'confirmed' => $booking->reschedules_exists ? 'Your session has been rescheduled. Your updated lesson details are below.' : match ($booking->funding_mode) {
                    'package' => 'Your appointment time is reserved using a package session credit. Your session details and available calendar download are below.',
                    'free' => 'Your appointment time is reserved. Your session details and available calendar download are below.',
                    default => 'Your appointment time is reserved. Payment is arranged separately; confirming this booking has not charged you. Your session details and available calendar download are below.',
                },
                'pending' => 'Your booking is awaiting confirmation.',
                'held' => 'This time is reserved temporarily. Complete your booking to confirm it.',
                default => 'We could not determine the status of this booking. Please contact your tutor.',
            };
        @endphp
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full {{ $isScheduled || $isDelivered ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800' }}" aria-hidden="true"><span class="text-3xl">{{ $isScheduled || $isDelivered ? '✓' : '!' }}</span></div>
        <span class="sr-only mb-2 inline-flex rounded-full bg-stone-100 px-3 py-1 text-xs font-bold uppercase">{{ __($statusLabel) }}</span>
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
                <span class="font-semibold text-stone-800">{{ __($booking->contact?->name ?? 'Student') }}</span>
            </div>
        </div>
    </div>

    <!-- Appointment-specific local schedule -->
    @php
        $confirmationInstant = \Carbon\CarbonImmutable::instance($booking->start_at_utc);
        $customerStart = $booking->start_at_utc->copy()->setTimezone($timezone);
        $customerEnd = $booking->end_at_utc->copy()->setTimezone($customerStart->timezoneName);
        $customerTimezoneDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)
            ->formatSlotForDisplay($customerStart->timezoneName, $confirmationInstant);
        $businessStart = $confirmationInstant->setTimezone(app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone());
        $businessEnd = \Carbon\CarbonImmutable::instance($booking->end_at_utc)->setTimezone($businessStart->timezoneName);
        $businessTimezoneDisplay = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)
            ->formatSlotForDisplay($businessStart->timezoneName, $confirmationInstant);
        $localScheduleMatches = $customerStart->format('Y-m-d H:i:s') === $businessStart->format('Y-m-d H:i:s')
            && $customerEnd->format('Y-m-d H:i:s') === $businessEnd->format('Y-m-d H:i:s');
        $schedulePanels = [
            ['label' => $localScheduleMatches ? __('Your Lesson Schedule') : __('Your Local Time'), 'start' => $customerStart, 'end' => $customerEnd, 'display' => $customerTimezoneDisplay],
        ];
        if (!$localScheduleMatches) {
            $schedulePanels[] = ['label' => __("Tutor's Time (:timezone)", ['timezone' => $businessStart->timezoneName]), 'start' => $businessStart, 'end' => $businessEnd, 'display' => $businessTimezoneDisplay];
        }
    @endphp
    <div class="booking-panel mb-8 space-y-6">
        <h2 class="text-lg font-bold text-stone-900 border-b border-stone-100 pb-3">{{ __('Lesson Schedule') }}</h2>

        <div @class(['grid grid-cols-1 gap-6', 'md:grid-cols-2' => !$localScheduleMatches])>
            @foreach($schedulePanels as $panel)
            <div data-confirmation-schedule @class(['p-5 rounded-2xl border', 'bg-stone-50 border-stone-200' => $loop->first, 'bg-nile-50/50 border-nile-100' => !$loop->first])>
                <div class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-1 inline-flex items-center gap-1.5">
                    <x-timezone-flag :display="$panel['display']" :alt="__('Flag for :country', ['country' => $panel['display']['timezone_country_name']])" />
                    {{ $panel['label'] }}
                </div>
                <div class="text-lg font-extrabold text-stone-900">
                    {{ localized_date($panel['start'], 'l, F j, Y') }}
                </div>
                <div class="text-xl font-extrabold text-terracotta-600 mt-1">
                    {{ localized_date($panel['start'], 'g:i A') }} –
                    @if($panel['start']->toDateString() !== $panel['end']->toDateString())
                        {{ localized_date($panel['end'], 'l, F j, Y') }} ·
                    @endif
                    {{ localized_date($panel['end'], 'g:i A') }}
                </div>
                <div class="text-xs text-stone-500 mt-1">
                    {{ $panel['display']['city'] }} · {{ $panel['start']->timezoneName }} {{ __('(UTC') }}{{ $panel['start']->format('P') }})
                    @if($panel['start']->format('P') !== $panel['end']->format('P'))
                        · {{ __('End offset: :offset', ['offset' => 'UTC'.$panel['end']->format('P')]) }}
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        @if($localScheduleMatches)
            <p class="text-sm text-stone-600">{{ $customerStart->timezoneName === $businessStart->timezoneName
                ? __('Your local appointment time matches the tutor’s time in :timezone.', ['timezone' => $businessStart->timezoneName])
                : __('For this appointment, :student and :tutor have matching local dates and times.', ['student' => $customerStart->timezoneName, 'tutor' => $businessStart->timezoneName]) }}</p>
        @endif

        <!-- Session Details Row -->
        <div class="pt-4 border-t border-stone-100 flex flex-wrap items-center justify-between text-sm">
            <div>
                <span class="text-stone-500">{{ __('Session:') }}</span>
                <span class="font-bold text-stone-900 ml-1">{{ __($booking->sessionType?->title ?? '') }}</span>
                <span class="text-stone-400 ml-1">({{ $booking->sessionType?->duration_minutes }} {{ __('min') }})</span>
            </div>
            <div>
                <span class="text-stone-500">{{ __('Tuition:') }}</span>
                <span class="font-bold text-terracotta-600 ml-1">{{ localized_number($booking->sessionType?->price, 2) }} {{ $booking->sessionType?->currency }}</span>
            </div>
        </div>

        @if($booking->status === 'confirmed')
            <!-- Calendar Actions -->
            <div class="pt-6 border-t border-stone-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                <a href="{{ route('booking.ics', ['token' => $booking->confirmation_token, 'lang' => app()->getLocale()]) }}"
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
    <section data-confirmation-next-steps class="booking-panel mb-8 space-y-4">
        <h2 class="text-lg font-bold text-stone-900">{{ __('What Happens Next?') }}</h2>
        <ol class="list-inside list-decimal space-y-3 text-sm text-stone-600">
            @if($isScheduled)
                <li>{{ __('Save this page and add your lesson to your calendar using the button above.') }}</li>
                @if(in_array($booking->funding_mode, ['direct', 'legacy'], true))
                    <li>{{ __('Abdallah will contact you personally to arrange payment and confirm the practical details.') }}</li>
                @endif
                @if($meetingUrl)<li>{{ __('At lesson time, connect via your private classroom:') }} <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="font-bold text-terracotta-600 underline">{{ __('Join Video Classroom →') }}</a></li>
                @else<li>{{ __('Your tutor will share the private video meeting link before the lesson. Keep this confirmation page for access when it becomes available.') }}</li>@endif
            @else<li>{{ __('This session has no active meeting link. Review your sessions or book another time in your student portal.') }}</li>@endif
        </ol>
        @if($isDiagnostic && $isScheduled)
            <p class="text-sm leading-7 text-stone-600">{{ __('After your diagnostic, you’ll receive a personalized written learning roadmap.') }}</p>
        @endif
        @if($isDiagnostic && $isScheduled && $booking->funding_mode !== 'package')
            <p class="text-sm leading-7 text-stone-600">{{ __('If you enroll in an eligible coaching package within 48 hours after completing the diagnostic, the full $25 fee is credited toward its price. No package purchase is required.') }}</p>
        @endif
        <div class="border-t border-stone-200 pt-4 space-y-3">
            <h3 class="font-bold text-stone-900">{{ __('Student Portal & Sign-in') }}</h3>
            @if($isScheduled)<p class="text-sm leading-7 text-stone-600">{{ __('If this is your first session, your student portal access becomes active once your profile has been verified.') }}</p>@endif
            <p class="text-sm leading-7 text-stone-600">{{ __('To sign in, use your Date of Birth plus any two registered identifiers: your name, email address, or phone number.') }}</p>
            <a href="{{ route('student.login') }}" class="studio-button studio-button-secondary">{{ __('Student Portal') }}</a>
        </div>
    </section>
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
                    <span>{{ __('To request rescheduling, contact Abdallah directly at least 24 hours before your lesson. For cancellation and rebooking by direct contact, allow at least four hours before the appointment. A cancellation with sufficient notice is eligible for a full refund; payments are handled personally.') }}</span>
                    @if($booking->start_at_utc >= $nowUtc->copy()->addHours(24))
                        <a href="{{ route('booking.reschedule', $booking->confirmation_token) }}" class="mt-3 block font-semibold text-terracotta-600 underline">{{ __('Request Rescheduling') }}</a>
                    @endif
                    <a href="{{ route('policy') }}" class="mt-3 block font-semibold underline">{{ __('Read the Booking & Refund Policy') }}</a>
                </p>

                <div x-data="{
                    showCancelModal: false,
                    closeCancel() { this.showCancelModal = false; this.$refs.cancelTrigger.focus(); },
                    trapCancelFocus(event) {
                        const controls = Array.from(this.$refs.cancelDialog.querySelectorAll('button, input')).filter(control => control.type !== 'hidden');
                        const first = controls[0], last = controls[controls.length - 1];
                        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
                    }
                }">
                    <button type="button"
                            x-ref="cancelTrigger" @click="showCancelModal = true; $nextTick(() => $refs.cancelReason.focus())"
                            class="text-xs font-semibold text-stone-400 hover:text-rose-600 transition-colors underline py-2">
                        {{ __('Need to cancel this lesson?') }}
                    </button>

                    <!-- Cancel Modal -->
                    <div x-show="showCancelModal"
                         x-cloak
                         @keydown.escape.window="if (showCancelModal) closeCancel()"
                         @click.self="closeCancel()"
                         class="fixed inset-0 z-50 bg-stone-900/60 backdrop-blur-sm flex items-center justify-center p-4">
                        <div class="bg-white rounded-3xl p-6 max-w-md w-full text-left shadow-2xl space-y-4"
                             x-ref="cancelDialog" role="dialog" aria-modal="true" aria-labelledby="cancel-lesson-title" @keydown.tab="trapCancelFocus($event)">
                            <h3 id="cancel-lesson-title" class="text-lg font-bold text-stone-900">{{ __('Cancel Lesson') }}</h3>
                            <p class="text-sm text-stone-600 leading-relaxed">
                                {{ __('Are you sure you want to cancel your session scheduled for :date? The slot will be released back to other students.', ['date' => $customerStart->locale(app()->getLocale())->translatedFormat(localized_date_format('M j, Y \a\t g:i A'))]) }}
                            </p>

                            <form method="POST" action="{{ route('booking.cancel', ['token' => $booking->confirmation_token]) }}" class="space-y-4">
                                @csrf
                                <div>
                                    <label for="reason" class="block text-xs font-bold text-stone-700 uppercase tracking-wider mb-1">{{ __('Reason (Optional)') }}</label>
                                    <input type="text" x-ref="cancelReason" name="reason" id="reason" placeholder="{{ __('e.g. Work conflict, unexpected illness') }}" class="w-full px-3 py-2 text-sm border border-stone-200 rounded-xl focus:border-terracotta-500 outline-none">
                                </div>

                                <div class="flex items-center justify-end gap-3 pt-2">
                                    <button type="button" @click="closeCancel()" class="px-4 py-2 text-sm font-semibold text-stone-600 hover:text-stone-900 rounded-xl">
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
                        {{ __('This session is within the :hours-hour policy cutoff window. Please contact the tutor directly for assistance.', ['hours' => $cutoffHours]) }}
                    @endif
                </p>
            </div>
        @endif
    @endif
</div>
@endsection

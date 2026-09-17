@extends('layouts.public')

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12" x-data="{ selectedDate: '{{ array_key_first($availableSlots) ?? '' }}', selectedSlot: null }">

    @if(session('error'))
        <div class="mb-6 bg-red-50 border-l-4 border-red-500 p-4 rounded-r-xl text-sm text-red-800 font-medium shadow-sm">
            {{ session('error') }}
        </div>
    @endif

    <!-- Header & Breadcrumb -->
    <div class="mb-8">
        <a href="{{ route('booking.confirmation', ['token' => $booking->confirmation_token]) }}" class="inline-flex items-center gap-1.5 text-xs font-bold text-stone-500 hover:text-stone-900 transition-colors mb-4">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            <span>Back to Confirmation</span>
        </a>
        <h1 class="text-3xl font-extrabold text-stone-900 tracking-tight">Reschedule Lesson</h1>
        <p class="text-stone-600 text-sm mt-1">Select a new date and time that suits your schedule. Your current slot will be automatically released.</p>
    </div>

    <!-- Current Lesson Snapshot -->
    <div class="bg-white rounded-3xl border border-stone-200/80 p-6 shadow-sm mb-8">
        <div class="text-xs font-bold uppercase tracking-wider text-stone-400 mb-2">Current Appointment</div>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <div class="text-base font-extrabold text-stone-900">{{ $booking->sessionType?->title }}</div>
                <div class="text-sm font-semibold text-terracotta-600 mt-0.5">
                    {{ \Carbon\CarbonImmutable::instance($booking->start_at_utc)->setTimezone($booking->customer_timezone)->format('l, F j, Y \a\t g:i A') }}
                    ({{ str_replace('_', ' ', $booking->customer_timezone) }})
                </div>
            </div>
            <span class="px-3 py-1 bg-stone-100 text-stone-700 rounded-full text-xs font-bold shrink-0">
                Ref: #{{ substr($booking->confirmation_token, 0, 8) }}
            </span>
        </div>
    </div>

    <!-- Slot Selection Section -->
    <div class="bg-white rounded-3xl border border-stone-200/80 p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex items-center justify-between border-b border-stone-100 pb-4">
            <h2 class="text-lg font-bold text-stone-900">Choose a New Time</h2>
            <div class="text-xs text-stone-500">
                Times displayed in <span class="font-bold text-stone-800">{{ str_replace('_', ' ', $booking->customer_timezone) }}</span>
            </div>
        </div>

        @if(empty($availableSlots))
            <div class="py-12 text-center text-stone-500 text-sm">
                No available tutoring slots found in the next 30 days. Please reach out directly to coordinate.
            </div>
        @else
            <!-- Date Tabs Carousel -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-stone-400 mb-2">1. Select Date</label>
                <div class="flex gap-2 overflow-x-auto pb-2 scrollbar-none">
                    @foreach($availableSlots as $date => $slots)
                        @php
                            $carbonDate = \Carbon\CarbonImmutable::parse($date, $booking->customer_timezone);
                        @endphp
                        <button type="button"
                                @click="selectedDate = '{{ $date }}'; selectedSlot = null;"
                                :class="selectedDate === '{{ $date }}' ? 'bg-terracotta-500 text-white shadow-sm' : 'bg-stone-50 text-stone-700 hover:bg-stone-100 border border-stone-200/80'"
                                class="px-4 py-3 rounded-2xl text-center shrink-0 min-w-[90px] transition-all cursor-pointer">
                            <div class="text-[11px] font-bold uppercase tracking-wider opacity-80">{{ $carbonDate->format('D') }}</div>
                            <div class="text-base font-extrabold">{{ $carbonDate->format('M j') }}</div>
                            <div class="text-[10px] opacity-75 mt-0.5">{{ count($slots) }} slot(s)</div>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Time Slots for Selected Date -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-stone-400 mb-2">2. Select Time</label>
                
                @foreach($availableSlots as $date => $slots)
                    <div x-show="selectedDate === '{{ $date }}'" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach($slots as $slot)
                            <button type="button"
                                    @click="selectedSlot = '{{ $slot['slot_start_utc'] }}'"
                                    :class="selectedSlot === '{{ $slot['slot_start_utc'] }}' ? 'bg-stone-900 text-white ring-2 ring-stone-900 ring-offset-2' : 'bg-stone-50 text-stone-800 hover:bg-stone-100 border border-stone-200'"
                                    class="p-3 rounded-xl text-center text-sm font-bold transition-all cursor-pointer">
                                <div>{{ $slot['customer_formatted'] }}</div>
                                <div class="text-[10px] font-normal opacity-70 mt-0.5">Cairo {{ $slot['business_start_time'] }}</div>
                            </button>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <!-- Reschedule Submission Form -->
            <form method="POST" action="{{ route('booking.reschedule.submit', ['token' => $booking->confirmation_token]) }}" class="pt-6 border-t border-stone-100 space-y-4">
                @csrf
                <input type="hidden" name="new_start_utc" :value="selectedSlot">

                <div>
                    <label for="reason" class="block text-xs font-bold uppercase tracking-wider text-stone-600 mb-1.5">Reason for Rescheduling (Optional)</label>
                    <input type="text" id="reason" name="reason" placeholder="e.g. Work meeting conflict, flight delay"
                           class="w-full px-4 py-2.5 bg-stone-50 border border-stone-300 rounded-xl text-sm focus:ring-2 focus:ring-terracotta-500 focus:outline-none">
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
                    <div class="text-xs text-stone-500">
                        <span x-show="!selectedSlot">Please choose a date and time slot above to proceed.</span>
                        <span x-show="selectedSlot" class="text-emerald-700 font-semibold" style="display: none;">Ready to reschedule your session.</span>
                    </div>

                    <div class="flex items-center gap-3 w-full sm:w-auto">
                        <a href="{{ route('booking.confirmation', ['token' => $booking->confirmation_token]) }}" class="w-full sm:w-auto text-center px-4 py-2.5 text-xs font-semibold text-stone-600 hover:text-stone-900">
                            Cancel
                        </a>
                        <button type="submit"
                                :disabled="!selectedSlot"
                                :class="selectedSlot ? 'bg-terracotta-500 hover:bg-terracotta-600 cursor-pointer shadow-sm' : 'bg-stone-300 cursor-not-allowed opacity-60'"
                                class="w-full sm:w-auto px-6 py-2.5 text-white font-bold text-sm rounded-full transition-all">
                            Confirm New Time
                        </button>
                    </div>
                </div>
            </form>
        @endif
    </div>

</div>
@endsection

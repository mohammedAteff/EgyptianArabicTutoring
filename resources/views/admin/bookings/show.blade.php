@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-8" x-data="{ rescheduleModalOpen: false, cancelModalOpen: false }">

    <!-- Top Breadcrumb & Status -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                <a href="{{ route('admin.bookings.index') }}" class="hover:text-amber-600 transition-colors">&larr; Back to Bookings</a>
                <span>&bull;</span>
                <span>Booking #{{ $booking->id }}</span>
            </div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight flex items-center gap-3">
                <span>{{ $booking->contact->name ?? 'Student' }}</span>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold capitalize
                    {{ $booking->status === 'confirmed' ? 'bg-amber-100 text-amber-900 border border-amber-300' : '' }}
                    {{ $booking->status === 'completed' ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : '' }}
                    {{ $booking->status === 'no_show' ? 'bg-red-100 text-red-900 border border-red-300' : '' }}
                    {{ $booking->status === 'cancelled' ? 'bg-slate-100 text-slate-700 border border-slate-300' : '' }}">
                    {{ str_replace('_', ' ', $booking->status) }}
                </span>
            </h1>
        </div>

        <!-- Quick Status Actions -->
        @if($booking->status === 'confirmed')
            <div class="flex items-center gap-2">
                <form action="{{ route('admin.bookings.complete', $booking->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs">
                        Mark Completed
                    </button>
                </form>
                <form action="{{ route('admin.bookings.no-show', $booking->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 bg-slate-200 hover:bg-red-100 hover:text-red-700 text-slate-700 rounded-xl text-xs font-bold transition-colors">
                        Mark No-Show
                    </button>
                </form>
                <button @click="rescheduleModalOpen = true" class="px-3.5 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                    Reschedule
                </button>
                <button @click="cancelModalOpen = true" class="px-3.5 py-2 bg-white border border-red-300 text-red-600 hover:bg-red-50 rounded-xl text-xs font-bold transition-colors">
                    Cancel Session
                </button>
            </div>
        @endif
    </div>

    <!-- Dual Timezone Comparison Card -->
    @php
        $cairoStart = \Carbon\CarbonImmutable::instance($booking->start_at_utc)->setTimezone($businessTz);
        $cairoEnd = \Carbon\CarbonImmutable::instance($booking->end_at_utc)->setTimezone($businessTz);
        $studentStart = \Carbon\CarbonImmutable::instance($booking->start_at_utc)->setTimezone($booking->customer_timezone);
        $studentEnd = \Carbon\CarbonImmutable::instance($booking->end_at_utc)->setTimezone($booking->customer_timezone);
    @endphp

    <div class="bg-gradient-to-r from-slate-900 to-slate-950 text-white rounded-3xl p-6 sm:p-8 shadow-xl border border-slate-800">
        <div class="text-xs font-semibold uppercase tracking-wider text-amber-400 mb-4">Dual Timezone Authoritative Comparison</div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Cairo Tutor Local Time -->
            <div class="bg-slate-800/60 rounded-2xl p-5 border border-slate-700/60">
                <div class="flex items-center justify-between text-xs font-bold text-amber-400 uppercase tracking-wider mb-2">
                    <span>Tutor Time (Cairo)</span>
                    <span>{{ $booking->business_utc_offset_at_booking ?? '+03:00' }}</span>
                </div>
                <div class="text-2xl font-bold font-serif text-white">{{ $cairoStart->format('l, F j, Y') }}</div>
                <div class="text-lg font-semibold text-amber-300 mt-1">
                    {{ $cairoStart->format('h:i A') }} &ndash; {{ $cairoEnd->format('h:i A') }}
                </div>
                <div class="text-xs text-slate-400 mt-2">
                    Canonical UTC Instant: {{ $booking->start_at_utc->format('Y-m-d H:i:s \U\T\C') }}
                </div>
            </div>

            <!-- Student Local Time -->
            <div class="bg-slate-800/60 rounded-2xl p-5 border border-slate-700/60">
                <div class="flex items-center justify-between text-xs font-bold text-amber-400 uppercase tracking-wider mb-2">
                    <span>Student Local Time</span>
                    <span>{{ $booking->customer_utc_offset_at_booking ?? '+00:00' }}</span>
                </div>
                <div class="text-2xl font-bold font-serif text-white">{{ $studentStart->format('l, F j, Y') }}</div>
                <div class="text-lg font-semibold text-amber-300 mt-1">
                    {{ $studentStart->format('h:i A') }} &ndash; {{ $studentEnd->format('h:i A') }}
                </div>
                <div class="text-xs text-slate-400 mt-2">
                    Student Timezone: {{ $booking->customer_timezone }}
                </div>
            </div>
        </div>
    </div>

    <!-- Student & Session Details Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Student Information Card -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
            <h2 class="text-base font-bold font-serif text-slate-900 flex items-center justify-between">
                <span>Student Details</span>
                <a href="{{ route('admin.contacts.show', $booking->contact_id) }}" class="text-xs font-semibold text-amber-600 hover:text-amber-700">
                    Full Profile &rarr;
                </a>
            </h2>

            <div class="space-y-3 text-sm">
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Name</span>
                    <span class="font-bold text-slate-900">{{ $booking->contact->name ?? 'Student' }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Email</span>
                    <span class="text-slate-700 font-mono text-xs">{{ $booking->contact->email }}</span>
                </div>
                @if($booking->contact->phone)
                    <div>
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">WhatsApp / Phone</span>
                        <span class="text-slate-900 font-mono text-xs">{{ $booking->contact->phone }}</span>
                    </div>
                @endif
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Confirmation Token</span>
                    <span class="text-slate-600 font-mono text-xs select-all">{{ $booking->confirmation_token }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Self-Service Link</span>
                    <a href="{{ route('booking.confirmation', $booking->confirmation_token) }}" target="_blank" class="text-xs text-amber-700 hover:underline">
                        Open Student Confirmation Page &rarr;
                    </a>
                </div>
            </div>
        </div>

        <!-- Tutor Private Session Notes -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div>
                <h2 class="text-base font-bold font-serif text-slate-900 mb-2">Tutor Session Notes & Homework</h2>
                <p class="text-xs text-slate-500 mb-4">Private curriculum notes, vocabulary taught, student strengths, and recommended exercises.</p>

                <form action="{{ route('admin.bookings.notes', $booking->id) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <textarea name="notes" rows="6" 
                              class="w-full p-4 text-sm bg-slate-50 border border-slate-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all placeholder-slate-400"
                              placeholder="Add lesson notes, topics covered (e.g. Masri greetings, taxi directions, numbers 1-20)...">{{ old('notes', $booking->notes) }}</textarea>

                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors font-serif">
                            Save Notes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking Lifecycle Timeline (Spec 31 & 84) -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
        <h2 class="text-base font-bold font-serif text-slate-900 mb-4">Booking Lifecycle & Audit Timeline</h2>
        
        <div class="space-y-4">
            @forelse($booking->events as $event)
                <div class="flex items-start gap-4 text-sm">
                    <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-700 flex items-center justify-center font-bold text-xs shrink-0 mt-0.5">
                        {{ substr($event->event_type, 0, 1) }}
                    </div>
                    <div class="flex-1 pb-4 border-b border-slate-100 last:border-0">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 capitalize">{{ str_replace('_', ' ', $event->event_type) }}</span>
                            <span class="text-xs text-slate-400">{{ $event->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Performed by: <span class="font-semibold text-slate-700 capitalize">{{ $event->performed_by }}</span>
                        </p>
                        @if($event->new_data)
                            <div class="mt-1 text-[11px] font-mono text-slate-600 bg-slate-50 p-2 rounded-lg">
                                {{ json_encode($event->new_data) }}
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="text-xs text-slate-400 py-4 text-center">
                    Initial booking creation event recorded.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div x-show="rescheduleModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" 
         style="display: none;">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-200" @click.away="rescheduleModalOpen = false">
            <h3 class="text-lg font-bold font-serif text-slate-900 mb-1">Reschedule Tutoring Session</h3>
            <p class="text-xs text-slate-500 mb-6">Enter the new appointment date and time in Cairo Time ({{ $businessTz }}).</p>

            <form action="{{ route('admin.bookings.reschedule', $booking->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">New Date (YYYY-MM-DD)</label>
                    <input type="date" name="new_date" required min="{{ date('Y-m-d') }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">New Start Time (HH:MM)</label>
                    <input type="time" name="new_time" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Reason for Reschedule</label>
                    <input type="text" name="reason" placeholder="e.g. Student requested evening slot"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="rescheduleModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs">
                        Confirm Reschedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Cancellation Modal -->
    <div x-show="cancelModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" 
         style="display: none;">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-200" @click.away="cancelModalOpen = false">
            <h3 class="text-lg font-bold font-serif text-slate-900 mb-1">Cancel Tutoring Session</h3>
            <p class="text-xs text-slate-500 mb-6">Please provide a reason for the cancellation. Non-destructive event will be logged.</p>

            <form action="{{ route('admin.bookings.cancel', $booking->id) }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Cancellation Reason</label>
                    <textarea name="cancellation_reason" rows="3" required
                              class="w-full p-3 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-red-500 focus:outline-none"
                              placeholder="Reason for cancellation..."></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="cancelModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Go Back
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-xl text-xs font-bold shadow-xs">
                        Confirm Cancellation
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

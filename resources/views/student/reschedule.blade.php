@extends('layouts.student', ['title' => 'Reschedule your session'])
@section('content')
<div class="mx-auto max-w-4xl">
    <a href="{{ route('student.dashboard') }}" class="text-sm font-semibold text-nile-800 underline">Back to sessions</a>
    <h1 class="mt-5 text-3xl font-semibold text-nile-900">Reschedule your session</h1>
    <section aria-label="Current session" class="mt-5 rounded-2xl border border-stone-200 bg-white p-5">
        <h2 class="font-semibold">Current session · {{ $booking->sessionType?->title }}</h2>
        <p class="mt-2">{{ $booking->start_at_utc->copy()->setTimezone($timezone)->format('D, M j, Y · g:i A') }} – {{ $booking->end_at_utc->copy()->setTimezone($timezone)->format('g:i A') }}</p>
        <p class="mt-1 text-sm text-stone-500">{{ $timezone }} · Your existing entitlement stays attached.</p>
    </section>
    <x-student-timezone :timezone="$timezone" />
    <p class="text-sm text-stone-600">Choose a date and available time, review the change, then confirm. Your tutor will reconfirm the meeting link.</p>
    @if($errors->any())<div role="alert" class="mt-5 rounded-xl bg-rose-50 p-4 text-rose-700">{{ $errors->first() }}</div>@endif
    <div class="mt-7" x-data="{ selectedDate: @js(array_key_first($slots)), selectedSlot: '', selectedLabel: '', reviewing: false }" @date-selected="selectedSlot = ''; selectedLabel = ''; reviewing = false">
        <div x-show="!reviewing" class="grid gap-6 lg:grid-cols-2">
            <x-booking-calendar :month="$fromDate->format('Y-m')" :timezone="$timezone" :slots="$slots"
                :previous-url="route('student.bookings.reschedule', ['booking' => $booking->id, 'timezone' => $timezone, 'date' => $fromDate->subMonth()->startOfMonth()->format('Y-m-d')])"
                :next-url="route('student.bookings.reschedule', ['booking' => $booking->id, 'timezone' => $timezone, 'date' => $fromDate->addMonth()->startOfMonth()->format('Y-m-d')])" />
            <section aria-label="Available times" class="rounded-2xl border border-stone-200 bg-white p-5">
                <h2 class="font-semibold">Choose an available time</h2>
                @forelse($slots as $date => $dailySlots)
                    <div x-show="selectedDate === @js($date)" class="mt-4 space-y-3">
                        <h3 class="text-sm font-semibold">{{ \Carbon\CarbonImmutable::parse($date)->format('l, M j, Y') }}</h3>
                        @foreach($dailySlots as $slot)
                            <button type="button" @click="selectedSlot = @js($slot['slot_id']); selectedLabel = @js(\Carbon\CarbonImmutable::parse($slot['slot_start_utc'], 'UTC')->setTimezone($timezone)->format('D, M j, Y · g:i A').' – '.\Carbon\CarbonImmutable::parse($slot['slot_end_utc'], 'UTC')->setTimezone($timezone)->format('g:i A'))" :aria-pressed="(selectedSlot === @js($slot['slot_id'])).toString()" :class="selectedSlot === @js($slot['slot_id']) ? 'border-terracotta-500 bg-terracotta-50' : 'border-stone-200'" class="w-full rounded-xl border p-3.5 text-left transition hover:border-terracotta-500 hover:bg-terracotta-50">
                                <x-booking-slot :appointment="$slot" :timezone="$timezone" />
                            </button>
                        @endforeach
                    </div>
                @empty
                    <p class="mt-4 text-sm text-stone-500">No available slots in this month. Try the next month.</p>
                @endforelse
                <button type="button" :disabled="!selectedSlot" @click="reviewing = true" class="mt-5 min-h-11 w-full rounded-xl bg-nile-800 px-5 py-3 font-semibold text-white disabled:opacity-40">Review change</button>
            </section>
        </div>
        <section x-show="reviewing" x-cloak aria-label="Review session change" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-7">
            <h2 class="text-xl font-semibold">Review your change</h2>
            <dl class="mt-5 space-y-4">
                <div><dt class="text-sm text-stone-500">Old time</dt><dd>{{ $booking->start_at_utc->copy()->setTimezone($timezone)->format('D, M j, Y · g:i A') }} – {{ $booking->end_at_utc->copy()->setTimezone($timezone)->format('g:i A') }}</dd></div>
                <div><dt class="text-sm text-stone-500">New time</dt><dd x-text="selectedLabel"></dd></div>
                <div><dt class="text-sm text-stone-500">Selected timezone</dt><dd>{{ $timezone }}</dd></div>
            </dl>
            <p class="mt-5 text-sm text-stone-600">This change uses the same entitlement type and units. No additional credit is spent.</p>
            <form method="POST" action="{{ route('student.bookings.reschedule.submit', $booking->id) }}" class="mt-5 flex flex-wrap gap-3">
                @csrf
                <input type="hidden" name="timezone" value="{{ $timezone }}"><input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}"><input type="hidden" name="slot_id" :value="selectedSlot">
                <button type="button" @click="reviewing = false" class="min-h-11 rounded-xl border border-stone-300 px-5 py-3 font-semibold">Choose another time</button>
                <button :disabled="!selectedSlot" class="min-h-11 rounded-xl bg-nile-800 px-5 py-3 font-semibold text-white">Confirm reschedule</button>
            </form>
        </section>
    </div>
</div>
@endsection
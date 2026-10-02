@extends('layouts.student', ['title' => 'Reschedule session'])

@section('content')
<div class="max-w-3xl">
    <a href="{{ route('student.dashboard') }}" class="text-sm font-medium text-nile-800 underline">← Your sessions</a>
    <x-student-timezone :timezone="$timezone" />
    <h1 class="mt-5 text-3xl font-semibold tracking-tight text-nile-900">Choose a new time</h1>
    <p class="mt-2 text-stone-600">Times shown in {{ $timezone }}. Changes require your tutor's reconfirmation.</p>
    @if($errors->any())<p role="alert" class="mt-5 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</p>@endif
    <form method="GET" class="mt-6 flex flex-wrap items-end gap-3">
        <input type="hidden" name="timezone" value="{{ $timezone }}"><div><label for="date" class="block text-sm font-medium">Show dates from</label><input id="date" name="date" type="date" value="{{ $fromDate->toDateString() }}" class="mt-1 rounded-lg border border-stone-300 px-3 py-2"></div>
        <button type="submit" class="rounded-lg border border-nile-800 px-4 py-2 font-semibold text-nile-800">Show available times</button>
    </form>
    <div class="mt-7 grid gap-6 lg:grid-cols-2" x-data="{ selectedDate: @js(array_key_first($slots) ?? $fromDate->toDateString()) }">
    <x-booking-calendar :month="$fromDate->format('Y-m')" :timezone="$timezone" :slots="$slots" :previous-url="request()->fullUrlWithQuery(['date' => $fromDate->subMonthNoOverflow()->startOfMonth()->toDateString()])" :next-url="request()->fullUrlWithQuery(['date' => $fromDate->addMonthNoOverflow()->startOfMonth()->toDateString()])" />
    <form method="POST" action="{{ route('student.bookings.reschedule.submit', $booking->id) }}" class="mt-8">
        @csrf
        <input type="hidden" name="timezone" value="{{ $timezone }}">
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        @forelse($slots as $date => $dailySlots)
            <fieldset x-show="selectedDate === '{{ $date }}'" class="mt-6 rounded-xl border border-stone-200 bg-white p-5">
                <legend class="px-1 font-semibold">{{ \Carbon\CarbonImmutable::parse($date, $timezone)->format('D, M j, Y') }}</legend>
                <div class="flex flex-wrap gap-3">
                    @foreach($dailySlots as $slot)
                        <label class="cursor-pointer rounded-lg border border-stone-300 px-4 py-2 text-sm has-[:checked]:border-nile-800 has-[:checked]:bg-nile-50"><input type="radio" name="slot_id" value="{{ $slot['id'] }}" required class="mr-2">{{ $slot['label'] }}</label>
                    @endforeach
                </div>
            </fieldset>
        @empty
            <p class="rounded-xl border border-stone-200 bg-white p-5 text-stone-600">No slots in this date range. Try another date.</p>
        @endforelse
        @if($slots)<button type="submit" class="mt-7 rounded-lg bg-nile-900 px-6 py-3 font-semibold text-white">Request reschedule</button>@endif
    </form>
    </div>
</div>
@endsection

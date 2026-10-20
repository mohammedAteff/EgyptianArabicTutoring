@extends('layouts.student', ['title' => 'Book a Lesson'])
@section('content')
<div class="mx-auto max-w-4xl">
<a href="{{ route('student.dashboard') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-nile-800">← Back to Sessions</a>
<x-student-timezone :timezone="$timezone" :student-view="true" />
<h1 class="mt-3 text-3xl font-semibold tracking-tight text-nile-900">Book Your Next Lesson</h1>
<p class="mt-2 text-stone-600">Choose a lesson type, date and time. The required lesson credit is used automatically from your earliest-expiring compatible package.</p>
<p class="mt-2 text-sm text-stone-500">Different credit types can't be exchanged. A time is only booked when you confirm it.</p>
@if($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<section class="mt-4 rounded-xl border border-stone-200 bg-white p-5" aria-labelledby="available-credits"><h2 id="available-credits" class="font-semibold">Available Lesson Credits</h2>
@forelse($entitlementBalances as $balance)<p class="mt-2 text-sm">{{ $balance['label'] }}: <strong>{{ $balance['available'] }}</strong> available</p>@empty<p class="mt-2 text-sm text-stone-500">Your lesson credits will appear here when a package is connected to your profile.</p>@endforelse
</section>
@if($sessionTypes->isEmpty())
    <p role="status" class="mt-4 rounded-xl bg-amber-50 p-5 text-sm text-amber-900">Lesson booking is unavailable right now. Contact Abdallah about arranging your next lesson.</p>
@elseif(!$hasFundableFormat)
    <p role="status" class="mt-4 rounded-xl bg-amber-50 p-5 text-sm text-amber-900">No lesson type is currently available for your eligible credits. Contact Abdallah about arranging your next lesson.</p>
@elseif($selectedSessionType && $availableCredits < 1)
    <p role="status" class="mt-4 rounded-xl bg-amber-50 p-5 text-sm text-amber-900">You don't have enough eligible credits for this lesson type. You can choose another type if you have compatible credits, or contact Abdallah about your next lesson.</p>
@endif
<form method="GET" action="{{ route('student.bookings.create') }}" class="mt-5 grid gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-3">
    <label class="text-sm font-medium">Lesson type<select name="session_type_id" required class="mt-1 block min-h-11 w-full rounded-lg border border-stone-300 px-3"><option value="" @selected(!$selectedSessionType)>Choose a lesson type</option>@foreach($sessionTypes as $type)<option value="{{ $type->id }}" @disabled(!in_array($type->id, $fundableTypeIds, true)) @selected($selectedSessionType?->id === $type->id)>{{ $type->duration_minutes }}-minute lesson · Uses {{ $type->required_entitlement_units }} matching package credit{{ $type->required_entitlement_units > 1 ? 's' : '' }}{{ in_array($type->id, $fundableTypeIds, true) ? '' : ' · No eligible credits' }}</option>@endforeach</select></label>
    <input type="hidden" name="timezone" value="{{ $timezone }}">
    <label class="text-sm font-medium">Starting date<input type="date" name="date" min="{{ now($timezone)->toDateString() }}" max="{{ now($timezone)->addDays(60)->toDateString() }}" value="{{ $fromDate->toDateString() }}" required class="mt-1 block min-h-11 w-full rounded-lg border border-stone-300 px-3"></label>
    <div class="sm:col-span-3"><button @disabled($sessionTypes->isEmpty()) class="min-h-11 rounded-lg bg-nile-800 px-4 py-2.5 text-sm font-semibold text-white enabled:hover:bg-nile-900 disabled:cursor-not-allowed disabled:opacity-50">Show Available Times</button></div>
</form>
@if($selectedSessionType && $availableCredits > 0)
<div class="mt-7" x-data="{ selectedDate: @js(array_key_first($slots) ?? $fromDate->toDateString()), selectedSlot: '', selectedLabel: '', reviewing: false, confirming: false }" @date-selected="selectedSlot = ''; selectedLabel = ''; reviewing = false">
    <div x-show="!reviewing" class="grid gap-6 lg:grid-cols-2">
        <x-booking-calendar :month="$fromDate->format('Y-m')" :timezone="$timezone" :slots="$slots" :previous-url="request()->fullUrlWithQuery(['date' => $fromDate->subMonthNoOverflow()->startOfMonth()->toDateString()])" :next-url="request()->fullUrlWithQuery(['date' => $fromDate->addMonthNoOverflow()->startOfMonth()->toDateString()])" />
        <section class="rounded-2xl border border-stone-200 bg-white p-5" aria-label="Available lesson times">
            <h2 class="text-lg font-semibold">Choose an available time</h2>
            @forelse($slots as $date => $daySlots)
            <div x-show="selectedDate === '{{ $date }}'" class="mt-4 space-y-3">
                <h3 class="font-semibold">{{ \Carbon\CarbonImmutable::parse($date, $timezone)->format('l, M j, Y') }}</h3>
                @foreach($daySlots as $slot)
                <button type="button" @click="selectedSlot = @js($slot['slot_id']); selectedLabel = @js(\Carbon\CarbonImmutable::parse($slot['slot_start_utc'], 'UTC')->setTimezone($timezone)->format('D, M j, Y · g:i A').' – '.\Carbon\CarbonImmutable::parse($slot['slot_end_utc'], 'UTC')->setTimezone($timezone)->format('D, M j · g:i A'))" :aria-pressed="selectedSlot === @js($slot['slot_id'])" :class="selectedSlot === @js($slot['slot_id']) ? 'border-terracotta-600 bg-terracotta-50' : 'border-stone-200 bg-white'" class="block w-full rounded-xl border p-3 text-left">
                    <x-booking-slot :appointment="$slot" :timezone="$timezone" />
                </button>
                @endforeach
            </div>
            @empty
            <p class="mt-4 text-sm text-stone-500">No Available Times. Choose another starting date.</p>
            @endforelse
            <p x-show="selectedDate && !@js(array_keys($slots)).includes(selectedDate)" class="mt-4 text-sm text-stone-500">No times available on this date.</p>
            <button type="button" @click="reviewing = true" :disabled="!selectedSlot" class="mt-4 min-h-11 w-full rounded-xl bg-nile-800 px-5 py-3 text-sm font-semibold text-white disabled:opacity-40">Review lesson</button>
        </section>
    </div>
    <section x-show="reviewing" x-cloak class="rounded-2xl border border-stone-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Review Your Lesson</h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div><dt class="font-semibold">Lesson</dt><dd>{{ $selectedSessionType->duration_minutes }} minutes</dd></div>
            <div><dt class="font-semibold">Your selected time</dt><dd x-text="selectedLabel"></dd></div>
            <div><dt class="font-semibold">Timezone</dt><dd>{{ $timezone }}</dd></div>
            <div><dt class="font-semibold">Package credit used</dt><dd>{{ $selectedSessionType->required_entitlement_units }} credit{{ $selectedSessionType->required_entitlement_units > 1 ? 's' : '' }} for a {{ $selectedSessionType->duration_minutes }}-minute lesson</dd></div>
        </dl>
        <p class="mt-4 text-sm text-stone-600">Your existing package covers this lesson. No additional payment is charged. Availability and credits are checked again when you confirm.</p>
        <form method="POST" action="{{ route('student.bookings.store') }}" class="mt-5 flex flex-wrap gap-3" @submit="if (confirming) $event.preventDefault(); else confirming = true">
            @csrf
            <input type="hidden" name="session_type_id" value="{{ $selectedSessionType->id }}">
            <input type="hidden" name="timezone" value="{{ $timezone }}">
            <input type="hidden" name="slot_id" :value="selectedSlot">
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
            <button type="button" @click="reviewing = false" class="min-h-11 rounded-xl border border-stone-300 px-5 py-3 text-sm font-semibold">Choose another time</button>
            <button :disabled="confirming || !selectedSlot" class="min-h-11 rounded-xl bg-terracotta-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-40" x-text="confirming ? 'Confirming…' : 'Confirm Lesson'">Confirm Lesson</button>
        </form>
    </section>
</div>
@endif
</div>
@endsection

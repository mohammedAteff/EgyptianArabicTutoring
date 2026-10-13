@extends('layouts.student', ['title' => 'Book a Lesson'])
@section('content')
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
    <label class="text-sm font-medium">Lesson type<select name="session_type_id" required class="mt-1 block min-h-11 w-full rounded-lg border border-stone-300 px-3"><option value="" @selected(!$selectedSessionType)>Choose a lesson type</option>@foreach($sessionTypes as $type)<option value="{{ $type->id }}" @selected($selectedSessionType?->id === $type->id)>{{ $type->title }} · {{ $type->duration_minutes }} min · {{ $type->required_entitlement_units }} {{ $type->requiredEntitlementType?->label }}</option>@endforeach</select></label>
    <input type="hidden" name="timezone" value="{{ $timezone }}">
    <label class="text-sm font-medium">Starting date<input type="date" name="date" min="{{ now($timezone)->toDateString() }}" max="{{ now($timezone)->addDays(60)->toDateString() }}" value="{{ $fromDate->toDateString() }}" required class="mt-1 block min-h-11 w-full rounded-lg border border-stone-300 px-3"></label>
    <div class="sm:col-span-3"><button @disabled($sessionTypes->isEmpty()) class="min-h-11 rounded-lg bg-nile-800 px-4 py-2.5 text-sm font-semibold text-white enabled:hover:bg-nile-900 disabled:cursor-not-allowed disabled:opacity-50">Show Available Times</button></div>
</form>
@if($selectedSessionType && $availableCredits > 0)
    <div class="mt-7 grid gap-6 lg:grid-cols-2" x-data="{ selectedDate: @js(array_key_first($slots) ?? $fromDate->toDateString()) }">
        <x-booking-calendar :month="$fromDate->format('Y-m')" :timezone="$timezone" :slots="$slots" :previous-url="request()->fullUrlWithQuery(['date' => $fromDate->subMonthNoOverflow()->startOfMonth()->toDateString()])" :next-url="request()->fullUrlWithQuery(['date' => $fromDate->addMonthNoOverflow()->startOfMonth()->toDateString()])" />
        <section class="space-y-5" aria-label="Available lesson times">
        @forelse($slots as $date => $daySlots)
            <div x-show="selectedDate === '{{ $date }}'"><h2 class="font-semibold text-nile-900">{{ \Carbon\CarbonImmutable::parse($date, $timezone)->format('l, F j, Y') }}</h2><div class="mt-3 grid gap-3 sm:grid-cols-2">
                @foreach($daySlots as $slot)
                <details class="rounded-xl border border-stone-200 bg-white p-4">
                    <summary class="flex min-h-11 cursor-pointer items-center font-semibold text-nile-900">{{ $slot['label'] }}</summary>
                    <h3 class="mt-3 font-semibold">Review Your Lesson</h3>
                    <p class="mt-2 text-sm"><bdi>{{ $selectedSessionType->title }}</bdi> · {{ $selectedSessionType->duration_minutes }} minutes</p>
                    <p class="mt-2 text-sm">{{ \Carbon\CarbonImmutable::parse($date, $timezone)->format('j F Y') }} · {{ $slot['label'] }} · {{ $timezone }}</p>
                    <p class="mt-2 text-sm">Credit used: {{ $selectedSessionType->required_entitlement_units }} {{ $selectedSessionType->requiredEntitlementType?->label }}</p>
                    <p class="mt-2 text-xs text-stone-500">Availability is checked again when you confirm.</p>
                    <form method="POST" action="{{ route('student.bookings.store') }}" class="mt-4" x-data="{ confirming: false }" x-on:submit="if (confirming) $event.preventDefault(); else confirming = true">
                        @csrf
                        <input type="hidden" name="session_type_id" value="{{ $selectedSessionType->id }}">
                        <input type="hidden" name="timezone" value="{{ $timezone }}">
                        <input type="hidden" name="slot_id" value="{{ $slot['slot_id'] }}">
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                        <button x-bind:disabled="confirming" class="min-h-11 w-full rounded-lg bg-terracotta-600 px-3 py-2 text-sm font-semibold text-white hover:bg-terracotta-700 disabled:opacity-50" x-text="confirming ? 'Confirming...' : 'Confirm Lesson'">Confirm Lesson</button>
                    </form>
                </details>
                @endforeach
            </div></div>
        @empty
            <div class="rounded-xl border border-stone-200 bg-white p-5"><h2 class="font-semibold">No Available Times</h2><p class="mt-2 text-sm text-stone-600">No times are available in this date range. Choose another starting date.</p></div>
        @endforelse
        </section>
    </div>
@endif
@endsection

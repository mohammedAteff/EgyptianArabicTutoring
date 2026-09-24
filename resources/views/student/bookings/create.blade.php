@extends('layouts.student', ['title' => 'Book a session'])

@section('content')
<a href="{{ route('student.dashboard') }}" class="text-sm font-medium text-nile-800 underline">← Student dashboard</a>
<h1 class="mt-3 text-3xl font-semibold tracking-tight text-nile-900">Book a session</h1>
<p class="mt-2 text-stone-600">Confirmed student bookings use one credit from your earliest-expiring eligible package.</p>
@if($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<p class="mt-4 rounded-lg bg-white px-4 py-3 text-sm text-stone-700">Available package credits: <strong>{{ $availableCredits }}</strong></p>
@if($availableCredits < 1)
    <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">There are no active session credits available. Contact your tutor to add a package. Public booking remains available for intake sessions.</p>
@endif
<form method="GET" action="{{ route('student.bookings.create') }}" class="mt-5 grid gap-4 rounded-xl border border-stone-200 bg-white p-5 sm:grid-cols-3">
    <label class="text-sm font-medium">Session type<select name="session_type_id" required class="mt-1 block w-full rounded-lg border-stone-300">@foreach($sessionTypes as $type)<option value="{{ $type->id }}" @selected($selectedSessionType?->id === $type->id)>{{ $type->title }} · {{ $type->duration_minutes }} min</option>@endforeach</select></label>
    <label class="text-sm font-medium">Your timezone<input name="timezone" value="{{ $timezone }}" list="student-timezones" required class="mt-1 block w-full rounded-lg border-stone-300"><datalist id="student-timezones"><option value="Africa/Cairo"><option value="Europe/London"><option value="Europe/Paris"><option value="America/New_York"><option value="Asia/Dubai"></datalist></label>
    <label class="text-sm font-medium">Starting date<input type="date" name="date" min="{{ now($timezone)->toDateString() }}" max="{{ now($timezone)->addDays(60)->toDateString() }}" value="{{ $fromDate->toDateString() }}" required class="mt-1 block w-full rounded-lg border-stone-300"></label>
    <div class="sm:col-span-3"><button @disabled($availableCredits < 1 || $sessionTypes->isEmpty()) class="rounded-lg bg-nile-800 px-4 py-2.5 text-sm font-semibold text-white enabled:hover:bg-nile-900 disabled:cursor-not-allowed disabled:opacity-50">Show available times</button></div>
</form>
@if($selectedSessionType && $availableCredits > 0)
    <section class="mt-7 space-y-5" aria-label="Available appointment times">
        @forelse($slots as $date => $daySlots)
            <div><h2 class="font-semibold text-nile-900">{{ \Carbon\CarbonImmutable::parse($date, $timezone)->format('l, F j, Y') }}</h2><div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($daySlots as $slot)
                    <form method="POST" action="{{ route('student.bookings.store') }}" class="rounded-xl border border-stone-200 bg-white p-4">
                        @csrf
                        <input type="hidden" name="session_type_id" value="{{ $selectedSessionType->id }}">
                        <input type="hidden" name="timezone" value="{{ $timezone }}">
                        <input type="hidden" name="slot_id" value="{{ $slot['slot_id'] }}">
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                        <p class="font-semibold">{{ $slot['label'] }} <span class="text-xs font-normal text-stone-500">{{ $timezone }}</span></p>
                        <button class="mt-3 w-full rounded-lg bg-terracotta-600 px-3 py-2 text-sm font-semibold text-white hover:bg-terracotta-700">Book with 1 credit</button>
                    </form>
                @endforeach
            </div></div>
        @empty
            <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-600">No times are available in this date range. Choose another start date.</p>
        @endforelse
    </section>
@endif
@endsection

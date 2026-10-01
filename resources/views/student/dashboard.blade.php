@extends('layouts.student', ['title' => 'Your sessions'])

@section('content')
<h1 class="text-3xl font-semibold tracking-tight text-nile-900">Your sessions</h1>
<p class="mt-2 text-stone-600">Welcome, {{ $student->first_name }}.</p>
@if(session('success'))<p role="status" class="mt-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</p>@endif
@if(session('info'))<p role="status" class="mt-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('info') }}</p>@endif
<section class="mt-8" aria-labelledby="forms-title">
    <h2 id="forms-title" class="text-xl font-semibold">Questionnaires</h2>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @forelse($forms as $form)
            @php $submission = $formSubmissions->get($form->id); @endphp
            <article class="rounded-xl border border-stone-200 bg-white p-5">
                <h3 class="font-semibold text-nile-900">{{ $form->title }}</h3>
                @if($form->description)<p class="mt-1 text-sm text-stone-600">{{ $form->description }}</p>@endif
                <p class="mt-2 text-xs text-stone-500">@if(!$submission) Not started @elseif($submission->status === 'draft') Draft @elseif((int) $submission->form_version_id !== (int) $form->published_version_id) Submitted · Update available @else Submitted @endif @if($form->is_mandatory) · Required @endif</p>
                <a href="{{ route('student.forms.show', $form->slug) }}" class="mt-3 inline-block text-sm font-semibold text-nile-800 underline">{{ $submission?->status === 'submitted' ? 'View responses' : 'Open questionnaire' }}</a>
            </article>
        @empty
            <p class="text-sm text-stone-600">No questionnaires are currently assigned.</p>
        @endforelse
    </div>
</section>
<section class="mt-8" aria-labelledby="credits-title">
    <h2 id="credits-title" class="text-xl font-semibold">Sessions and credits</h2>
    @if($availableCredits > 0)<a href="{{ route('student.bookings.create') }}" class="mt-3 inline-block rounded-lg bg-nile-800 px-4 py-2 text-sm font-semibold text-white hover:bg-nile-900">Book with a package credit</a>@endif
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Purchased sessions</p><p class="mt-1 text-2xl font-semibold text-nile-900">{{ $totalPurchased }}</p></div>
        <div class="rounded-xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Completed sessions</p><p class="mt-1 text-2xl font-semibold text-nile-900">{{ $completedCount }}</p></div>
        <div class="rounded-xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">Available credits</p><p class="mt-1 text-2xl font-semibold text-nile-900">{{ $availableCredits }}</p></div>
    </div>
    @foreach($packageSummaries as $package)
        <p class="mt-3 text-sm text-stone-600">{{ $package['name'] }} · {{ ucfirst($package['status']) }} · {{ $package['summary']['remaining_credits'] }} credits · {{ $package['summary']['net_paid'] }} {{ $package['currency'] }} paid net · {{ $package['summary']['balance_due'] }} {{ $package['currency'] }} due</p>
    @endforeach
</section>
<section class="mt-8" aria-labelledby="upcoming-title">
    <h2 id="upcoming-title" class="text-xl font-semibold">Upcoming sessions</h2>
    @forelse($upcoming as $booking)
        @php $customerStart = $booking->customer_start; @endphp
        <article class="mt-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <p class="font-semibold">{{ $customerStart->format('D, M j, Y · g:i A') }} <span class="text-sm font-normal text-stone-500">{{ $customerStart->timezoneName }}</span></p>
            <p class="mt-1 text-sm text-stone-600">{{ $booking->sessionType?->name ?? 'Private lesson' }} · {{ $booking->studentStatusLabel() }}</p>
            <p class="mt-1 text-sm text-stone-600">Tutor: {{ $tutorName }}</p>
            @if($booking->status === 'confirmed' && $meetingUrl)
                <a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block rounded-lg bg-nile-800 px-4 py-2 text-sm font-semibold text-white hover:bg-nile-900">Join lesson with {{ $tutorName }}</a>
            @elseif($booking->status === 'confirmed')
                <p class="mt-2 text-sm text-stone-600">Your tutor will share the private meeting link before the lesson.</p>
            @endif
            @if($booking->status === 'confirmed' && now('UTC')->addHours(24)->lessThanOrEqualTo($booking->start_at_utc))
                <a href="{{ route('student.bookings.reschedule', $booking->id) }}" class="mt-3 inline-block text-sm font-semibold text-nile-800 underline">Reschedule</a>
            @elseif($booking->status === 'confirmed')
                <p class="mt-3 text-sm text-stone-600">To change a session within 24 hours, contact your tutor directly.</p>
            @endif
        </article>
    @empty
        <p class="mt-4 rounded-xl border border-stone-200 bg-white p-5 text-stone-600">No upcoming sessions.</p>
    @endforelse
</section>
<section class="mt-10" aria-labelledby="history-title">
    <h2 id="history-title" class="text-xl font-semibold">Session history</h2>
    @foreach($history as $booking)
        @php $customerStart = $booking->customer_start; @endphp
        <article class="mt-4 rounded-xl border border-stone-200 bg-white p-5">
            <p class="font-semibold">{{ $customerStart->format('D, M j, Y · g:i A') }} <span class="text-sm font-normal text-stone-500">{{ $customerStart->timezoneName }}</span></p>
            <p class="mt-1 text-sm text-stone-600">{{ $booking->sessionType?->name ?? 'Private lesson' }} · {{ $booking->studentStatusLabel() }}</p>
        </article>
    @endforeach
</section>
@endsection

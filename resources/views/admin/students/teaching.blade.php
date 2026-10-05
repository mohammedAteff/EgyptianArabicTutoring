@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-5xl space-y-6">
    <a href="{{ route('admin.students.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-amber-700">&larr; Student records</a>
    <header><h1 class="font-serif text-3xl font-bold text-slate-900">Teaching workspace</h1><p class="mt-2 text-slate-600">{{ $student->name }}@if($bookingId) · Lesson #{{ $bookingId }}@endif</p>
    @if($bookingId)<a href="{{ route('admin.lessons.show', $bookingId) }}" class="mt-2 inline-block text-sm font-semibold text-amber-700 underline">Back to lesson materials</a>@endif</header>
    @if($errors->any())<div role="alert" class="rounded-xl bg-red-50 p-4 text-red-800"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">Share selected teaching records with the student. Tutor preparation stays staff-only. Educational Notes remain in their existing area.</p>
    @foreach(['homework' => 'Homework', 'plan' => 'Learning plans', 'preparation' => 'Tutor preparation · Staff only', 'resource' => 'Assigned resources', 'error' => 'Pronunciation and error log', 'tag' => 'Student and lesson tags'] as $kind => $heading)
    <section aria-label="{{ $heading }}" class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">{{ $heading }}</h2>
        <details class="rounded-2xl border border-slate-200 bg-white p-5" @if($kind === 'homework' && $records[$kind]->isEmpty()) open @endif>
            <summary class="cursor-pointer font-semibold text-amber-700">Add {{ strtolower($heading) }}</summary>
            <x-teaching-record-form :student="$student" :kind="$kind" :bookings="$bookings" :booking-id="$bookingId" :resources="$resources" :materials="$materials" :plans="$records['plan']" />
        </details>
        @foreach($records[$kind] as $record)
        <details class="rounded-2xl border border-slate-200 bg-white p-5">
            <summary class="cursor-pointer font-semibold"><span class="break-words">{{ $record->title ?? $record->label ?? $record->mistake ?? $record->resource?->title ?? 'Preparation #'.$record->id }}</span> <span class="text-xs font-normal text-slate-500">· {{ $kind === 'preparation' || !$record->student_visible ? 'Staff only' : 'Shared with student' }}@if($record->booking_id) · Lesson #{{ $record->booking_id }}@endif @if($record->status) · {{ str_replace('_', ' ', $record->status) }}@endif</span></summary>
            <x-teaching-record-form :student="$student" :kind="$kind" :record="$record" :bookings="$bookings" :booking-id="$bookingId" :resources="$resources" :materials="$materials" :plans="$records['plan']" />
            @if($kind === 'plan')
                <div class="mt-5 border-t border-slate-100 pt-4"><h3 class="font-semibold">Milestones</h3>
                    @foreach($record->milestones as $milestone)
                    <details class="mt-3 rounded-xl bg-slate-50 p-4"><summary class="cursor-pointer text-sm font-semibold">{{ $milestone->title }} · {{ str_replace('_', ' ', $milestone->status) }}</summary>
                    <x-teaching-record-form :student="$student" kind="milestone" :record="$milestone" :bookings="$bookings" :resources="$resources" :materials="$materials" :plans="$records['plan']" />
                    </details>
                    @endforeach
                    <details class="mt-3 rounded-xl bg-slate-50 p-4"><summary class="cursor-pointer text-sm font-semibold text-amber-700">Add milestone</summary><x-teaching-record-form :student="$student" kind="milestone" :bookings="$bookings" :resources="$resources" :materials="$materials" :plans="collect([$record])" /></details>
                </div>
            @endif
        </details>
        @endforeach
    </section>
    @endforeach
    <section aria-label="Private student feedback" class="space-y-3">
        <h2 class="text-xl font-bold text-slate-900">Private student feedback</h2>
        @forelse($feedback as $entry)<article class="rounded-2xl border border-slate-200 bg-white p-5"><h3 class="font-semibold">Lesson #{{ $entry->booking_id }} · {{ $entry->booking?->start_at_utc->format('M j, Y') }} · {{ $entry->rating ?? 'Redacted' }} / 5</h3>@if($entry->comment)<p class="mt-2 whitespace-pre-line break-words text-sm text-slate-600">{{ $entry->comment }}</p>@endif</article>
        @empty <p class="rounded-xl border border-slate-200 bg-white p-5 text-sm text-slate-500">No lesson feedback submitted yet.</p>@endforelse
    </section>
</div>
@endsection

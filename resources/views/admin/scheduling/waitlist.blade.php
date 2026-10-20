@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div><h1 class="font-serif text-3xl font-bold">Lesson waitlist</h1><p class="mt-2 text-sm text-slate-600">Students can request booked times from Planning → Lesson Waitlist. Matching openings are checked every 15 minutes. Confirm availability when booking; preferences reserve no time or credits.</p><p class="mt-2 text-xs text-slate-500">Open / contacted queues show active students. All includes archived and inactive history. Add a preference from a student’s Scheduling & holidays page.</p></div>
    <x-report-filters :default-labels="['status' => 'Default (open)']" :filters="['status' => $status]" :fields="['status' => ['Status', ['all' => 'All', 'open' => 'Open', 'contacted' => 'Contacted', 'closed' => 'Closed', 'withdrawn' => 'Withdrawn']]]" :action="route('admin.waitlist.index')" />
    @forelse($interests as $interest)
    <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap justify-between gap-3">
            <div><a href="{{ route('admin.students.scheduling', $interest->student_id) }}" class="font-semibold text-amber-800">{{ $interest->student?->name ?? 'Historical student' }}</a><p class="mt-1 text-sm">{{ $interest->sessionType->title }} · {{ $interest->date_from->toDateString() }} to {{ $interest->date_to->toDateString() }} · {{ $interest->timezone }}</p>
                @if($interest->preferred_time_from)<p class="mt-1 text-sm">Preferred start: {{ substr($interest->preferred_time_from, 0, 5) }}–{{ substr($interest->preferred_time_to, 0, 5) }}</p>@endif
                <p class="mt-1 text-xs text-slate-500">Joined {{ $interest->created_at->format('j M Y') }} @if($interest->date_to->endOfDay()->isPast()) · Date range ended @endif</p>
                <p class="mt-2 whitespace-pre-wrap text-sm text-slate-600">{{ $interest->notes }}</p>
            </div>
            <form method="POST" action="{{ route('admin.waitlist.update', $interest) }}" class="flex items-end gap-2">@csrf @method('PATCH')<label class="text-sm">Status<select name="status" class="mt-1 block rounded-xl border border-slate-300 bg-white p-3">@foreach(['open', 'contacted', 'closed', 'withdrawn'] as $state)<option @selected($interest->status === $state)>{{ $state }}</option>@endforeach</select></label><button class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">Save</button></form>
        </div>
        <div class="mt-3 space-y-2">
            @forelse($matches[$interest->id] as $match)
            <p class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900">Opening: {{ $match['label'] }} <a class="ml-2 font-semibold underline" href="{{ $match['admin_booking_url'] }}">Book for student →</a></p>
            @empty<p class="text-xs text-slate-500">No matching opening currently available.</p>@endforelse
        </div>
    </article>
    @empty<p class="rounded-2xl border border-dashed border-slate-300 p-8 text-sm text-slate-500">No matching waitlist interests.</p>@endforelse
    {{ $interests->links() }}
</div>
@endsection

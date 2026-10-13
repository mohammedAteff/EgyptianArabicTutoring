@extends('layouts.student', ['title' => 'Practice & Feedback'])
@section('content')
<h1 class="text-3xl font-semibold text-nile-900">Practice &amp; Feedback</h1>
<p class="mt-2 text-stone-600">Your homework, learning plan, assigned resources, feedback, and current practice focus—all in one place.</p>
<a href="{{ route('student.learning.index') }}#for-you" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">Courses & personal learning · For You</a>
@if(session('success'))<p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="mt-4 rounded-xl bg-rose-50 p-4 text-rose-800">{{ $errors->first() }}</p>@endif
<x-student-progress :progress="$progress" />
@if($teachingTags->isNotEmpty())<div class="mt-5 flex flex-wrap gap-2" aria-label="Teaching tags">@foreach($teachingTags as $tag)<span class="rounded-full bg-terracotta-50 px-3 py-1 text-sm text-terracotta-700">{{ $tag->label }}@if($tag->booking_id) · Lesson #{{ $tag->booking_id }}@endif</span>@endforeach</div>@endif
<section class="mt-8 space-y-4" aria-labelledby="homework-heading">
    <h2 id="homework-heading" class="text-xl font-semibold">Homework</h2>
    @forelse($homework as $item)
    <article id="homework-{{ $item->id }}" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="font-semibold text-nile-900">{{ $item->title }}</h3><span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold">{{ match($item->status) { 'assigned' => 'Not Started', 'in_progress' => 'In Progress', 'submitted' => 'Submitted', 'reviewed' => 'Reviewed', 'completed' => 'Completed', default => 'Status Unavailable' } }}</span></div>
        <p class="mt-2 text-xs text-stone-500">Assigned {{ $item->assigned_date->format('M j, Y') }}@if($item->due_date) · Due {{ $item->due_date->format('M j, Y') }}@endif @if($item->booking_id) · Lesson #{{ $item->booking_id }}@endif</p>
        <p class="mt-4 whitespace-pre-line break-words text-sm text-stone-700">{{ $item->instructions }}</p>
        <div class="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-nile-800">
            @if($item->url)<a href="{{ route('student.homework.link', $item->id) }}" target="_blank" rel="noopener noreferrer" class="underline">Open practice link</a>@endif
            @if($item->resource?->isPublished())<a href="{{ route('student.homework.resource', $item->id) }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $item->resource->title }}</a>@endif
            @if($item->material)<a href="{{ route('student.lessons.materials.open', [$item->material->booking_id, $item->material->id]) }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $item->material->title }}</a>@endif
        </div>
        <x-student-bookmark-button kind="homework" :target-id="$item->id" />
        @if($item->due_date && $item->due_date->isPast() && !in_array($item->status, ['submitted', 'reviewed', 'completed'], true))<p class="mt-2 text-sm font-semibold text-amber-800">Overdue</p>@endif
        @if($item->submissions->isNotEmpty())<details class="mt-4"><summary class="cursor-pointer text-sm font-semibold">Submission History</summary>@foreach($item->submissions as $submission)<article class="mt-2 rounded-xl bg-stone-50 p-3"><p class="text-xs text-stone-500">{{ $submission->submitted_at->setTimezone($timezone)->format('j M Y, g:i A') }}</p><p class="mt-1 whitespace-pre-line">{{ $submission->body }}</p></article>@endforeach</details>@endif
        @if($item->feedback)<div class="mt-4 rounded-xl bg-nile-50 p-4"><h4 class="text-sm font-semibold text-nile-900">Tutor feedback</h4><p class="mt-1 whitespace-pre-line break-words text-sm">{{ $item->feedback }}</p></div>@endif
        @if($item->status !== 'completed')
        <form method="POST" action="{{ route('student.homework.update', $item->id) }}" class="mt-5 grid gap-3">
            @csrf @method('PATCH')
            <input type="hidden" name="submission_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <label class="text-sm font-medium">Your response (optional)<textarea name="student_response" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border border-stone-300 p-3">{{ $item->student_response }}</textarea></label>
            <div class="flex flex-wrap gap-3"><button name="status" value="in_progress" class="min-h-11 rounded-xl border border-stone-300 px-4 py-2 text-sm font-semibold">Save Progress</button><button name="status" value="submitted" class="min-h-11 rounded-xl bg-nile-800 px-4 py-2 text-sm font-semibold text-white">Submit Homework</button></div>
        </form>
        @elseif($item->student_response)<p class="mt-4 whitespace-pre-line break-words text-sm"><span class="font-semibold">Your response:</span> {{ $item->student_response }}</p>@endif
    </article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">Nothing Assigned Right Now. When homework is assigned, you'll find it here.</p>@endforelse
</section>
<section class="mt-8 space-y-4" aria-labelledby="plan-heading">
    <h2 id="plan-heading" class="text-xl font-semibold">Your Learning Plan</h2>
    @forelse($learningPlans as $plan)
    <article class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
        <h3 class="font-semibold text-nile-900">{{ $plan->title }} · {{ ucfirst(str_replace('_', ' ', $plan->status)) }}</h3>
        <p class="mt-2 text-xs text-stone-500">Started {{ $plan->start_date->format('M j, Y') }} · Updated {{ $plan->updated_at->format('M j, Y') }}</p>
        <dl class="mt-4 space-y-3"><div><dt class="text-sm font-semibold">Goals</dt><dd class="whitespace-pre-line break-words text-sm text-stone-600">{{ $plan->goals }}</dd></div>
        @if($plan->focus_areas)<div><dt class="text-sm font-semibold">Focus areas</dt><dd class="whitespace-pre-line break-words text-sm text-stone-600">{{ $plan->focus_areas }}</dd></div>@endif
        @if($plan->current_level)<div><dt class="text-sm font-semibold">Current level</dt><dd class="text-sm text-stone-600">{{ $plan->current_level }}</dd></div>@endif
        @if($plan->notes)<div><dt class="text-sm font-semibold">Tutor notes</dt><dd class="whitespace-pre-line break-words text-sm text-stone-600">{{ $plan->notes }}</dd></div>@endif</dl>
        @if($plan->milestones->isNotEmpty())<ul class="mt-4 space-y-2">@foreach($plan->milestones as $milestone)<li class="rounded-xl bg-stone-50 p-3 text-sm">{{ $milestone->title }} · {{ ucfirst(str_replace('_', ' ', $milestone->status)) }}@if($milestone->completed_at) · {{ $milestone->completed_at->format('M j, Y') }}@endif</li>@endforeach</ul>@endif
    </article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">Your Learning Plan Will Appear Here. Once your learning direction has been prepared or updated, you'll be able to return to it here.</p>@endforelse
</section>
<section id="resources" class="mt-8 space-y-4" aria-labelledby="resources-heading">
    <h2 id="resources-heading" class="text-xl font-semibold">Assigned Resources</h2>
    <a href="{{ route('resources.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">Explore Public Resources</a>
    @forelse($assignedResources as $assignment)
    <article class="rounded-2xl border border-stone-200 bg-white p-5">
        <h3 class="font-semibold text-nile-900">{{ $assignment->resource->title }}</h3>
        <p class="mt-2 whitespace-pre-line break-words text-sm text-stone-600">{{ $assignment->instructions ?? $assignment->resource->short_description }}</p>
        <a href="{{ route('student.resources.open', $assignment->id) }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">Open Resource</a>
        <x-student-bookmark-button kind="resource" :target-id="$assignment->id" />
        @if(!$assignment->reviewed_at)<form method="POST" action="{{ route('student.resources.update', $assignment->id) }}" class="mt-2">@csrf @method('PATCH')<button class="min-h-11 rounded-xl bg-nile-800 px-4 py-2 text-sm font-semibold text-white">Mark as reviewed</button></form>@else<p class="mt-2 text-xs text-stone-500">Reviewed {{ $assignment->reviewed_at->format('M j, Y') }}</p>@endif
    </article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">No Assigned Resources Yet. Resources chosen for your learning will appear here.</p>@endforelse
</section>
<section class="mt-8 space-y-4" aria-labelledby="practice-heading">
    <h2 id="practice-heading" class="text-xl font-semibold">Practice Focus</h2>
    @forelse($errorLog as $entry)<article class="rounded-2xl border border-stone-200 bg-white p-5"><h3 class="font-semibold">{{ ucfirst($entry->category) }} · {{ match($entry->status) { 'active' => 'Working On It', 'improved' => 'Improving', 'resolved' => 'Completed', default => 'Status Unavailable' } }}</h3><p class="mt-2 break-words text-sm"><span class="font-semibold">Practise:</span> {{ $entry->mistake }}</p><p class="mt-2 whitespace-pre-line break-words text-sm"><span class="font-semibold">Correction:</span> {{ $entry->correction }}</p>@if($entry->notes)<p class="mt-2 whitespace-pre-line break-words text-sm text-stone-600">{{ $entry->notes }}</p>@endif</article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">Nothing to Review Yet. Useful practice areas will appear here with guidance for what to work on.</p>@endforelse
</section>
<section class="mt-8 space-y-4" aria-labelledby="feedback-heading">
    <h2 id="feedback-heading" class="text-xl font-semibold">Feedback</h2>
    <p class="text-sm text-stone-600">Comments and guidance connected to your submitted work.</p>
    @forelse($homework->filter(fn($item) => filled($item->feedback)) as $item)
        <article class="rounded-2xl border border-stone-200 bg-white p-5"><h3 dir="auto" class="font-semibold">{{ $item->title }}</h3><p dir="auto" class="mt-3 whitespace-pre-line break-words text-sm">{{ $item->feedback }}</p><a href="#homework-{{ $item->id }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">View Homework</a></article>
    @empty<p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">No Feedback Yet. Feedback will appear here when your submitted work has been reviewed.</p>@endforelse
    <a href="{{ route('student.learning.index') }}#for-you" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">View Learning Activities and Feedback</a>
</section>
<section class="mt-8 space-y-4"><h2 class="text-xl font-semibold">Learning Collections</h2>@forelse($resourceCollections as $assignment)<article class="rounded-xl border border-stone-200 bg-white p-5"><h3 class="font-semibold">{{ $assignment->collection->title }}</h3><p class="mt-2">{{ $assignment->collection->description }}</p><ol class="mt-3 list-inside list-decimal">@foreach($assignment->collection->resources as $resource)<li>{{ $resource->title }}@php $assigned = $assignedResources->firstWhere('resource_id', $resource->id); @endphp @if($assigned)<a href="{{ route('student.resources.open', $assigned->id) }}" class="ml-2 text-nile-800 underline">Open Resource</a>@endif</li>@endforeach</ol></article>@empty<p class="text-sm text-stone-500">No learning collections assigned yet.</p>@endforelse</section>
<section class="mt-8 space-y-4"><h2 class="text-xl font-semibold">Skill Assessments</h2><p class="text-sm text-stone-500">Tutor observations shared with you.</p>@forelse($skillAssessments as $assessment)<article class="rounded-xl border border-stone-200 bg-white p-5"><h3 class="font-semibold">{{ $assessment->skill->label }} · {{ ucfirst(str_replace('_', ' ', $assessment->state)) }}</h3><p class="mt-2 whitespace-pre-line">{{ $assessment->notes }}</p><p class="mt-2 text-xs text-stone-500">{{ $assessment->assessed_at->setTimezone($timezone)->format('j M Y') }}</p></article>@empty<p class="text-sm text-stone-500">No skill assessments shared yet.</p>@endforelse</section>
<section class="mt-8"><h2 class="text-xl font-semibold">Progress Reports</h2>@forelse($progressReports as $report)<a href="{{ route('student.progress-reports.show', $report) }}" class="mt-3 block min-h-11 text-nile-800 underline">{{ $report->date_from->format('j M Y') }} – {{ $report->date_to->format('j M Y') }}</a>@empty<p class="mt-3 text-sm text-stone-500">No progress reports shared yet.</p>@endforelse</section>
@endsection

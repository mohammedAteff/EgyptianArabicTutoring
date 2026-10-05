@extends('layouts.student', ['title' => 'My learning'])
@section('content')
<h1 class="text-3xl font-semibold text-nile-900">My learning</h1>
<p class="mt-2 text-stone-600">Your tutor’s goals, homework and practice resources.</p>
@if(session('success'))<p role="status" class="mt-4 rounded-xl bg-green-50 p-4 text-green-800">{{ session('success') }}</p>@endif
@if($errors->any())<p role="alert" class="mt-4 rounded-xl bg-rose-50 p-4 text-rose-800">{{ $errors->first() }}</p>@endif
<x-student-progress :progress="$progress" />
@if($teachingTags->isNotEmpty())<div class="mt-5 flex flex-wrap gap-2" aria-label="Teaching tags">@foreach($teachingTags as $tag)<span class="rounded-full bg-terracotta-50 px-3 py-1 text-sm text-terracotta-700">{{ $tag->label }}@if($tag->booking_id) · Lesson #{{ $tag->booking_id }}@endif</span>@endforeach</div>@endif
<section class="mt-8 space-y-4" aria-labelledby="homework-heading">
    <h2 id="homework-heading" class="text-xl font-semibold">Homework</h2>
    @forelse($homework as $item)
    <article id="homework-{{ $item->id }}" class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3"><h3 class="font-semibold text-nile-900">{{ $item->title }}</h3><span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold">{{ str_replace('_', ' ', $item->status) }}</span></div>
        <p class="mt-2 text-xs text-stone-500">Assigned {{ $item->assigned_date->format('M j, Y') }}@if($item->due_date) · Due {{ $item->due_date->format('M j, Y') }}@endif @if($item->booking_id) · Lesson #{{ $item->booking_id }}@endif</p>
        <p class="mt-4 whitespace-pre-line break-words text-sm text-stone-700">{{ $item->instructions }}</p>
        <div class="mt-3 flex flex-wrap gap-4 text-sm font-semibold text-nile-800">
            @if($item->url)<a href="{{ route('student.homework.link', $item->id) }}" target="_blank" rel="noopener noreferrer" class="underline">Open practice link</a>@endif
            @if($item->resource?->isPublished())<a href="{{ route('student.homework.resource', $item->id) }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $item->resource->title }}</a>@endif
            @if($item->material)<a href="{{ route('student.lessons.materials.open', [$item->material->booking_id, $item->material->id]) }}" target="_blank" rel="noopener noreferrer" class="underline">{{ $item->material->title }}</a>@endif
        </div>
        @if($item->feedback)<div class="mt-4 rounded-xl bg-nile-50 p-4"><h4 class="text-sm font-semibold text-nile-900">Tutor feedback</h4><p class="mt-1 whitespace-pre-line break-words text-sm">{{ $item->feedback }}</p></div>@endif
        @if($item->status !== 'completed')
        <form method="POST" action="{{ route('student.homework.update', $item->id) }}" class="mt-5 grid gap-3">
            @csrf @method('PATCH')
            <label class="text-sm font-medium">Your response (optional)<textarea name="student_response" rows="3" maxlength="5000" class="mt-2 w-full rounded-xl border border-stone-300 p-3">{{ $item->student_response }}</textarea></label>
            <div class="flex flex-wrap gap-3"><button name="status" value="in_progress" class="min-h-11 rounded-xl border border-stone-300 px-4 py-2 text-sm font-semibold">Save progress</button><button name="status" value="submitted" class="min-h-11 rounded-xl bg-nile-800 px-4 py-2 text-sm font-semibold text-white">Submit homework</button></div>
        </form>
        @elseif($item->student_response)<p class="mt-4 whitespace-pre-line break-words text-sm"><span class="font-semibold">Your response:</span> {{ $item->student_response }}</p>@endif
    </article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">No homework has been shared yet.</p>@endforelse
</section>
<section class="mt-8 space-y-4" aria-labelledby="plan-heading">
    <h2 id="plan-heading" class="text-xl font-semibold">Learning plan</h2>
    @forelse($learningPlans as $plan)
    <article class="rounded-2xl border border-stone-200 bg-white p-5 sm:p-6">
        <h3 class="font-semibold text-nile-900">{{ $plan->title }} · {{ $plan->status }}</h3>
        <p class="mt-2 text-xs text-stone-500">Started {{ $plan->start_date->format('M j, Y') }} · Updated {{ $plan->updated_at->format('M j, Y') }}</p>
        <dl class="mt-4 space-y-3"><div><dt class="text-sm font-semibold">Goals</dt><dd class="whitespace-pre-line break-words text-sm text-stone-600">{{ $plan->goals }}</dd></div>
        @if($plan->focus_areas)<div><dt class="text-sm font-semibold">Focus areas</dt><dd class="whitespace-pre-line break-words text-sm text-stone-600">{{ $plan->focus_areas }}</dd></div>@endif
        @if($plan->current_level)<div><dt class="text-sm font-semibold">Current level</dt><dd class="text-sm text-stone-600">{{ $plan->current_level }}</dd></div>@endif
        @if($plan->notes)<div><dt class="text-sm font-semibold">Tutor notes</dt><dd class="whitespace-pre-line break-words text-sm text-stone-600">{{ $plan->notes }}</dd></div>@endif</dl>
        @if($plan->milestones->isNotEmpty())<ul class="mt-4 space-y-2">@foreach($plan->milestones as $milestone)<li class="rounded-xl bg-stone-50 p-3 text-sm">{{ $milestone->title }} · {{ str_replace('_', ' ', $milestone->status) }}@if($milestone->completed_at) · {{ $milestone->completed_at->format('M j, Y') }}@endif</li>@endforeach</ul>@endif
    </article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">Your tutor will share your learning plan here.</p>@endforelse
</section>
<section id="resources" class="mt-8 space-y-4" aria-labelledby="resources-heading">
    <h2 id="resources-heading" class="text-xl font-semibold">Assigned resources</h2>
    @forelse($assignedResources as $assignment)
    <article class="rounded-2xl border border-stone-200 bg-white p-5">
        <h3 class="font-semibold text-nile-900">{{ $assignment->resource->title }}</h3>
        <p class="mt-2 whitespace-pre-line break-words text-sm text-stone-600">{{ $assignment->instructions ?? $assignment->resource->short_description }}</p>
        <a href="{{ route('student.resources.open', $assignment->id) }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">Open resource</a>
        @if(!$assignment->reviewed_at)<form method="POST" action="{{ route('student.resources.update', $assignment->id) }}" class="mt-2">@csrf @method('PATCH')<button class="min-h-11 rounded-xl bg-nile-800 px-4 py-2 text-sm font-semibold text-white">Mark as reviewed</button></form>@else<p class="mt-2 text-xs text-stone-500">Reviewed {{ $assignment->reviewed_at->format('M j, Y') }}</p>@endif
    </article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">No resources have been assigned yet.</p>@endforelse
</section>
<section class="mt-8 space-y-4" aria-labelledby="practice-heading">
    <h2 id="practice-heading" class="text-xl font-semibold">Practice patterns</h2>
    @forelse($errorLog as $entry)<article class="rounded-2xl border border-stone-200 bg-white p-5"><h3 class="font-semibold">{{ ucfirst($entry->category) }} · {{ $entry->status }}</h3><p class="mt-2 break-words text-sm"><span class="font-semibold">Practise:</span> {{ $entry->mistake }}</p><p class="mt-2 whitespace-pre-line break-words text-sm"><span class="font-semibold">Correction:</span> {{ $entry->correction }}</p>@if($entry->notes)<p class="mt-2 whitespace-pre-line break-words text-sm text-stone-600">{{ $entry->notes }}</p>@endif</article>
    @empty <p class="rounded-xl border border-stone-200 bg-white p-5 text-sm text-stone-500">Your tutor will share practice patterns here.</p>@endforelse
</section>
@endsection
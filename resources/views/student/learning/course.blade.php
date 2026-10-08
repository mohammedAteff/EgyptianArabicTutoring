@extends('layouts.student', ['title' => $course->title])
@section('content')
<div class="space-y-8">
    <a href="{{ route('student.learning.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">← My Learning</a>
    <header class="space-y-4 rounded-3xl bg-nile-900 p-6 text-white sm:p-9">
        <p class="text-xs font-semibold uppercase tracking-widest text-nile-100">{{ $course->kind === 'private' ? 'Your personal learning' : 'Course overview' }}</p>
        <h1 dir="auto" class="break-words text-3xl font-bold tracking-tight sm:text-4xl">{{ $course->title }}</h1>
        <p class="text-sm text-nile-100">{{ $lessonList->count() }} available {{ $lessonList->count() === 1 ? 'lesson' : 'lessons' }} · Learn at your own pace</p>
        <p class="text-sm font-semibold text-nile-100">{{ $progress['status'] }} · {{ $progress['percent'] }}% complete</p>
        @if($progress['next'])<a href="{{ route('student.learning.lessons.show', [$course, $progress['next']]) }}" class="inline-flex min-h-11 items-center rounded-xl bg-white px-5 text-sm font-semibold text-nile-900">Continue learning →</a>@endif
    </header>
    @include('student.learning.feedback')
    @if(isset($learningAssignment) && $learningAssignment->instructions)<section class="space-y-2 rounded-2xl border border-nile-100 bg-nile-50 p-5"><h2 class="font-semibold text-nile-900">For You</h2><p dir="auto" class="whitespace-pre-wrap break-words text-sm leading-relaxed">{{ $learningAssignment->instructions }}</p></section>@endif
    <div class="grid items-start gap-6 lg:grid-cols-[18rem_1fr]">
        @include('student.learning.curriculum')
        <section class="space-y-4 rounded-2xl border border-stone-200 bg-white p-6">
            <h2 class="text-xl font-semibold">A space to learn and practise</h2><p class="text-sm leading-7 text-stone-600">Choose any available lesson from the curriculum. Keep personal notes as you go, and save useful moments in videos that support bookmarks.</p>
            <p class="text-sm leading-7 text-stone-600">@if($accessEndsAt)Current access available until <strong>{{ $accessEndsAt->timezone($timezone)->format('j M Y, H:i T') }}</strong>.@else Current access has no scheduled end.@endif</p>
            <p class="text-xs text-stone-500">Only learning currently available to you is shown.</p>
        </section>
    </div>
</div>
@endsection

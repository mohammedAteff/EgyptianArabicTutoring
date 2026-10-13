@extends('layouts.student', ['title' => 'My Learning'])
@section('content')
<div class="space-y-10">
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-2"><p class="text-xs font-semibold uppercase tracking-widest text-nile-700">Your learning space</p><h1 class="text-3xl font-bold tracking-tight sm:text-4xl">My Learning</h1><p class="text-stone-600">Pick up where you left off, revisit something useful, or explore learning prepared especially for you.</p></div>
        <div class="flex flex-wrap gap-3">
            <a href="{{ route('student.teaching.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-stone-300 bg-white px-4 text-sm font-semibold text-nile-900">Practice &amp; Feedback</a>
            <a href="{{ route('student.learning.notes.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-stone-300 bg-white px-4 text-sm font-semibold text-nile-900">Private Lesson Notes · {{ $privateNoteCount }}</a>
            <a href="{{ route('student.video.devices') }}" class="inline-flex min-h-11 items-center rounded-xl border border-stone-300 bg-white px-4 text-sm font-semibold text-nile-900">Authorized Browsers</a>
        </div>
    </header>
    @include('student.learning.feedback')
    <nav aria-label="Learning sections" class="flex flex-wrap gap-2">
        @foreach(['continue-learning' => 'Continue Learning', 'my-courses' => 'My Courses', 'for-you' => 'For You', 'completed-learning' => 'Completed'] as $anchor => $label)
            <a href="#{{ $anchor }}" class="inline-flex min-h-11 items-center rounded-full border border-stone-200 bg-white px-4 text-sm font-semibold text-nile-800 hover:bg-nile-50">{{ $label }}</a>
        @endforeach
    </nav>
    <section aria-labelledby="continue-learning" class="space-y-4">
        <h2 id="continue-learning" class="text-xl font-semibold">Continue Learning</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @forelse($continueLearning as $item)
                <a href="{{ $item['url'] }}" class="rounded-2xl border border-nile-100 bg-nile-50 p-5 transition hover:border-nile-600">
                    <p class="text-xs font-semibold uppercase tracking-wide text-nile-700">Pick up where you left off</p>
                    <h3 dir="auto" class="mt-2 break-words text-lg font-semibold text-nile-900">{{ $item['course']->title }}</h3>
                    @if($item['lesson'])<p dir="auto" class="mt-1 break-words text-sm text-nile-800">{{ $item['lesson']->title }}</p>@endif
                    <p class="mt-3 text-xs text-stone-600">Last opened {{ $item['accessed_at']->copy()->timezone($timezone)->format('j M Y, g:i A T') }}</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-nile-900">Continue →</span>
                </a>
            @empty
                <p class="rounded-2xl border border-dashed border-stone-300 p-6 text-sm text-stone-600 sm:col-span-2">Nothing to Continue Yet. Once you begin a course or learning activity, you'll be able to return to it quickly from here.</p>
            @endforelse
        </div>
    </section>
    <section aria-labelledby="my-courses" class="space-y-4">
        <h2 id="my-courses" class="text-xl font-semibold">My Courses <span class="text-sm font-normal text-stone-500">({{ $cards->count() }})</span></h2>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($cards as $card)
                <article class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                    <div aria-hidden="true" class="flex h-28 items-center justify-between bg-nile-900 px-6 text-nile-100"><span dir="rtl" class="text-4xl">عربي</span><span class="text-xs uppercase tracking-widest">Learn & practice</span></div>
                    <div class="flex flex-1 flex-col gap-4 p-5">
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-stone-500">{{ $card['course']->kind === 'private' ? 'Personal learning' : 'Course' }}</p><h3 dir="auto" class="mt-2 break-words text-lg font-semibold">{{ $card['course']->title }}</h3></div>
                        <p class="text-xs leading-relaxed text-stone-500">@if($accessEndings[$card['course']->id])Current access available until {{ $accessEndings[$card['course']->id]->timezone($timezone)->format('j M Y, g:i A T') }}@else Current access has no scheduled end.@endif</p>
                        <p class="text-sm font-semibold text-nile-800">{{ $card['progress']['status'] }} · {{ $card['progress']['percent'] }}% complete</p>
                        <a href="{{ $card['url'] }}" class="mt-auto inline-flex min-h-11 items-center justify-center rounded-xl bg-nile-800 px-4 text-sm font-semibold text-white hover:bg-nile-900">{{ $card['progress']['status'] === 'Completed' ? 'Review course' : ($card['visited'] ? 'Continue' : 'Start learning') }}</a>
                    </div>
                </article>
            @empty
                <p class="rounded-2xl border border-dashed border-stone-300 p-6 text-sm text-stone-600 sm:col-span-2 lg:col-span-3">No Courses Yet. You don't have any courses available right now. When a course becomes available to you, you'll find it here.</p>
            @endforelse
        </div>
    </section>
    <section aria-labelledby="for-you" class="space-y-4">
        <h2 id="for-you" class="text-xl font-semibold">For You</h2>
        @foreach(['New','In Progress','Completed','Expired','Scheduled','Unavailable'] as $group)
        @php($groupItems=$forYou->where('group',$group))
        @if($groupItems->isNotEmpty())<div class="space-y-3"><h3 class="text-sm font-semibold text-stone-600">{{ $group }} <span class="font-normal">({{ $groupItems->count() }})</span></h3>
        @foreach($groupItems as $item)
            <article class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-5">
                <div class="min-w-0 flex-1"><p class="text-xs font-semibold uppercase tracking-wide text-nile-700">{{ ucfirst($item['kind']) }}@if($item['booking_id']) · Session #{{ $item['booking_id'] }}@endif</p><h3 dir="auto" class="mt-2 break-words font-semibold">{{ $item['title'] }}</h3><p class="mt-2 text-sm text-stone-600">{{ $item['assessment']??$item['state'] }}</p>@if($item['instructions'])<p dir="auto" class="mt-2 whitespace-pre-wrap break-words text-sm text-stone-600">{{ $item['instructions'] }}</p>@endif<p class="mt-2 text-xs text-stone-500">@if($item['group']==='Scheduled')Available {{ $item['starts_at']->copy()->timezone($timezone)->format('j M Y, g:i A T') }} · @endif @if($item['expires_at'])Access ends {{ $item['expires_at']->copy()->timezone($timezone)->format('j M Y, g:i A T') }}@else No scheduled expiry @endif</p>@if($item['reason'])<p class="mt-2 text-xs text-stone-500">{{ $item['reason'] }}</p>@endif</div>
                @if($item['url'])<a href="{{ $item['url'] }}" class="inline-flex min-h-11 items-center rounded-xl border border-nile-100 px-4 text-sm font-semibold text-nile-900">{{ $group==='Completed'?'Review learning':'Open learning' }} →</a>@endif
            </article>
        @endforeach</div>@endif
        @endforeach
        @if($forYou->isEmpty())
            <p class="rounded-2xl border border-dashed border-stone-300 p-6 text-sm text-stone-600">Nothing Here Yet. Personal learning selected for you will appear here as we continue working together.</p>
        @endif
        @if($assignedResources->isNotEmpty())<a href="{{ route('student.teaching.index') }}#resources" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">Assigned Resources · {{ $assignedResources->count() }}</a>@endif
    </section>
    <section aria-labelledby="completed-learning" class="space-y-3 rounded-2xl bg-stone-100 p-6">
        <h2 id="completed-learning" class="text-xl font-semibold">Completed</h2>
        @forelse($completedCards as $card)<a href="{{ $card['url'] }}" dir="auto" class="block min-h-11 rounded-xl bg-white p-4 text-sm font-semibold text-nile-800">{{ $card['course']->title }} · 100%</a>@empty<p class="text-sm text-stone-600">Nothing Completed Yet. Completed learning will appear here as you progress.</p>@endforelse
    </section>
    @include('components.learning-timeline',['timelineId'=>'learning-timeline','timelineItems'=>$learningTimeline,'timelineTimezone'=>$timezone,'timelineStudentView'=>true])
    <p class="text-xs text-stone-500">Times shown in {{ $timezone }}.</p>
</div>
@endsection

@extends('layouts.student', ['title' => 'My Learning'])
@section('content')
<div class="student-learning-hub">
    <header>
        <div class="space-y-2"><p class="text-xs font-semibold uppercase tracking-widest text-nile-700">{{ __('Your learning space') }}</p><h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ __('My Learning') }}</h1><p class="text-stone-600">{{ __('Pick up where you left off, revisit something useful, or explore learning prepared especially for you.') }}</p></div>
    </header>
    <nav aria-label="{{ __('Learning sections') }}" class="student-learning-sections">
        @foreach(['continue-learning' => 'Continue Learning', 'my-courses' => 'My Courses', 'for-you' => 'For You', 'completed-learning' => 'Completed'] as $anchor => $label)
            <a href="#{{ $anchor }}">{{ __($label) }}</a>
        @endforeach
    </nav>
    <details class="student-learning-tools">
        <summary>{{ __('Learning tools') }}</summary>
        <div>
            <a href="{{ route('student.teaching.index') }}" class="student-button student-button-secondary">{{ __('Practice & Feedback') }}</a>
            <a href="{{ route('student.learning.notes.index') }}" class="student-button student-button-secondary">{{ __('Private Lesson Notes ·') }} {{ $privateNoteCount }}</a>
            <a href="{{ route('student.video.devices') }}" class="student-button student-button-secondary">{{ __('Authorized Browsers') }}</a>
        </div>
    </details>
    @include('student.learning.feedback')
    <section aria-labelledby="continue-learning" class="space-y-4">
        <h2 id="continue-learning" class="text-xl font-semibold">{{ __('Continue Learning') }}</h2>
        <div class="grid gap-4 sm:grid-cols-2">
            @forelse($continueLearning as $item)
                <a href="{{ $item['url'] }}" class="rounded-2xl border border-nile-100 bg-nile-50 p-5 transition hover:border-nile-600">
                    <p class="text-xs font-semibold uppercase tracking-wide text-nile-700">{{ __('Pick up where you left off') }}</p>
                    <h3 dir="auto" class="mt-2 break-words text-lg font-semibold text-nile-900">{{ $item['course']->title }}</h3>
                    @if($item['lesson'])<p dir="auto" class="mt-1 break-words text-sm text-nile-800">{{ $item['lesson']->title }}</p>@endif
                    <p class="mt-3 text-xs text-stone-600">{{ __('Last opened') }} {{ localized_date($item['accessed_at']->copy()->timezone($timezone), 'j M Y, g:i A T') }}</p>
                    <span class="mt-4 inline-block text-sm font-semibold text-nile-900">{{ __('Continue →') }}</span>
                </a>
            @empty
                <p class="student-surface student-surface--white text-sm text-stone-600 sm:col-span-2">{{ __('Nothing to Continue Yet. Once you begin a course or learning activity, you\'ll be able to return to it quickly from here.') }}</p>
            @endforelse
        </div>
    </section>
    <section aria-labelledby="my-courses" class="space-y-4">
        <h2 id="my-courses" class="text-xl font-semibold">{{ __('My Courses') }} <span class="text-sm font-normal text-stone-500">({{ $cards->count() }})</span></h2>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($cards as $card)
                <article class="flex flex-col overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-sm">
                    <div aria-hidden="true" class="flex h-28 items-center justify-between bg-nile-900 px-6 text-nile-100"><span dir="rtl" class="text-4xl">عربي</span><span class="text-xs uppercase tracking-widest">{{ __('Learn & practice') }}</span></div>
                    <div class="flex flex-1 flex-col gap-4 p-5">
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-stone-500">{{ __($card['course']->kind === 'private' ? 'Personal learning' : 'Course') }}</p><h3 dir="auto" class="mt-2 break-words text-lg font-semibold">{{ $card['course']->title }}</h3></div>
                        <p class="text-xs leading-relaxed text-stone-500">@if($accessEndings[$card['course']->id]){{ __('Current access available until') }} {{ localized_date($accessEndings[$card['course']->id]->timezone($timezone), 'j M Y, g:i A T') }}@else {{ __('Current access has no scheduled end.') }}@endif</p>
                        <p class="text-sm font-semibold text-nile-800">{{ __($card['progress']['status']) }} · {{ $card['progress']['percent'] }}{{ __('% complete') }}</p>
                        <a href="{{ $card['url'] }}" class="mt-auto inline-flex min-h-11 items-center justify-center rounded-xl bg-nile-800 px-4 text-sm font-semibold text-white hover:bg-nile-900">{{ __($card['progress']['status'] === 'Completed' ? 'Review course' : ($card['visited'] ? 'Continue' : 'Start learning')) }}</a>
                    </div>
                </article>
            @empty
                <p class="student-surface student-surface--white text-sm text-stone-600 sm:col-span-2 lg:col-span-3">{{ __('No Courses Yet. You don\'t have any courses available right now. When a course becomes available to you, you\'ll find it here.') }}</p>
            @endforelse
        </div>
    </section>
    <section aria-labelledby="for-you" class="space-y-4">
        <h2 id="for-you" class="text-xl font-semibold">{{ __('For You') }}</h2>
        @foreach(['New','In Progress','Completed','Expired','Scheduled','Unavailable'] as $group)
        @php($groupItems=$forYou->where('group',$group))
        @if($groupItems->isNotEmpty())<div class="space-y-3"><h3 class="text-sm font-semibold text-stone-600">{{ __($group) }} <span class="font-normal">({{ $groupItems->count() }})</span></h3>
        @foreach($groupItems as $item)
            <article class="flex flex-wrap items-center justify-between gap-4 student-surface student-surface--white">
                <div class="min-w-0 flex-1"><p class="text-xs font-semibold uppercase tracking-wide text-nile-700">{{ __(ucfirst($item['kind'])) }}@if($item['booking_id']) {{ __('· Session #') }}{{ $item['booking_id'] }}@endif</p><h3 dir="auto" class="mt-2 break-words font-semibold">{{ $item['title'] }}</h3><p class="mt-2 text-sm text-stone-600">{{ __($item['assessment']??$item['state']) }}</p>@if($item['instructions'])<p dir="auto" class="mt-2 whitespace-pre-wrap break-words text-sm text-stone-600">{{ $item['instructions'] }}</p>@endif<p class="mt-2 text-xs text-stone-500">@if($item['group']==='Scheduled'){{ __('Available') }} {{ localized_date($item['starts_at']->copy()->timezone($timezone), 'j M Y, g:i A T') }} · @endif @if($item['expires_at']){{ __('Access ends') }} {{ localized_date($item['expires_at']->copy()->timezone($timezone), 'j M Y, g:i A T') }}@else {{ __('No scheduled expiry') }} @endif</p>@if($item['reason'])<p class="mt-2 text-xs text-stone-500">{{ __($item['reason']) }}</p>@endif</div>
                @if($item['url'])<a href="{{ $item['url'] }}" class="inline-flex min-h-11 items-center rounded-xl border border-nile-100 px-4 text-sm font-semibold text-nile-900">{{ __($group==='Completed'?'Review learning':'Open learning') }} →</a>@endif
            </article>
        @endforeach</div>@endif
        @endforeach
        @if($forYou->isEmpty())
            <p class="student-surface student-surface--white text-sm text-stone-600">{{ __('Nothing Here Yet. Personal learning selected for you will appear here as we continue working together.') }}</p>
        @endif
        @if($assignedResources->isNotEmpty())<a href="{{ route('student.teaching.index') }}#resources" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">{{ __('Assigned Resources ·') }} {{ $assignedResources->count() }}</a>@endif
    </section>
    <section aria-labelledby="completed-learning" class="student-surface student-surface--white space-y-3">
        <h2 id="completed-learning" class="text-xl font-semibold">{{ __('Completed') }}</h2>
        @forelse($completedCards as $card)<a href="{{ $card['url'] }}" dir="auto" class="flex min-h-11 items-center py-2 text-sm font-semibold text-nile-800">{{ $card['course']->title }} · 100%</a>@empty<p class="text-sm text-stone-600">{{ __('Nothing Completed Yet. Completed learning will appear here as you progress.') }}</p>@endforelse
    </section>
    @include('components.learning-timeline',['timelineId'=>'learning-timeline','timelineItems'=>$learningTimeline,'timelineTimezone'=>$timezone,'timelineStudentView'=>true])
    <p class="text-xs text-stone-500">{{ __('Times shown in') }} {{ $timezone }}.</p>
</div>
@endsection

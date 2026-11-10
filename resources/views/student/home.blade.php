@extends('layouts.student', ['title' => 'Home'])

@section('content')
<div class="student-home">
    <header>
        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Your learning, at a glance') }}</p>
        <h1 class="student-home-welcome mt-2">@if(trim($student->first_name ?? '') !== ''){{ __('Welcome back,') }} <bdi>{{ trim($student->first_name) }}</bdi>.@else {{ __('Welcome back.') }}@endif</h1>
    </header>
    <div @class(['student-home-leading', 'student-home-leading--single' => !$nextLesson])>
        <section class="student-home-action" aria-labelledby="next-action-title" data-home-action="{{ $nextAction['kind'] }}">
            <p class="text-sm font-semibold text-nile-700">{{ __('Your next step') }}</p>
            <h2 id="next-action-title" class="mt-2 font-semibold">{{ __($nextAction['title']) }}</h2>
            <p dir="auto" class="mt-3 text-sm text-stone-600">{{ $nextAction['detail'] }}</p>
            <a href="{{ $nextAction['url'] }}" class="student-button student-button-primary inline-flex min-h-11 items-center justify-center rounded-xl bg-nile-800 px-5 py-3 text-sm font-semibold text-white" data-home-primary>{{ __($nextAction['button']) }}</a>
        </section>
        @if($nextLesson)
        @php $localStart = $nextLesson->start_at_utc->copy()->setTimezone($timezone); @endphp
        <section class="student-surface" aria-labelledby="next-lesson-title">
            <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Next confirmed lesson') }}</p>
            <h2 id="next-lesson-title" dir="auto" class="mt-2 text-xl font-semibold">{{ __($nextLesson->sessionType?->title ?? 'Private lesson') }}</h2>
            <p class="mt-3 font-semibold">{{ localized_date($localStart, 'D, j M Y') }}<br>{{ localized_date($localStart, 'g:i A') }} – {{ localized_date($nextLesson->end_at_utc->copy()->setTimezone($timezone), 'g:i A') }}</p>
            <p class="mt-1 text-sm text-stone-600">{{ $timezone }} · {{ (int) $nextLesson->start_at_utc->diffInMinutes($nextLesson->end_at_utc) }} {{ __('minutes') }}</p>
            <p class="mt-2 text-sm font-semibold">{{ __('Confirmed') }} @if($nextLesson->start_at_utc->lt(now('UTC'))) {{ __('· In progress') }} @endif</p>
            <a href="{{ route('student.bookings.show', $nextLesson) }}" class="student-text-link mt-2">{{ __('View Session') }}</a>
        </section>
        @endif
    </div>
    @if($learningUnavailable)
    <section class="student-surface" role="status"><h2 class="font-semibold">{{ __('Learning is temporarily unavailable') }}</h2><p class="mt-2 text-sm text-stone-600">{{ __('Your learning summary couldn’t be loaded. You can try your learning area again.') }}</p><a href="{{ route('student.learning.index') }}" class="student-text-link mt-2">{{ __('Open My Learning') }}</a></section>
    @endif
    @if($featuredLearning)
    <section class="student-surface" aria-labelledby="home-learning-title">
        <p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Your learning') }}</p>
        <h2 id="home-learning-title" dir="auto" class="mt-2 text-xl font-semibold">{{ $featuredLearning['title'] }}</h2>
        <p dir="auto" class="mt-2 text-sm text-stone-600">{{ $featuredLearning['detail'] }} · {{ $featuredLearning['state'] }}</p>
        <a href="{{ $featuredLearning['url'] }}" class="student-text-link mt-2">{{ __($featuredLearning['button']) }}</a>
    </section>
    @endif
    @if($practice->isNotEmpty())
    <section aria-labelledby="home-practice-title">
        <h2 id="home-practice-title" class="text-xl font-semibold">{{ __('Practice & Feedback') }}</h2>
        <div class="student-home-secondary mt-3">@foreach($practice as $item)
            <article class="student-surface"><p class="text-sm text-stone-600">{{ __($item['state']) }}</p><h3 dir="auto" class="mt-1 font-semibold">{{ $item['title'] }}</h3><a href="{{ $item['url'] }}" class="student-text-link mt-2">{{ __($item['button']) }}</a></article>
        @endforeach</div>
    </section>
    @endif
    @if(count($credits))
    <section class="student-surface" aria-labelledby="home-credits-title">
        <h2 id="home-credits-title" class="text-xl font-semibold">{{ __('Your Lesson Credits') }}</h2>
        <dl class="student-home-secondary mt-3">@foreach($credits as $balance)<div><dt class="font-semibold">{{ __($balance['label']) }}</dt><dd class="text-sm text-stone-600">{{ $balance['remaining'] }} {{ __('remaining ·') }} {{ $balance['available'] }} {{ __('currently available') }}</dd></div>@endforeach</dl>
        @if($formats->isNotEmpty())<p class="mt-3 text-sm">{{ __('Compatible formats:') }} {{ $formats->pluck('title')->map(fn ($title) => __($title))->join(', ') }}{{ __('. Appointment times are subject to availability.') }}</p>@else<p class="mt-3 text-sm text-stone-600">{{ __('No compatible lesson format is currently bookable with these credits.') }}</p>@endif
        <a href="{{ route('student.dashboard') }}#credits-title" class="student-text-link mt-2">{{ __('Review Credits & Validity') }}</a>
    </section>
    @endif
    @if(array_sum($progress) > 0)<div class="student-home-progress"><x-student-progress :progress="$progress" variant="card" /></div>@endif
    @if($forms->isNotEmpty())
    <details id="questionnaires" class="student-surface">
        <summary class="cursor-pointer font-semibold">{{ __('Questionnaires') }} <span class="font-normal text-sm">· {{ __(':count to review', ['count' => localized_number($forms->where('attention', true)->count())]) }}</span></summary>
        <div class="mt-4 space-y-4">@foreach($forms as $item)
            <article><h2 dir="auto" class="font-semibold">{{ __($item['form']->title) }}</h2><p class="mt-1 text-sm text-stone-600">{{ __($item['state']) }} @if($item['form']->is_mandatory) {{ __('· Required') }} @endif</p>
                <a href="{{ $item['url'] }}" class="student-text-link">{{ __($item['attention'] ? $item['button'] : 'View Answers') }}</a>
                @if($item['attention'] && $item['submission']?->status === 'submitted')<a href="{{ $item['url'] }}" class="student-text-link ml-3">{{ __('View Answers') }}</a>@endif
            </article>
        @endforeach</div>
    </details>
    @else<span id="questionnaires" aria-hidden="true"></span>@endif
    @if($updates->isNotEmpty())
    <section aria-labelledby="home-updates-title"><h2 id="home-updates-title" class="text-xl font-semibold">{{ __('Recent Updates') }}</h2><ul class="mt-3 space-y-2">@foreach($updates as $update)<li><a href="{{ $update['url'] }}" class="student-text-link">{{ __($update['title']) }}</a><span class="block text-xs text-stone-500">{{ localized_date($update['created_at']->copy()->setTimezone($timezone), 'j M Y, g:i A') }}</span></li>@endforeach</ul><a href="{{ route('student.notifications.index') }}" class="student-text-link mt-2">{{ __('All Notifications') }}</a></section>
    @endif
</div>
@endsection

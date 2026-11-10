@extends('layouts.student', ['title' => __('feedback.title')])
@section('content')
<div class="student-feedback min-w-0 space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0"><h1 class="text-3xl font-semibold">{{ __('feedback.title') }}</h1><p class="mt-3 max-w-2xl text-sm leading-7 text-slate-600">{{ __('feedback.intro') }}</p></div>
        <a href="{{ route('student.feedback.create') }}" class="student-button student-button-primary">{{ __('feedback.new') }}</a>
    </header>
    @include('components.feedback-list', ['admin' => false, 'baseRoute' => 'student.feedback'])
</div>
@endsection

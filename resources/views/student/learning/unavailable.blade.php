@extends('layouts.student', ['title' => 'Learning unavailable'])
@section('content')
<div class="mx-auto max-w-xl space-y-5 rounded-3xl border border-stone-200 bg-white p-8">
    <p class="text-xs font-semibold uppercase tracking-widest text-stone-500">{{ __('Learning availability') }}</p><h1 class="text-2xl font-bold">{{ __('This Learning Isn\'t Available Right Now') }}</h1><p class="text-sm leading-7 text-stone-600">{{ __($message) }}@if($at) <strong>{{ localized_date($at->timezone($timezone), 'j M Y, g:i A T') }}</strong>.@endif</p><a href="{{ route('student.learning.index') }}" class="inline-flex min-h-11 items-center rounded-xl bg-nile-800 px-5 text-sm font-semibold text-white">{{ __('Return to My Learning') }}</a>
</div>
@endsection

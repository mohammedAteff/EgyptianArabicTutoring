@extends('layouts.student', ['title' => __('feedback.new')])
@section('content')
<div class="student-feedback min-w-0 space-y-6">
    <a href="{{ route('student.feedback.index') }}" class="student-text-link">{{ __('feedback.back') }}</a>
    <h1 class="text-3xl font-semibold">{{ __('feedback.new') }}</h1>
    <p class="text-sm leading-7 text-slate-600">{{ __('feedback.intro') }}</p>
    @php($inputClass = 'mt-2 block min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm')
    <form action="{{ route('student.feedback.store') }}" method="POST" novalidate class="feedback-composer min-w-0 space-y-5 rounded-2xl border border-slate-200 bg-white p-4 sm:p-6" data-feedback-form data-feedback-locale="{{ app()->getLocale() }}" data-session-url="{{ route('student.feedback.session') }}" data-actor="student:{{ $actor->id }}">
        @include('components.feedback-form-state', ['baseRoute' => 'student.feedback', 'historyUrl' => route('student.feedback.index'), 'showDraftNotice' => false])
        <div class="grid min-w-0 gap-5 sm:grid-cols-2">
            @foreach(['type' => \App\Domains\Students\Models\FeedbackConversation::TYPES, 'area' => \App\Domains\Students\Models\FeedbackConversation::AREAS] as $field => $options)
                <label class="min-w-0 text-sm font-semibold">{{ __('feedback.'.$field) }}<select name="{{ $field }}" required class="{{ $inputClass }}"><option value="">{{ __('feedback.select') }}</option>@foreach($options as $option)<option value="{{ $option }}">{{ __('feedback.'.($field === 'type' ? 'types.' : 'areas.').$option) }}</option>@endforeach</select><span data-error-for="{{ $field }}" class="mt-2 block text-sm font-normal text-red-700" role="alert"></span></label>
            @endforeach
        </div>
        <label class="block text-sm font-semibold">{{ __('feedback.subject') }}<input name="subject" dir="auto" required maxlength="160" class="{{ $inputClass }}"><span data-error-for="subject" class="mt-2 block text-sm font-normal text-red-700" role="alert"></span></label>
        <label class="block text-sm font-semibold">{{ __('feedback.message') }}<textarea name="body" dir="auto" required maxlength="5000" rows="7" class="{{ $inputClass }}"></textarea><span data-error-for="body" class="mt-2 block text-sm font-normal text-red-700" role="alert"></span></label>
        <fieldset class="min-w-0 space-y-3 rounded-xl border border-slate-200 p-4" data-feedback-context data-url="{{ route('student.feedback.contexts') }}">
            <legend class="px-2 text-sm font-semibold">{{ __('feedback.context') }}</legend>
            <label class="block text-sm">{{ __('feedback.type') }}<select name="context_type" class="{{ $inputClass }}"><option value="">{{ __('feedback.no_context') }}</option>@foreach(['course','lesson','homework','booking'] as $kind)<option value="{{ $kind }}" @selected(($context['type'] ?? '') === $kind)>{{ __('feedback.'.$kind) }}</option>@endforeach</select></label>
            <label class="block text-sm">{{ __('feedback.search') }}<input data-context-search maxlength="100" class="{{ $inputClass }}"></label>
            <button type="button" data-context-find class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">{{ __('feedback.find') }}</button>
            <label class="block text-sm">{{ __('feedback.choose') }}<select name="context_id" class="{{ $inputClass }}"><option value="">{{ __('feedback.no_context') }}</option>@if($context)<option selected value="{{ $context['id'] }}">{{ $context['label'] }}</option>@endif</select><span data-error-for="context_id" class="mt-2 block text-red-700" role="alert"></span></label>
            <p data-context-status role="status" class="text-sm text-slate-600"></p>
        </fieldset>
        <button class="student-button student-button-primary" data-feedback-submit>{{ __('feedback.send') }}</button>
    </form>
</div>
@endsection

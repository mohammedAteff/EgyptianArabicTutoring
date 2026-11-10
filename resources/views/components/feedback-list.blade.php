@php($inputClass = 'mt-2 block min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm')
<form method="GET" action="{{ route($baseRoute.'.index') }}" class="feedback-filters aq-filters grid min-w-0 gap-4 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2">
    <label class="min-w-0 text-sm font-semibold">{{ __('feedback.status') }}<select name="status" class="{{ $inputClass }}"><option value="">{{ __('feedback.all') }}</option>@foreach(\App\Domains\Students\Models\FeedbackConversation::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('feedback.'.$status) }}</option>@endforeach</select></label>
    @if($admin)
        @foreach(['type' => \App\Domains\Students\Models\FeedbackConversation::TYPES, 'area' => \App\Domains\Students\Models\FeedbackConversation::AREAS] as $field => $options)
            <label class="min-w-0 text-sm font-semibold">{{ __('feedback.'.$field) }}<select name="{{ $field }}" class="{{ $inputClass }}"><option value="">{{ __('feedback.all') }}</option>@foreach($options as $option)<option value="{{ $option }}" @selected(($filters[$field] ?? '') === $option)>{{ __('feedback.'.($field === 'type' ? 'types.' : 'areas.').$option) }}</option>@endforeach</select></label>
        @endforeach
        <label class="min-w-0 text-sm font-semibold">{{ __('feedback.student_search') }}<input name="student_search" maxlength="100" value="{{ $filters['student_search'] ?? '' }}" class="{{ $inputClass }}"></label>
        <label class="min-w-0 text-sm font-semibold">{{ __('feedback.student_filter') }}<select name="student_id" class="{{ $inputClass }}"><option value="">{{ __('feedback.all') }}</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected((int) ($filters['student_id'] ?? 0) === $student->id)>{{ $student->name }}</option>@endforeach</select><span class="mt-2 block text-xs font-normal text-slate-500">{{ __('feedback.student_options') }}</span></label>
        <label class="min-w-0 text-sm font-semibold">{{ __('feedback.date_from') }}<input name="date_from" type="date" value="{{ $filters['date_from'] ?? '' }}" class="{{ $inputClass }}"></label>
        <label class="min-w-0 text-sm font-semibold">{{ __('feedback.date_to') }}<input name="date_to" type="date" value="{{ $filters['date_to'] ?? '' }}" class="{{ $inputClass }}"></label>
        <label class="min-w-0 text-sm font-semibold">{{ __('feedback.read_filter') }}<select name="read" class="{{ $inputClass }}"><option value="">{{ __('feedback.all') }}</option>@foreach(['unread','read'] as $state)<option value="{{ $state }}" @selected(($filters['read'] ?? '') === $state)>{{ __('feedback.'.$state) }}</option>@endforeach</select></label>
    @endif
    <div class="feedback-filter-actions flex flex-wrap items-end gap-3 sm:col-span-2"><button class="{{ $admin ? 'min-h-11 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white' : 'student-button student-button-primary' }}">{{ __('feedback.apply') }}</button><a href="{{ route($baseRoute.'.index') }}" class="{{ $admin ? 'inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-4 text-sm font-semibold' : 'student-button student-button-secondary' }}">{{ __('feedback.clear') }}</a></div>
    @if($errors->any())<div role="alert" class="text-sm text-red-700 sm:col-span-2">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
</form>
<div class="space-y-4">
@forelse($conversations as $item)
    <article class="feedback-list-item min-w-0 rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1">@if($admin)<p class="mb-2 text-sm text-slate-600" dir="auto">{{ $item->student->name }}</p>@endif<a href="{{ route($baseRoute.'.show', $item->id) }}" dir="auto" class="block break-words text-lg font-semibold underline decoration-slate-300 underline-offset-4 [overflow-wrap:anywhere]">{{ $item->subject }}</a></div>
            @if($item->has_unread)<span class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold">{{ __('feedback.unread') }}</span>@endif
        </div>
        <dl class="mt-4 grid min-w-0 gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs text-slate-500">{{ __('feedback.status') }}</dt><dd>{{ __('feedback.'.$item->status) }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('feedback.type') }} · {{ __('feedback.area') }}</dt><dd>{{ __('feedback.types.'.$item->type) }} · {{ __('feedback.areas.'.$item->area) }}</dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('feedback.created_at') }}</dt><dd><time datetime="{{ $item->created_at->toIso8601String() }}">{{ localized_date($item->created_at->copy()->setTimezone($timezone), 'j M Y, H:i T') }}</time></dd></div>
            <div><dt class="text-xs text-slate-500">{{ __('feedback.activity') }}</dt><dd><time datetime="{{ $item->last_activity_at->toIso8601String() }}">{{ localized_date($item->last_activity_at->setTimezone($timezone), 'j M Y, H:i T') }}</time></dd></div>
        </dl>
    </article>
@empty
    <p class="feedback-empty rounded-2xl border border-dashed border-slate-300 p-6 text-sm">@unless($admin)<x-student-portal-icon name="practice" />@endunless<span>{{ $hasAny ? __('feedback.no_matches') : __($admin ? 'feedback.admin_empty' : 'feedback.empty') }}</span></p>
@endforelse
</div>
<div class="mt-6">{{ $conversations->links() }}</div>

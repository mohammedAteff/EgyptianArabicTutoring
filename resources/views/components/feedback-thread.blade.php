@php
    $inputClass = 'mt-2 block min-h-11 w-full min-w-0 rounded-xl border border-slate-300 bg-white px-3 py-3 text-sm';
    $historyUrl = route($baseRoute.'.show', $conversation->id);
    $actorKey = ($admin ? 'administrator:' : 'student:').$actor->id;
    $studentFirstName = trim($conversation->student->first_name ?? '');
@endphp
<div class="min-w-0 space-y-6" data-feedback-thread>
    <a href="{{ route($baseRoute.'.index') }}" class="feedback-back inline-flex min-h-11 items-center text-sm font-semibold underline">{{ __('feedback.back') }}</a>
    @if(session('feedback_success'))<p role="status" class="feedback-notice rounded-xl border border-slate-300 bg-white p-4">@unless($admin)<x-student-portal-icon name="check" />@endunless<span>{{ __(session('feedback_success')) }}</span></p>@endif
    <header class="feedback-thread-header min-w-0 space-y-3">
        @if($admin)<p class="text-sm" dir="auto">{{ $conversation->student->name }}</p>@endif
        <h1 dir="auto" class="break-words text-3xl font-semibold [overflow-wrap:anywhere]">{{ $conversation->subject }}</h1>
        <p class="feedback-classification text-sm">{{ __('feedback.types.'.$conversation->type) }} · {{ __('feedback.areas.'.$conversation->area) }}</p>
        <p class="feedback-thread-status text-sm font-semibold" data-feedback-state="{{ $conversation->status }}">{{ __('feedback.status') }}: {{ __('feedback.'.$conversation->status) }}</p>
        @if($conversation->context_type)<p class="break-words text-sm">@if($context)<a href="{{ $context['url'] }}" class="underline" dir="auto">{{ $context['label'] }}</a>@else{{ __('feedback.context_unavailable') }}@endif</p>@endif
    </header>
    <section class="feedback-history min-w-0 space-y-4" aria-labelledby="feedback-history">
        <h2 id="feedback-history" class="text-lg font-semibold">{{ __('feedback.history') }}</h2>
        @if($latest > 1)<p class="text-xs text-slate-500">{{ __('feedback.segment', ['page' => $page, 'total' => $latest]) }}</p>@endif
        @foreach($events as $event)
            <article class="feedback-event feedback-event--{{ $event->kind }} min-w-0 rounded-2xl border border-slate-200 bg-white p-4 sm:p-5" data-feedback-author="{{ $event->actor_type }}">
                <div class="feedback-event-meta mb-3 flex flex-wrap justify-between gap-2 text-xs text-slate-500"><span class="feedback-event-author font-semibold" dir="auto">{{ $event->actor_type === 'student' ? ($studentFirstName !== '' ? $studentFirstName : __('feedback.student')) : 'Abdallah' }}</span><time datetime="{{ $event->created_at->toIso8601String() }}">{{ localized_date($event->created_at->setTimezone($timezone), 'j M Y, H:i:s T') }}</time></div>
                @if($event->kind === 'message')<p dir="auto" class="whitespace-pre-wrap break-words text-sm leading-7 [overflow-wrap:anywhere]">{{ $event->body }}</p>
                @else<p class="text-sm">{{ __('feedback.transition', ['from' => __('feedback.'.$event->from_status), 'to' => __('feedback.'.$event->to_status)]) }}</p>@endif
                @if($admin && $event->kind === 'message' && $event->actor_type === 'administrator')<p class="mt-3 text-xs font-semibold" data-student-read="{{ $event->sequence <= $studentReadSequence ? 'read' : 'unread' }}">{{ __($event->sequence <= $studentReadSequence ? 'feedback.read_by_student' : 'feedback.not_read_by_student') }}</p>@endif
            </article>
        @endforeach
    </section>
    @if($latest > 1)<nav aria-label="{{ __('feedback.history') }}" class="flex flex-wrap gap-3 text-sm">
        @if($page > 1)<a class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-3" href="{{ route($baseRoute.'.show', [$conversation->id, 'segment' => $page-1]) }}">{{ __('feedback.older') }}</a>@endif
        @if($page < $latest)<a class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 px-3" href="{{ route($baseRoute.'.show', [$conversation->id, 'segment' => $page+1]) }}">{{ __('feedback.next') }}</a>@endif
        @if($firstUnread && $page !== $firstUnread)<a class="inline-flex min-h-11 items-center underline" href="{{ route($baseRoute.'.show', [$conversation->id, 'segment' => $firstUnread]) }}">{{ __('feedback.first_unread') }}</a>@endif
        @if($page !== $latest)<a class="inline-flex min-h-11 items-center underline" href="{{ route($baseRoute.'.show', [$conversation->id, 'segment' => $latest]) }}">{{ __('feedback.latest') }}</a>@endif
    </nav>@endif
    <form method="POST" action="{{ route($baseRoute.'.read', $conversation->id) }}" data-feedback-form data-feedback-locale="{{ app()->getLocale() }}" data-feedback-ack @unless($admin) data-feedback-auto-read hidden @endunless data-session-url="{{ route($baseRoute.'.session') }}" data-actor="{{ $actorKey }}" class="feedback-read-actions space-y-3">
        @include('components.feedback-form-state', ['showDraftNotice' => $admin])
        <input type="hidden" name="receipt" value="{{ $receipt }}">
        @if($admin)<p class="text-xs text-slate-500">{{ __('feedback.read_help') }}</p>
        <button data-feedback-submit class="min-h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold">{{ __('feedback.acknowledge') }}</button>@endif
    </form>
    @if($conversation->status !== 'closed')
        <form method="POST" action="{{ route($baseRoute.'.update', $conversation->id) }}" novalidate data-feedback-form data-feedback-locale="{{ app()->getLocale() }}" data-session-url="{{ route($baseRoute.'.session') }}" data-actor="{{ $actorKey }}" class="feedback-composer min-w-0 space-y-4 rounded-2xl border border-slate-200 bg-white p-4 sm:p-6">
            @include('components.feedback-form-state', ['showDraftNotice' => $admin])
            @unless($admin)<h2 class="feedback-composer-heading"><x-student-portal-icon name="pencil" />{{ __('feedback.reply') }}</h2>@endunless
            @if($admin)<label class="block text-sm font-semibold">{{ __('feedback.reply') }}<select name="action" class="{{ $inputClass }}"><option value="reply">{{ __('feedback.ordinary_reply') }}</option><option value="clarify">{{ __('feedback.clarify') }}</option></select></label><input type="hidden" name="version" value="{{ $conversation->version }}">
            @else<input type="hidden" name="action" value="reply">@endif
            <label class="block text-sm font-semibold">{{ __('feedback.message') }}<textarea name="body" dir="auto" required maxlength="5000" rows="{{ $admin ? 6 : 5 }}" class="{{ $inputClass }}"></textarea><span data-error-for="body" class="mt-2 block text-sm font-normal text-red-700" role="alert"></span></label>
            <button data-feedback-submit class="{{ $admin ? 'min-h-11 rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white' : 'student-button student-button-primary' }}">{{ __('feedback.send_reply') }}</button>
        </form>
    @else
        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5"><p class="text-sm">{{ __('feedback.closed_notice') }}</p>@unless($admin)<a href="{{ route('student.feedback.create') }}" class="student-button student-button-primary">{{ __('feedback.new') }}</a>@endunless</div>
    @endif
    @if($admin)
        @foreach(match($conversation->status) {'open','waiting_student' => ['resolve'], 'resolved' => ['close'], 'closed' => ['reopen']} as $action)
            <form method="POST" action="{{ route($baseRoute.'.update', $conversation->id) }}" data-feedback-form data-feedback-locale="{{ app()->getLocale() }}" data-session-url="{{ route($baseRoute.'.session') }}" data-actor="{{ $actorKey }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                @include('components.feedback-form-state')
                <input type="hidden" name="version" value="{{ $conversation->version }}"><input type="hidden" name="action" value="{{ $action }}">
                <button data-feedback-submit class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">{{ __('feedback.'.$action) }}</button>
            </form>
        @endforeach
    @endif
</div>

@extends('layouts.student', ['title' => 'Sessions'])

@section('content')
@php $isPreview = $isPreview ?? false; @endphp
@if(!empty($isPreview))
    <aside class="mb-6 rounded-2xl border-2 border-amber-500 bg-amber-50 p-4 shadow-sm" role="status">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500 text-slate-950 font-bold text-lg" aria-hidden="true">👁</span>
                <div>
                    <h2 class="text-sm font-bold text-amber-950">{{ __('Read-Only Student Portal Preview') }}</h2>
                    <p class="text-xs text-amber-800">{{ __('Viewing as') }} <strong>{{ $previewStudent->first_name ?? $student->first_name }} {{ $previewStudent->last_name ?? $student->last_name }}</strong>{{ __('. All actions and mutations are disabled.') }}</p>
                </div>
            </div>
            <a href="{{ $returnUrl ?? route('admin.students.show', $student) }}" class="inline-flex min-h-11 items-center rounded-xl bg-slate-900 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800">
                {{ __('Exit Preview & Return to Admin →') }}
            </a>
        </div>
    </aside>
@endif
<p class="text-xs font-semibold uppercase tracking-wider text-stone-500">{{ __('Your Sessions') }}</p>
<h1 class="mt-2 text-3xl font-semibold tracking-tight text-nile-900">{{ __('Sessions') }}</h1>
<p class="mt-3 text-stone-600">{{ __('Keep track of your upcoming lessons, available credits, previous sessions, and anything that needs your attention.') }}</p>
@if($isPreview)
<p class="mt-3 text-sm text-stone-600">{{ __('Times shown in') }} {{ $timezone }}.</p>
@else
<x-student-timezone :timezone="$timezone" :student-view="true" compact />
@endif
@if($isPreview)<p class="mt-2 text-stone-600">{{ __('Welcome,') }} {{ $student->first_name }}.</p>@endif
@if(session('success'))<p role="status" class="mt-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ __(session('success')) }}</p>@endif
@if(session('info'))<p role="status" class="mt-5 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ __(session('info')) }}</p>@endif
@if($isPreview)
<section aria-label="{{ __('Your next action') }}" class="mt-6 rounded-2xl border border-nile-200 bg-nile-50 p-5">
    <p class="text-sm font-semibold text-nile-700">{{ __('Your next step') }}</p>
    <h2 class="mt-1 text-xl font-semibold text-nile-900">{{ __($nextAction['label']) }}</h2>
    <p class="mt-2 text-sm text-stone-600">{{ __($nextAction['detail']) }}</p>
    @if(!$isPreview)<a href="{{ $nextAction['url'] }}" class="mt-4 inline-flex min-h-11 items-center rounded-xl bg-nile-800 px-5 py-2 text-sm font-semibold text-white">{{ __($nextAction['button'] ?? $nextAction['label']) }}</a>@endif
    @if(!$isPreview)<a href="{{ route('student.notifications.index') }}" class="ml-3 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">{{ $unreadNotifications }} {{ __('unread notifications') }}</a>@endif
</section>
<x-student-progress :progress="$progress" :read-only="$isPreview" />
<section class="mt-8" aria-labelledby="forms-title">
    <h2 id="forms-title" class="text-xl font-semibold">{{ __('Questionnaires') }}</h2>
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        @forelse($forms as $form)
            @php $submission = $formSubmissions->get($form->id); @endphp
            <article class="rounded-xl border border-stone-200 bg-white p-5">
                <h3 class="font-semibold text-nile-900">{{ $form->title }}</h3>
                @if($form->description)<p class="mt-1 text-sm text-stone-600">{{ $form->description }}</p>@endif
                <p class="mt-2 text-xs text-stone-500">@if(!$submission) {{ __('Not Started') }} @elseif($submission->status === 'draft') {{ __('In Progress') }} @else {{ __('Completed') }} @if((int) $submission->form_version_id !== (int) $form->published_version_id) {{ __('· Update Available') }} @endif @endif @if($form->is_mandatory) {{ __('· Required') }} @endif</p>
                @if(!$isPreview)<a href="{{ route('student.forms.show', $form->slug) }}" class="mt-3 inline-block text-sm font-semibold text-nile-800 underline">{{ __($submission?->status === 'submitted' ? 'View Answers' : ($submission ? 'Continue Questionnaire' : 'Start Questionnaire')) }}</a>@endif
            </article>
        @empty
            <p class="text-sm text-stone-600">{{ __('No questionnaires are currently assigned.') }}</p>
        @endforelse
    </div>
</section>
@else
<p id="forms-title" class="mt-6 text-sm"><a href="{{ route('student.home') }}#questionnaires" class="student-text-link">{{ __('Questionnaires are on Home →') }}</a></p>
@endif
<section class="mt-8" aria-labelledby="credits-title">
    <h2 id="credits-title" class="text-xl font-semibold">{{ __('Your Lesson Credits') }}</h2>
    @foreach($entitlementNotices as $notice)<p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $notice }}</p>@endforeach
    @if($canBookWithPackage)@if(!$isPreview)<a href="{{ route('student.bookings.create') }}" class="mt-3 inline-block rounded-lg bg-nile-800 px-4 py-2 text-sm font-semibold text-white hover:bg-nile-900">{{ __('Book Your Next Lesson') }}</a>@endif@endif
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">{{ __('Remaining Credits') }}</p><p class="mt-1 text-2xl font-semibold text-nile-900">@foreach($entitlementBalances as $balance)<span class="block text-base">{{ __($balance['label']) }}: {{ $balance['remaining'] }}</span>@endforeach</p></div>
        <div class="rounded-xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">{{ __('Lessons Completed') }}</p><p class="mt-1 text-2xl font-semibold text-nile-900">{{ $completedCount }}</p></div>
        <div class="rounded-xl border border-stone-200 bg-white p-5"><p class="text-sm text-stone-600">{{ __('Available Credits') }}</p><p class="mt-1 text-2xl font-semibold text-nile-900">@foreach($entitlementBalances as $balance)<span class="block text-base">{{ __($balance['label']) }}: {{ $balance['available'] }}</span>@endforeach</p></div>
    </div>
    @foreach($packageSummaries as $package)
        <p class="mt-3 text-sm text-stone-600">{{ $package['name'] }} · {{ __($package['summary']['entitlement_status']) }} · {{ collect($package['summary']['entitlements'])->map(fn ($row) => __($row['label']).': '.localized_number($row['remaining']))->implode('; ') ?: __('No entitlements') }} {{ __('· Purchase #') }}{{ $package['purchase_number'] }} {{ __('· Valid until:') }} {{ __($package['expiry'] ?? 'No scheduled expiry') }} · {{ $package['summary']['net_paid'] }} {{ $package['currency'] }} {{ __('paid net ·') }} {{ bccomp($package['summary']['overpaid'], '0.00', 2) > 0 ? $package['summary']['overpaid'].' '.$package['currency'].' '.__('overpaid') : $package['summary']['balance_due'].' '.$package['currency'].' '.__('due') }}</p>
    @endforeach
</section>
<section class="mt-8" aria-labelledby="upcoming-title">
    <h2 id="upcoming-title" class="text-xl font-semibold">{{ __('Upcoming Sessions') }}</h2>
    @forelse($upcoming as $booking)
        @php $customerStart = $booking->start_at_utc->copy()->setTimezone($timezone); $meetingUrl = $meetingLinks->studentUrl($booking); @endphp
        <article class="mt-4 rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
            <p class="font-semibold">{{ localized_date($customerStart, 'D, M j, Y · g:i A') }} <span class="text-sm font-normal text-stone-500">{{ $customerStart->timezoneName }}</span></p>
            <p class="mt-1 text-sm text-stone-600">{{ __($booking->sessionType?->name ?? 'Private lesson') }} · {{ __($bookingStatusLabels[$booking->id]) }}</p>
            <p class="mt-1 text-sm text-stone-600">{{ __('Tutor:') }} {{ $tutorName }}</p>@if(!$isPreview)<a href="{{ route('student.bookings.show', $booking->id) }}" class="mt-3 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">{{ __('View Session') }}</a>@endif
            @if(!$isPreview)<p class="mt-2 text-sm text-stone-600" x-data="{ remaining: 0, timer: null, destroy() { clearInterval(this.timer); }, refresh() { const reveal = Number(this.$el.dataset.reveal); if (reveal > 0 && Date.now() >= reveal) { clearInterval(this.timer); location.reload(); return; } this.remaining = Math.max(0, Math.floor((Date.parse($el.dataset.start) - Date.now()) / 1000)); } }" data-reveal="{{ !$meetingUrl && $booking->meeting_room_id && $booking->status === 'confirmed' && $booking->start_at_utc->copy()->subMinutes((int) \App\Domains\CMS\Models\Setting::get('meeting.student_reveal_minutes', 15))->isFuture() ? $booking->start_at_utc->copy()->subMinutes((int) \App\Domains\CMS\Models\Setting::get('meeting.student_reveal_minutes', 15))->timestamp * 1000 : 0 }}" data-start="{{ $booking->start_at_utc->toIso8601String() }}" x-init="refresh(); timer = setInterval(() => refresh(), 1000)"><span x-text="@js(__(':hours h :minutes min until lesson')).replace(':hours', Math.floor(remaining / 3600)).replace(':minutes', Math.floor(remaining % 3600 / 60))"></span></p>@endif
            @if($booking->status === 'confirmed' && $meetingUrl)
                @if(!$isPreview)<a href="{{ $meetingUrl }}" target="_blank" rel="noopener noreferrer" class="mt-3 inline-block rounded-lg bg-nile-800 px-4 py-2 text-sm font-semibold text-white hover:bg-nile-900">{{ __('Join lesson with') }} {{ $tutorName }}</a>@endif
            @elseif($booking->status === 'confirmed')
                <p class="mt-2 text-sm text-stone-600">@if($booking->meeting_room_id){{ __('Your') }} {{ $booking->meeting_provider_snapshot }} {{ __('link opens') }} {{ \App\Domains\CMS\Models\Setting::get('meeting.student_reveal_minutes', 15) }} {{ __('minutes before the lesson.') }}@else {{ __('Your tutor will assign your meeting room before the lesson.') }}@endif</p>
            @endif
            @if($booking->status === 'confirmed' && now('UTC')->addHours(24)->lessThanOrEqualTo($booking->start_at_utc))
                @if(!$isPreview)<a href="{{ route('student.bookings.reschedule', $booking->id) }}" class="mt-3 inline-block text-sm font-semibold text-nile-800 underline">{{ __('Reschedule') }}</a>@endif
            @elseif($booking->status === 'confirmed')
                <p class="mt-3 text-sm text-stone-600">{{ __('To change a session within 24 hours, contact your tutor directly.') }}</p>
            @endif
        </article>
    @empty
        <p class="mt-4 rounded-xl border border-stone-200 bg-white p-5 text-stone-600">{{ __('No Upcoming Sessions. Book your next lesson when a suitable format and eligible credits are available.') }}</p>
    @endforelse
</section>
<section class="mt-10" aria-labelledby="history-title">
    <h2 id="history-title" class="text-xl font-semibold">{{ __('Previous Sessions') }}</h2>
    @forelse($history as $booking)
        @php $customerStart = $booking->start_at_utc->copy()->setTimezone($timezone); $meetingUrl = $meetingLinks->studentUrl($booking); @endphp
        <article class="mt-4 rounded-xl border border-stone-200 bg-white p-5">
            <p class="font-semibold">{{ localized_date($customerStart, 'D, M j, Y · g:i A') }} <span class="text-sm font-normal text-stone-500">{{ $customerStart->timezoneName }}</span></p>
            <p class="mt-1 text-sm text-stone-600">{{ __($booking->sessionType?->name ?? 'Private lesson') }} · {{ __($bookingStatusLabels[$booking->id]) }}</p>
            @if(!$isPreview)<a href="{{ route('student.bookings.show', $booking->id) }}" class="mt-2 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">{{ __('View Session') }}</a>@endif<x-lesson-materials :booking="$booking" :read-only="$isPreview" />
            @if(!$isPreview && $booking->status === 'completed')
                @php $savedFeedback = $lessonFeedback->get($booking->id); @endphp
                <details class="mt-4 border-t border-stone-100 pt-4"><summary class="cursor-pointer text-sm font-semibold text-nile-800">{{ __('Optional private lesson feedback') }}</summary>
                    <p class="mt-2 text-xs text-stone-500">{{ __('Only you and authorized teaching staff can see this feedback.') }}</p>
                    <form method="POST" action="{{ route('student.lessons.feedback', $booking->id) }}" class="mt-3 grid gap-3">
                        @csrf
                        <label class="text-sm">{{ __('Rating') }}<select name="rating" required class="mt-1 min-h-11 w-full rounded-xl border border-stone-300 p-2"><option value="">{{ __('Choose a rating') }}</option>@foreach([5 => '5 · Very helpful', 4 => '4 · Helpful', 3 => '3 · Okay', 2 => '2 · Could improve', 1 => '1 · Needs attention'] as $rating => $label)<option value="{{ $rating }}" @selected($savedFeedback?->rating === $rating)>{{ __($label) }}</option>@endforeach</select></label>
                        <label class="text-sm">{{ __('Comment (optional)') }}<textarea name="comment" rows="2" maxlength="2000" class="mt-1 w-full rounded-xl border border-stone-300 p-2">{{ $savedFeedback?->comment }}</textarea></label>
                        <button class="min-h-11 justify-self-start rounded-xl bg-nile-800 px-4 py-2 text-sm font-semibold text-white">{{ __('Save private feedback') }}</button>
                    </form>
                </details>
            @endif
        </article>
    @empty
        <p class="mt-4 rounded-xl border border-stone-200 bg-white p-5 text-stone-600">{{ __('No Previous Sessions Yet. Your completed and earlier appointments will appear here.') }}</p>
    @endforelse
</section>
@endsection

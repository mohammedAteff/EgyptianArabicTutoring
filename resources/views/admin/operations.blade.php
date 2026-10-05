@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-xs font-bold uppercase tracking-wider text-amber-700">{{ $todayDate }} · {{ $businessTz }}</p><h1 class="mt-2 font-serif text-3xl font-bold">Today & Operations</h1><p class="mt-2 text-sm text-slate-600">Lessons, follow-ups and the work that needs attention.</p></div><a href="{{ route('admin.tasks.index') }}" class="min-h-11 rounded-xl bg-amber-600 px-5 py-3 text-sm font-semibold text-white">Open staff tasks</a></div>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">@foreach(['todayLessons'=>'Lessons today', 'upcomingLessons'=>'Next 7 days', 'overdueTasks'=>'Overdue tasks', 'activeAlerts'=>'Active alerts'] as $key=>$label)<a href="#{{ $key }}" class="rounded-2xl border border-slate-200 bg-white p-5"><span class="text-sm text-slate-500">{{ $label }}</span><strong class="mt-2 block text-3xl">{{ $counts[$key] }}</strong></a>@endforeach</div>
    <div class="grid items-start gap-5 lg:grid-cols-2">
        @foreach(['todayLessons'=>'Today’s lessons', 'upcomingLessons'=>'Upcoming lessons'] as $key=>$heading)
        <x-operations-card :heading="$heading" :count="$counts[$key]" :url="route('admin.bookings.index')" :id="$key">
            @forelse($$key as $booking)<div class="rounded-xl bg-slate-50 p-3"><a href="{{ route('admin.bookings.show', $booking->id) }}" class="font-semibold text-amber-800">{{ $booking->student?->name ?? $booking->contact?->name ?? 'Student' }} · {{ $booking->sessionType?->title }}</a><p class="mt-1 text-sm text-slate-600">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($booking->start_at_utc, $businessTz) }} · {{ $businessTz }} · {{ ucfirst(str_replace('_', ' ', $booking->status)) }}</p>@can('viewLessonWorkspace', $booking)<a class="mt-2 inline-block py-2 text-sm font-semibold text-amber-800" href="{{ route('admin.lessons.show', $booking->id) }}">Open lesson workspace</a>@endcan</div>@empty<p class="text-sm text-slate-500">No lessons in this period.</p>@endforelse
        </x-operations-card>
        @endforeach
        @foreach(['overdueTasks'=>'Overdue tasks', 'followUps'=>'Student follow-ups today'] as $key=>$heading)
        <x-operations-card :heading="$heading" :count="$counts[$key]" :url="route('admin.tasks.index', ['due' => $key === 'overdueTasks' ? 'overdue' : 'today'])" :id="$key">
            @forelse($$key as $task)<div class="rounded-xl bg-slate-50 p-3"><a class="break-words font-semibold text-amber-800" href="{{ route('admin.tasks.index', ['q'=>$task->title]) }}">{{ $task->title }}</a><p class="mt-1 text-sm text-slate-600">{{ $task->assignee?->name }} · {{ $task->due_date?->format('j M Y') }} · {{ ucfirst($task->priority) }}</p>@if($task->student)<a class="inline-block py-2 text-sm text-amber-800" href="{{ route('admin.students.show', $task->student_id) }}">{{ $task->student->name }}</a>@endif</div>@empty<p class="text-sm text-slate-500">No outstanding tasks in this section.</p>@endforelse
        </x-operations-card>
        @endforeach
        <x-operations-card heading="Important student alerts" :count="$counts['activeAlerts']" id="activeAlerts">
            @forelse($activeAlerts as $alert)<a class="block rounded-xl border border-amber-200 bg-amber-50 p-3" href="{{ route('admin.students.show', $alert->student_id) }}"><strong class="break-words text-sm text-amber-950">{{ $alert->title }}</strong><span class="mt-1 block text-xs text-amber-800">{{ $alert->student?->name }}</span></a>@empty<p class="text-sm text-slate-500">No active student alerts.</p>@endforelse
        </x-operations-card>
        <x-operations-card heading="Shared pinned notes" :count="$counts['sharedNotes']" :url="route('admin.staff-bins.index', ['collection'=>'shared'])">
            @forelse($sharedNotes as $note)<a class="block rounded-xl bg-slate-50 p-3" href="{{ route('admin.staff-bins.index', ['q'=>$note->title, 'collection'=>'shared']) }}"><strong class="break-words text-sm text-amber-800">{{ $note->title }}</strong><span class="mt-1 block text-xs text-slate-500">Pinned by {{ $note->pinnedBy?->name ?? 'Former staff member' }}</span></a>@empty<p class="text-sm text-slate-500">No shared pinned notes.</p>@endforelse
        </x-operations-card>
        @if(auth('web')->user()?->isAdmin())
        <x-operations-card heading="Packages expiring within 7 days" :count="$counts['expiringPackages']" :url="route('admin.students.index', ['expiry_from'=>now($businessTz)->toDateString(), 'expiry_to'=>now($businessTz)->addDays(7)->toDateString()])">
            @forelse($expiringPackages as $package)<a class="block rounded-xl bg-slate-50 p-3" href="{{ route('admin.students.show', $package->student_id) }}"><strong class="text-sm text-amber-800">{{ $package->student?->name }}</strong><span class="mt-1 block text-sm text-slate-600">{{ $package->package_name }} · {{ $package->expiration_date?->format('j M Y') }}</span></a>@empty<p class="text-sm text-slate-500">No available packages nearing expiry.</p>@endforelse
        </x-operations-card>
        <x-operations-card heading="Low-credit students" :count="$counts['lowCreditStudents']" :url="route('admin.students.index')">
            <p class="text-xs text-slate-500">At least one active entitlement type has 0–2 units available. Types remain separate.</p>
            @forelse($lowCreditStudents as $student)<a class="block rounded-xl bg-slate-50 p-3" href="{{ route('admin.students.show', $student->id) }}"><strong class="text-sm text-amber-800">{{ $student->name }}</strong>@foreach($studentCredits[$student->id] as $credit)<span class="mt-1 block text-xs text-slate-600">{{ $credit['label'] }}: {{ $credit['available'] }} available</span>@endforeach</a>@empty<p class="text-sm text-slate-500">No low-credit students.</p>@endforelse
        </x-operations-card>
        <x-operations-card heading="Outstanding payment follow-ups" :count="$counts['paymentFollowUps']" :url="route('admin.billing.cashier')">
            @forelse($paymentFollowUps as $package)<a class="block rounded-xl bg-slate-50 p-3" href="{{ route('admin.billing.cashier', ['student_id'=>$package->student_id]) }}"><strong class="text-sm text-amber-800">{{ $package->student?->name }}</strong><span class="mt-1 block text-sm text-slate-600">{{ $package->package_name }} · Due {{ $package->currency }} {{ $paymentBalances[$package->id] }}</span></a>@empty<p class="text-sm text-slate-500">No outstanding balances on active packages.</p>@endforelse
        </x-operations-card>
        <x-operations-card heading="Recent form completions" :count="$counts['recentForms']">
            @forelse($recentForms as $submission)<a class="block rounded-xl bg-slate-50 p-3" href="{{ route('admin.students.show', $submission->student_id) }}"><strong class="text-sm text-amber-800">{{ $submission->student?->name }}</strong><span class="mt-1 block text-sm text-slate-600">{{ $submission->version?->form?->title }} · {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($submission->submitted_at) }}</span></a>@empty<p class="text-sm text-slate-500">No forms submitted in the last 7 days.</p>@endforelse
        </x-operations-card>
        @endif
        <x-operations-card heading="Recently viewed by me" :count="count($recentViews)">
            @forelse($recentViews as $recent)<a class="block rounded-xl bg-slate-50 p-3 text-sm font-semibold text-amber-800" href="{{ $recent['url'] }}">{{ $recent['label'] }}</a>@empty<p class="text-sm text-slate-500">Records you open will appear here.</p>@endforelse
        </x-operations-card>
    </div>
</div>
@endsection

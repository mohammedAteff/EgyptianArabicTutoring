@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-5xl space-y-5">
    <div><p class="text-xs font-bold uppercase tracking-wider text-amber-700">Daily productivity</p><h1 class="mt-2 font-serif text-3xl font-bold">Staff Tasks</h1><p class="mt-2 text-sm text-slate-600">Keep follow-ups clear with an owner and a due date. Assistants manage the status of their assigned tasks.</p></div>
    <x-report-filters :filters="$filters" :action="route('admin.tasks.index')" :fields="['q'=>['Search task title','search'], 'owner'=>['Owner',['mine'=>'Assigned to me','all'=>'All authorized tasks']], 'status'=>['Status',['open'=>'Open','in_progress'=>'In progress','completed'=>'Completed','cancelled'=>'Cancelled']], 'priority'=>['Priority',['high'=>'High','normal'=>'Normal','low'=>'Low']], 'due'=>['Due',['overdue'=>'Overdue','today'=>'Today','upcoming'=>'Upcoming','undated'=>'No due date']], 'student_id'=>['Student ID','number']]" />
    <x-staff-saved-views section="tasks" :filters="$filters" :views="$savedViews" />
    <details class="rounded-2xl border border-slate-200 bg-white p-5" @if($selectedStudent || $errors->any()) open @endif><summary class="cursor-pointer font-semibold text-amber-800">Create staff task @if($selectedStudent) · {{ $selectedStudent->name }} @endif</summary>
        <form method="POST" action="{{ route('admin.tasks.store') }}" class="mt-4 space-y-4">@csrf<x-staff-task-fields :assignees="$assignees" :student-id="$selectedStudent?->id" /><button class="min-h-11 rounded-lg bg-amber-600 px-4 font-semibold text-white">Create task</button></form>
    </details>
    @forelse($tasks as $task)
    <article class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex flex-wrap items-start justify-between gap-3"><h2 class="break-words text-lg font-semibold">{{ $task->title }}</h2><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ ucfirst(str_replace('_', ' ', $task->status)) }} · {{ ucfirst($task->priority) }}</span></div>
        <p class="mt-2 text-sm text-slate-500">{{ $task->assignee?->name ?? 'Former staff member' }} · Due {{ $task->due_date?->format('j M Y') ?? 'not set' }}</p>
        @if($task->student)<a class="mt-2 inline-block text-sm font-semibold text-amber-800" href="{{ route('admin.students.show', $task->student_id) }}">{{ $task->student->name }}</a>@endif
        <p class="mt-3 whitespace-pre-wrap break-words text-sm text-slate-700">{{ $task->description }}</p>
        @can('update', $task)
        @if(auth('web')->user()?->isAdmin())
            <details class="mt-4"><summary class="cursor-pointer py-2 text-sm font-semibold text-amber-800">Edit task</summary><form method="POST" action="{{ route('admin.tasks.update', $task) }}" class="mt-3 space-y-4">@csrf @method('PATCH')<x-staff-task-fields :task="$task" :assignees="$assignees" /><button class="min-h-11 rounded-lg bg-slate-900 px-4 font-semibold text-white">Save task</button></form></details>
        @else
            <form method="POST" action="{{ route('admin.tasks.update', $task) }}" class="mt-4 flex flex-wrap items-end gap-3">@csrf @method('PATCH')<label class="text-sm font-semibold">Task status<select name="status" class="mt-1 block rounded-lg border border-slate-300 p-3">@foreach(['open', 'in_progress', 'completed', 'cancelled'] as $status)<option value="{{ $status }}" @selected($task->status === $status)>{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></label><button class="min-h-11 rounded-lg bg-slate-900 px-4 text-sm font-semibold text-white">Update status</button></form>
        @endif
        @endcan
    </article>
    @empty<p class="rounded-xl bg-white p-6 text-slate-500">No tasks match this view.</p>@endforelse
    {{ $tasks->links() }}
</div>
@endsection

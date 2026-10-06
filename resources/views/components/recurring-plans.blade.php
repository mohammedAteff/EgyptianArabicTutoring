@props(['plans'])
<div class="space-y-4">
@forelse($plans as $plan)
<article class="rounded-2xl border border-slate-200 bg-white p-5">
<div class="flex flex-wrap items-start justify-between gap-3"><div><a href="{{ route('admin.students.scheduling', $plan->student_id) }}" class="font-semibold text-amber-800">{{ $plan->student->name }} · Plan #{{ $plan->id }}</a><p class="mt-1 text-sm text-slate-600">{{ $plan->sessionType->title }} · {{ ucfirst($plan->cadence) }} · {{ substr($plan->preferred_time, 0, 5) }} {{ $plan->timezone }}</p><p class="mt-1 text-xs text-slate-500">From {{ $plan->start_date->toDateString() }} · End {{ $plan->end_date?->toDateString() ?? 'not set' }} · Count {{ $plan->occurrence_count ?? 'not set (104 occurrence limit)' }} · DST ambiguity: {{ $plan->fold_policy }}</p></div><span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold">{{ ucfirst($plan->status) }}</span></div>
<div class="mt-4 flex flex-wrap items-end gap-4">
<form method="POST" action="{{ route('admin.recurring.generate', $plan) }}" class="flex flex-wrap items-end gap-2">@csrf<label class="text-xs font-semibold">Starting occurrence<input name="from_sequence" type="number" min="1" max="104" value="1" required class="mt-1 block w-28 rounded-lg border border-slate-300 p-2.5"></label><label class="text-xs font-semibold">Batch size<input name="count" type="number" min="1" max="12" value="4" required class="mt-1 block w-24 rounded-lg border border-slate-300 p-2.5"></label><button class="min-h-11 rounded-xl bg-amber-700 px-4 py-2 text-sm font-semibold text-white">Generate lessons</button></form>
<form method="POST" action="{{ route('admin.recurring.update', $plan) }}" class="flex items-end gap-2">@csrf @method('PATCH')<label class="text-xs font-semibold">Plan status<select name="status" class="mt-1 block rounded-lg border border-slate-300 bg-white p-2.5">@foreach(['active', 'paused', 'completed'] as $status)<option @selected($plan->status === $status)>{{ $status }}</option>@endforeach</select></label><button class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold">Save status</button></form>
</div></article>
@empty<p class="rounded-2xl border border-dashed border-slate-300 p-6 text-sm text-slate-500">No recurring plans yet. Open a student’s Scheduling & holidays page to create one.</p>@endforelse
{{ $plans->links() }}
</div>

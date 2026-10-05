@props(['student', 'alerts'])
<section aria-label="Student operational alerts" class="mb-6 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2"><h2 class="text-xl font-bold">Pinned operational alerts</h2><span class="text-xs text-slate-500">Staff only · Scheduling, contact and payment follow-ups</span></div>
    @foreach($alerts->where('status', 'active') as $alert)
        <article class="rounded-xl border border-amber-300 bg-amber-50 p-4">
            <h3 class="break-words font-semibold text-amber-950">{{ $alert->title }}</h3><p class="mt-2 whitespace-pre-wrap break-words text-sm text-amber-900">{{ $alert->body }}</p>
            <p class="mt-2 text-xs text-amber-800">{{ $alert->author?->name ?? 'Former staff member' }} · Active</p>
            @can('update', $alert)<form method="POST" action="{{ route('admin.student-alerts.update', [$student->id, $alert->id]) }}" class="mt-3 flex flex-wrap gap-2">@csrf @method('PATCH')<button name="status" value="resolved" class="min-h-11 rounded-lg bg-white px-3 text-sm font-semibold">Resolve alert</button><button name="status" value="archived" class="min-h-11 rounded-lg px-3 text-sm font-semibold">Archive alert</button></form>@endcan
        </article>
    @endforeach
    @if($alerts->where('status', 'active')->isEmpty())<p class="text-sm text-slate-500">No active operational alerts.</p>@endif
    @can('create', \App\Domains\Students\Models\StudentOperationalAlert::class)
    <details class="rounded-xl border border-slate-200 bg-white p-4"><summary class="cursor-pointer py-1 text-sm font-semibold text-amber-800">Add operational alert</summary>
        <form method="POST" action="{{ route('admin.student-alerts.store', $student->id) }}" class="mt-4 space-y-3">@csrf
            <label class="block text-sm font-semibold">Alert title<input name="title" required maxlength="160" value="{{ old('title') }}" class="mt-1 block w-full rounded-lg border border-slate-300 p-3"></label>
            <label class="block text-sm font-semibold">Alert details<textarea name="body" required maxlength="5000" rows="3" class="mt-1 block w-full rounded-lg border border-slate-300 p-3">{{ old('body') }}</textarea></label>
            <button class="min-h-11 rounded-lg bg-amber-600 px-4 text-sm font-semibold text-white">Pin operational alert</button>
        </form>
    </details>
    @endcan
    @if($alerts->where('status', '!=', 'active')->isNotEmpty())
    <details class="rounded-xl border border-slate-200 bg-white p-4"><summary class="cursor-pointer py-1 text-sm font-semibold">Resolved and archived alerts ({{ $alerts->where('status', '!=', 'active')->count() }})</summary>
        @foreach($alerts->where('status', '!=', 'active') as $alert)<article class="mt-3 rounded-lg bg-slate-50 p-3"><h3 class="break-words font-semibold">{{ $alert->title }} · {{ ucfirst($alert->status) }}</h3><p class="mt-2 whitespace-pre-wrap break-words text-sm">{{ $alert->body }}</p>@can('update', $alert)<form method="POST" action="{{ route('admin.student-alerts.update', [$student->id, $alert->id]) }}">@csrf @method('PATCH')<button name="status" value="active" class="min-h-11 text-sm font-semibold text-amber-800">Reactivate alert</button></form>@endcan</article>@endforeach
    </details>
    @endif
</section>

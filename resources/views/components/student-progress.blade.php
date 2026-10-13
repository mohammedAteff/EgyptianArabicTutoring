@props(['progress'])
<section aria-label="Learning progress" class="mt-7 rounded-2xl border border-stone-200 bg-white p-5">
    <h2 class="text-xl font-semibold text-nile-900">Your Learning Progress</h2>
    <p class="mt-1 text-sm text-stone-500">Recorded lesson and practice milestones shared by your tutor.</p>
    @if(array_sum($progress) > 0)
    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-sm text-stone-500">Completed lessons</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['completed_lessons'] }}</dd></div>
        <div><dt class="text-sm text-stone-500">Milestones completed</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['milestones_completed'] }} / {{ $progress['milestones_total'] }}</dd></div>
        <div><dt class="text-sm text-stone-500">Homework completed</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['homework_completed'] }} / {{ $progress['homework_total'] }}</dd></div>
        <div><dt class="text-sm text-stone-500">Patterns improved or resolved</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['errors_improved'] }} / {{ $progress['errors_total'] }}</dd></div>
    </dl>
    @else
    <p class="mt-4 text-sm text-stone-600">Your progress will appear here as you complete lessons and learning activities.</p>
    @endif
    <a href="{{ route('student.bins.index') }}" class="mt-4 inline-flex min-h-11 items-center text-sm font-semibold text-nile-800 underline">View Learning Notes @if($progress['shared_notes'] > 0)({{ $progress['shared_notes'] }})@endif</a>
</section>

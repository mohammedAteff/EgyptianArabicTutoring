@props(['progress'])
<section aria-label="Learning progress" class="mt-7 rounded-2xl border border-stone-200 bg-white p-5">
    <h2 class="text-xl font-semibold text-nile-900">Your learning progress</h2>
    <p class="mt-1 text-sm text-stone-500">Recorded lesson and practice milestones shared by your tutor.</p>
    <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div><dt class="text-sm text-stone-500">Completed lessons</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['completed_lessons'] }}</dd></div>
        <div><dt class="text-sm text-stone-500">Milestones completed</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['milestones_completed'] }} / {{ $progress['milestones_total'] }}</dd></div>
        <div><dt class="text-sm text-stone-500">Homework completed</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['homework_completed'] }} / {{ $progress['homework_total'] }}</dd></div>
        <div><dt class="text-sm text-stone-500">Patterns improved or resolved</dt><dd class="mt-1 text-2xl font-semibold">{{ $progress['errors_improved'] }} / {{ $progress['errors_total'] }}</dd></div>
    </dl>
    <a href="{{ route('student.bins.index') }}" class="mt-4 inline-block text-sm font-semibold text-nile-800 underline">{{ $progress['shared_notes'] }} shared educational notes</a>
</section>
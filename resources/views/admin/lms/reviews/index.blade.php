@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <a href="{{ route('admin.lms.courses.edit',$course) }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-amber-800">← Course Studio</a>
    <h1 class="text-2xl font-bold" dir="auto">Learning reviews · {{ $course->title }}</h1>
    @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-900">{{ session('success') }}</p>@endif
    @if($errors->any())<p role="alert" class="rounded-xl bg-red-50 p-4 text-sm text-red-900">{{ $errors->first() }}</p>@endif
    <h2 class="text-xl font-bold">Written quiz answers</h2>
    @forelse($attempts as $attempt)<form method="POST" action="{{ route('admin.lms.reviews.quiz',$attempt) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
        @csrf<input type="hidden" name="version" value="{{ $attempt->lock_version }}">
        <h3 dir="auto" class="font-bold">{{ $attempt->definition['title'] }} · Student #{{ $attempt->student_id }} · Attempt {{ $attempt->number }}</h3>
        @foreach($attempt->definition['questions'] as $i=>$q)
            @if($q['type']==='written')<div class="space-y-2"><p dir="auto" class="font-semibold">{{ $q['prompt'] }}</p><p dir="auto" class="whitespace-pre-wrap break-words text-sm">{{ $attempt->answers[$i] }}</p><label class="block text-sm">Points out of {{ $q['points'] }}<input name="marks[{{ $i }}]" type="number" min="0" max="{{ $q['points'] }}" required class="ml-3 min-h-11 w-24 rounded-lg border border-slate-300 px-3"></label></div>@endif
        @endforeach
        <label class="block text-sm">Feedback<textarea dir="auto" name="feedback" maxlength="5000" rows="3" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></textarea></label>
        <x-lms.button>Save quiz review</x-lms.button>
    </form>@empty<p class="text-sm text-slate-500">No quizzes need manual review.</p>@endforelse
    {{ $attempts->links() }}
    <h2 class="text-xl font-bold">Assignments</h2>
    @forelse($submissions as $submission)<form method="POST" action="{{ route('admin.lms.reviews.assignment',$submission) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5">
        @csrf<input type="hidden" name="version" value="{{ $submission->lock_version }}">
        <h3 dir="auto" class="font-bold">{{ $submission->definition['title'] }} · Student #{{ $submission->student_id }} · Revision {{ $submission->number }}</h3>
        <p class="text-sm">{{ str_replace('_',' ',ucfirst($submission->status)) }} · {{ $submission->kind }}</p>
        <p dir="auto" class="whitespace-pre-wrap break-words text-sm">{{ $submission->body }}</p>
        @if($submission->url)<a href="{{ $submission->url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center break-all text-sm text-amber-800">Open submitted link ↗</a>@endif
        @if($submission->path)<a href="{{ route('admin.lms.reviews.file',$submission) }}" class="inline-flex min-h-11 items-center text-sm text-amber-800">Download private attachment</a>@endif
        <label class="block text-sm">Review decision<select name="status" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"><option value="under_review">Under Review</option><option value="needs_revision">Needs Revision</option><option value="approved">Approved</option></select></label>
        <label class="block text-sm">Feedback<textarea dir="auto" name="feedback" maxlength="5000" rows="3" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></textarea></label>
        <x-lms.button>Save assignment review</x-lms.button>
    </form>@empty<p class="text-sm text-slate-500">No assignments need review.</p>@endforelse
    {{ $submissions->links() }}
</div>
@endsection

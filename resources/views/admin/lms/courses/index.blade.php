@extends('layouts.admin')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div><p class="text-xs font-bold uppercase tracking-wider text-amber-700">Learning</p><h1 class="mt-1 font-serif text-2xl font-bold text-slate-900">Course Studio</h1><p class="mt-1 text-sm text-slate-500">Build and organize courses. Saved drafts stay separate from the live course.</p></div>
        <a href="{{ route('admin.lms.courses.create') }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-bold text-white hover:bg-amber-700">+ Create course</a>
    </div>
    <form method="GET" class="grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-5">
        <x-lms.input label="Search courses" name="search" :value="$filters['search']??''" type="search" maxlength="120" placeholder="Title or URL"/>
        <div><label for="course-status-filter" class="mb-1.5 block text-sm font-semibold text-slate-700">Status</label><select id="course-status-filter" name="status" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="">All statuses</option>@foreach(['draft','published','unpublished','archived'] as $status)<option value="{{ $status }}" @selected(($filters['status']??'')===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div>
        <div><label for="course-scope-filter" class="mb-1.5 block text-sm font-semibold text-slate-700">Show</label><select id="course-scope-filter" name="view" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">@foreach(['active'=>'Active courses','archived'=>'Archived courses','all'=>'All courses'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['view']??'active')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div><label for="course-audience-filter" class="mb-1.5 block text-sm font-semibold text-slate-700">Access</label><select id="course-audience-filter" name="audience" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm"><option value="">All access types</option>@foreach(['public'=>'Public','member'=>'Member','all_students'=>'All Students','selected_students'=>'Selected Students'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['audience']??'')===$value)>{{ $label }}</option>@endforeach</select></div>
        <div class="flex items-end gap-2"><x-lms.button>Apply filters</x-lms.button><a class="px-2 py-3 text-xs font-semibold text-slate-500" href="{{ route('admin.lms.courses.index') }}">Reset</a></div>
    </form>
    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
        <div class="overflow-x-auto"><table class="w-full text-left text-sm">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs uppercase tracking-wider text-slate-500"><tr><th scope="col" class="px-4 py-4">Course</th><th scope="col" class="px-4 py-4">State</th><th scope="col" class="px-4 py-4">Live access</th><th scope="col" class="px-4 py-4">Actions</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($courses as $course)
                @php $draft=$course->revisions->first(); @endphp
                <tr class="align-top hover:bg-slate-50/70">
                    <td class="min-w-48 px-4 py-4"><a href="{{ route('admin.lms.courses.edit',$course) }}" class="font-bold text-slate-900 hover:text-amber-700" dir="auto">{{ $draft?->title??$course->title }}</a><p class="mt-1 text-xs text-slate-500">{{ $course->kind==='private'?'Private Student course':'Catalog course' }}</p>@if($draft)<p class="mt-2 text-xs font-semibold text-amber-700">Draft changes pending</p>@endif</td>
                    <td class="px-4 py-4"><span class="rounded-lg px-2 py-1 text-xs font-bold capitalize {{ $course->status==='published'?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-600' }}">{{ $course->status }}</span></td>
                    <td class="px-4 py-4 text-xs font-semibold text-slate-600">{{ ['public'=>'Public','member'=>'Member','all_students'=>'All Students','selected_students'=>'Selected Students'][$course->accessRule?->audience??'selected_students'] }}</td>
                    <td class="min-w-64 px-4 py-3"><div class="flex flex-wrap items-center gap-2">
                        <a href="{{ route('admin.lms.courses.edit',$course) }}" class="min-h-11 px-2 py-3 text-xs font-bold text-amber-700">Edit</a>
                        <a href="{{ route('admin.lms.courses.preview',$course) }}" class="min-h-11 px-2 py-3 text-xs font-semibold text-slate-600">Preview</a>
                        @if($course->status!=='archived')
                        <form method="POST" action="{{ route('admin.lms.courses.lifecycle',$course) }}">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="status" value="published"><x-lms.button tone="neutral">Publish</x-lms.button></form>
                        @endif
                        @if($course->status==='published')
                        <form method="POST" action="{{ route('admin.lms.courses.lifecycle',$course) }}">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="status" value="unpublished"><x-lms.button tone="neutral">Unpublish</x-lms.button></form>
                        @endif
                        <form method="POST" action="{{ route('admin.lms.courses.duplicate',$course) }}">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><x-lms.button tone="neutral">Duplicate</x-lms.button></form>
                        <form method="POST" action="{{ route('admin.lms.courses.lifecycle',$course) }}" onsubmit="return confirm('{{ $course->status==='archived'?'Restore this course to Draft?':'Archive this course and close learner access?' }}');">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="status" value="{{ $course->status==='archived'?'draft':'archived' }}"><x-lms.button tone="danger">{{ $course->status==='archived'?'Restore Draft':'Archive' }}</x-lms.button></form>
                    </div></td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-14 text-center"><p class="font-semibold text-slate-700">No courses match these filters.</p><p class="mt-2 text-sm text-slate-500">Create a course or adjust the filters above.</p></td></tr>
                @endforelse
            </tbody>
        </table></div>
        @if($courses->hasPages())<div class="border-t border-slate-200 p-4">{{ $courses->links() }}</div>@endif
    </div>
</div>
@endsection

@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Student records</p>
            <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight text-slate-950">Student Records</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">Review student contact details, upcoming sessions, and submitted questionnaires.</p>
        </div>

    </div>

    <p class="text-sm text-slate-600">Total Students: <strong>{{ $totalStudents }}</strong> · Roster members with allocated credits or confirmed/delivered sessions.</p>
    <x-staff-saved-views section="students" :filters="$filters" :views="$savedViews" />
    <x-report-filters :default-labels="['operational_status' => 'Default (active)']" :filters="$filters" :action="route('admin.students.index')" export-route="admin.students.export" :fields="[
        'q'=>['Name, email or phone','search'], 'pinned'=>['Pinned students',['1'=>'My pinned students']], 'operational_status' => ['Operational status', ['active' => 'Active (default)', 'inactive' => 'Inactive', 'archived' => 'Archived', 'all' => 'All operational states']], 'status'=>['Status',['verified'=>'Verified','legacy_unverified'=>'Unverified','suspended'=>'Suspended']], 'package'=>['Package name','text'], 'credits'=>['Credits',['available'=>'Available','none'=>'None available']],
        'expiry_from'=>['Expiry from','date'], 'expiry_to'=>['Expiry to','date'], 'timezone'=>['Preferred timezone','text'], 'session_status'=>['Session status',['confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled','no_show'=>'No show']], 'joined_from'=>['Joined from','date'], 'joined_to'=>['Joined through','date']
    ]" />
    @if(session('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif

    <div x-data="{ selected: [], allSelected: false, showBulkTask: false }" @keydown.escape.window="showBulkTask = false" class="space-y-4">
        <!-- Bulk Actions Floating Bar (Idea 79) -->
        <div x-show="selected.length > 0" x-cloak class="sticky top-4 z-20 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50 px-5 py-3 shadow-md">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-amber-900" x-text="selected.length + ' student(s) selected'"></span>
                <button type="button" @click="selected = []; allSelected = false" class="text-xs text-amber-700 underline hover:text-amber-900">Clear selection</button>
            </div>
            <div class="flex items-center gap-3">
                <form method="GET" action="{{ route('admin.students.export') }}" class="inline">
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="student_ids[]" :value="id">
                    </template>
                    <input type="hidden" name="format" value="csv">
                    <button type="submit" class="rounded-xl border border-amber-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-amber-900 hover:bg-amber-100">
                        Export Selected (CSV)
                    </button>
                </form>
                <button type="button" @click="showBulkTask = !showBulkTask" class="rounded-xl bg-slate-900 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-slate-800">
                    Create Bulk Task
                </button>
            </div>
        </div>

        @if(auth('web')->user()?->isAdmin())
        <form method="POST" action="{{ route('admin.students.bulk-teaching') }}" x-show="selected.length > 0" x-cloak class="grid gap-3 rounded-xl border border-slate-200 bg-white p-5 sm:grid-cols-2">@csrf
            <template x-for="id in selected" :key="'teaching-'+id"><input type="hidden" name="student_ids[]" :value="id"></template>
            <label class="text-sm font-semibold">Safe Batch Action<select name="action" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 p-3"><option value="resource">Assign Resource</option><option value="tag">Apply Tag</option></select></label>
            <label class="text-sm font-semibold">Resource<select name="resource_id" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 p-3"><option value="">Choose for resource assignments</option>@foreach($bulkResources as $resource)<option value="{{ $resource->id }}">{{ $resource->title }}</option>@endforeach</select></label>
            <label class="text-sm font-semibold">Tag<input name="label" maxlength="40" placeholder="Use for tag batches" class="mt-2 min-h-11 w-full rounded-xl border border-slate-300 p-3"></label><label class="flex min-h-11 items-center gap-2 text-sm"><input type="hidden" name="student_visible" value="0"><input type="checkbox" name="student_visible" value="1">Share with students</label>
            <button class="min-h-11 rounded-xl bg-amber-700 px-4 py-2 text-white sm:col-span-2">Apply to Selected Students</button>
        </form>
        @endif
        <!-- Bulk Task Dialog Form -->
        <div x-show="showBulkTask && selected.length > 0" x-cloak class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 class="font-serif text-lg font-bold text-slate-900">Create Task for Selected Students</h3>
            <p class="mt-1 text-xs text-slate-500">This will generate a staff task for each selected student.</p>
            <form method="POST" action="{{ route('admin.students.bulk-task') }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                @csrf
                <template x-for="id in selected" :key="'task-' + id">
                    <input type="hidden" name="student_ids[]" :value="id">
                </template>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700">Task Title *</label>
                    <input name="title" required placeholder="e.g. Follow up on course placement" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Due Date</label>
                    <input type="date" name="due_date" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700">Priority</label>
                    <select name="priority" class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-amber-500">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700">Description / Instructions</label>
                    <textarea name="description" rows="2" placeholder="Optional details..." class="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-500"></textarea>
                </div>
                <div class="sm:col-span-2 flex justify-end gap-2">
                    <button type="button" @click="showBulkTask = false" class="rounded-xl border border-slate-300 px-4 py-2 text-xs font-semibold text-slate-700">Cancel</button>
                    <button type="submit" class="rounded-xl bg-amber-600 px-4 py-2 text-xs font-semibold text-white hover:bg-amber-700">Create Tasks</button>
                </div>
            </form>
        </div>

        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="hidden grid-cols-[3rem_minmax(12rem,1.3fr)_minmax(12rem,1fr)_minmax(9rem,.8fr)_auto] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 md:grid items-center">
                <input type="checkbox" @change="allSelected = !allSelected; selected = allSelected ? [{{ $students->pluck('id')->implode(',') }}] : []" :checked="allSelected" class="rounded text-amber-600" title="Select all on this page">
                <span>Student</span><span>Contact</span><span>Timezone</span><span>Record</span>
            </div>
            <div class="divide-y divide-slate-100">
                @forelse($students as $student)
                    @php $isPinned = in_array($student->id, $pinnedStudentIds ?? []); @endphp
                    <div class="grid gap-3 px-5 py-4 transition hover:bg-amber-50/50 md:grid-cols-[3rem_minmax(12rem,1.3fr)_minmax(12rem,1fr)_minmax(9rem,.8fr)_auto] md:items-center md:gap-4">
                        <div>
                            <input type="checkbox" :value="{{ $student->id }}" x-model="selected" class="rounded text-amber-600">
                        </div>
                        <a href="{{ route('admin.students.show', $student->id) }}" class="min-w-0">
                            <span class="flex items-center gap-1.5 font-semibold text-slate-900">
                                @if($isPinned) <span class="text-amber-500 text-sm" title="Pinned favorite">★</span> @endif
                                <span class="truncate">{{ $student->first_name }} {{ $student->last_name }}</span>
                            </span>
                            <span class="mt-1 block text-xs text-slate-500">Student #{{ $student->id }}</span>
                        </a>
                        <a href="{{ route('admin.students.show', $student->id) }}" class="min-w-0 text-sm text-slate-700">
                            <span class="block truncate">{{ $student->email ?: 'No email on file' }}</span>
                            <span class="mt-1 block text-xs text-slate-500">{{ $student->phone ?: 'No phone on file' }}</span>
                        </a>
                        <a href="{{ route('admin.students.show', $student->id) }}" class="text-sm text-slate-600">
                            {{ $student->preferred_timezone ?: 'Business timezone' }}
                        </a>
                        <div>
                            <a href="{{ route('admin.students.show', $student->id) }}" class="text-sm font-semibold text-amber-800 hover:text-amber-950">
                                Open <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-12 text-center">
                        <p class="font-semibold text-slate-800">No student records found</p>
                        <p class="mt-1 text-sm text-slate-500">Try a different name, email, or phone number.</p>
                    </div>
                @endforelse
            </div>
            @if($students->hasPages())
                <div class="border-t border-slate-200 px-5 py-4">{{ $students->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection

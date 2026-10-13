@extends($portal ? 'layouts.student' : 'layouts.admin', $portal ? ['title' => 'Learning Notes'] : [])
@section('content')
@php
    $prefix = $portal ? 'student.bins.' : 'admin.student-bins.';
    $indexParams = $portal ? [] : ['student'=>$student];
    $actor = $portal ? auth('student')->user() : auth('web')->user();
@endphp
<div class="mx-auto max-w-5xl space-y-6">
    <div><a href="{{ $portal ? route('student.dashboard') : route('admin.students.show',$student) }}" class="text-sm text-amber-800">← {{ $portal ? 'Back to Sessions' : 'Student record' }}</a><h1 class="mt-3 font-serif text-3xl font-bold">{{ $portal ? 'Learning Notes' : 'Educational Notes' }}</h1><p class="mt-2 text-sm text-slate-500">{{ $student->name }} · {{ $portal ? 'Keep study notes and review notes shared with you. Learning Notes are available to you and authorized teaching staff.' : 'Lesson notes, vocabulary and writing practice.' }}</p></div>
    @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('success') }}</p>@endif
    @if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800">{{ $errors->first() }}</p>@endif
    @if($portal)
    <form method="GET" action="{{ route('student.bins.index') }}" class="flex flex-wrap items-end gap-3"><label class="min-w-0 flex-1 text-sm font-semibold">Search notes<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="mt-2 block min-h-11 w-full rounded-xl border border-stone-300 px-3"></label><button class="min-h-11 rounded-xl bg-nile-800 px-4 font-semibold text-white">Search</button><a href="{{ route('student.bins.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">Clear Search</a></form>
    @else
    <x-report-filters :filters="$filters" :action="route($prefix.'index',$indexParams)" :fields="['q'=>['Search notes','search']]" />
    @endif
    <form method="POST" action="{{ $editing ? route($prefix.'update',[...$indexParams,'bin'=>$editing]) : route($prefix.'store',$indexParams) }}" class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5" x-data="{
        applyTemplate(e) {
            const val = e.target.value;
            if (!val) return;
            const data = JSON.parse(val);
            if (data.title) this.$refs.titleInput.value = data.title;
            if (data.body) this.$refs.bodyInput.value = data.body;
        }
    }">@csrf @if($editing) @method('PUT') @endif
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ $portal ? ($editing ? 'Edit Note' : 'Create Note') : ($editing ? 'Edit note' : 'Create educational note') }}</h2>
            @if(! $portal)
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-medium">Insert Template:</span>
                    <select @change="applyTemplate($event)" class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs">
                        <option value="">Select a note template...</option>
                        @foreach(\App\Domains\Administration\Models\StaffNoteTemplate::orderBy('title')->get() as $tpl)
                            <option value="{{ json_encode(['title' => $tpl->title, 'body' => $tpl->body]) }}">{{ $tpl->title }} ({{ ucfirst($tpl->category) }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <label class="block text-sm font-semibold">Title<input x-ref="titleInput" name="title" required maxlength="160" value="{{ old('title',$editing?->title) }}" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
        <label class="block text-sm font-semibold">{{ $portal ? 'Your note' : 'Text' }}<textarea x-ref="bodyInput" name="body" required maxlength="30000" rows="7" class="mt-1 w-full rounded-xl border border-slate-300 p-3">{{ old('body',$editing?->body) }}</textarea></label>
        @if(! $portal)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="student_visible" value="1" @checked(old('student_visible',$editing?->student_visible ?? true))> Visible to this student</label>@endif
        <button class="rounded-xl bg-amber-600 px-5 py-2.5 font-semibold text-white">{{ $portal ? 'Save Note' : 'Save note' }}</button>@if($editing)<a href="{{ route($prefix.'index',$indexParams) }}" class="ml-3 text-sm">Cancel</a>@endif
    </form>
    @php
        $loadedBins = method_exists($bins, 'getCollection') ? $bins->getCollection() : collect($bins);
        $noteGroups = $portal
            ? ['Shared With You' => $loadedBins->filter(fn($bin) => $bin->created_by_type !== 'student'), 'My Notes' => $loadedBins->where('created_by_type', 'student')]
            : ['' => $loadedBins];
    @endphp
    @foreach($noteGroups as $groupLabel => $groupBins)
    @if($portal)<section class="space-y-4"><h2 class="text-xl font-semibold">{{ $groupLabel }}</h2><p class="text-sm text-stone-500">{{ $groupLabel === 'Shared With You' ? 'Notes and explanations shared with you.' : 'Your study notes, vocabulary and writing practice.' }}</p>@endif
    @forelse($groupBins as $bin)<article data-bin class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="flex items-start justify-between gap-3"><h2 class="break-words text-lg font-semibold">{{ $bin->title }}</h2><x-copy-button :bin="true" label="Copy note text" /></div>
        <p class="mt-1 text-xs text-slate-500">{{ $portal ? ($bin->created_by_type === 'student' ? 'My Notes' : 'Shared With You') : ($bin->created_by_type === 'student' ? 'Student-created' : 'Tutor / staff-created') }}{{ ! $portal && ! $bin->student_visible ? ' · Staff only' : '' }} · {{ $portal ? $bin->updated_at->copy()->timezone(session('student_display_timezone', $student->preferred_timezone ?: 'Africa/Cairo'))->format('j M Y, g:i A T') : app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($bin->updated_at) }}</p>
        <div @if($portal) dir="auto" @endif data-bin-body class="mt-4 break-words whitespace-pre-wrap text-sm">{{ $bin->body }}</div>
        <div class="mt-4 flex gap-4">@if(\Illuminate\Support\Facades\Gate::forUser($actor)->allows('update',$bin))<a href="{{ route($prefix.'edit',[...$indexParams,'bin'=>$bin]) }}" class="text-sm font-semibold text-amber-800">Edit</a>@endif
        @if(! $portal && \Illuminate\Support\Facades\Gate::forUser($actor)->allows('delete',$bin))<form method="POST" action="{{ route($prefix.'destroy',[...$indexParams,'bin'=>$bin]) }}">@csrf @method('DELETE')<details class="text-sm"><summary class="cursor-pointer font-semibold text-rose-700">Remove note</summary><p class="mt-2 text-slate-600">Deletion history will be retained.</p><label class="mt-2 flex items-center gap-2"><input type="checkbox" name="confirmation" value="DELETE" required>Confirm removal</label><button class="mt-2 rounded-lg bg-rose-700 px-3 py-2 font-semibold text-white">Delete note</button></details></form>@endif</div>
    </article>@empty @if(! $editing)<p class="rounded-2xl bg-white p-6 text-slate-500">{{ $portal ? (filled($filters['q'] ?? null) ? 'No Notes Found. Try a different word or phrase.' : 'No notes to display in this section on this page. Create a note or return when notes have been shared with you.') : 'No educational notes match your search.' }}</p>@endif @endforelse
    @if($portal)</section>@endif
    @endforeach
    @if(method_exists($bins,'links')){{ $bins->links() }}@endif
</div>
@endsection

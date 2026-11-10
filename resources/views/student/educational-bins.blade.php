@extends($portal ? 'layouts.student' : 'layouts.admin', $portal ? ['title' => 'Learning Notes'] : [])
@section('content')
@php
    $prefix = $portal ? 'student.bins.' : 'admin.student-bins.';
    $indexParams = $portal ? [] : ['student'=>$student];
    $actor = $portal ? auth('student')->user() : auth('web')->user();
@endphp
<div class="mx-auto max-w-5xl space-y-6" data-learning-notes-listing>
    <div><a href="{{ $portal ? route('student.dashboard') : route('admin.students.show',$student) }}" class="text-sm {{ $portal ? 'inline-flex min-h-11 items-center text-nile-800' : 'text-amber-800' }}">← {{ __($portal ? 'Back to Sessions' : 'Student record') }}</a><h1 class="mt-3 font-serif text-3xl font-bold">{{ __($portal ? 'Learning Notes' : 'Educational Notes') }}</h1><p class="mt-2 text-sm text-slate-500">{{ $student->name }} · {{ __($portal ? 'Keep study notes and review notes shared with you. Learning Notes are available to you and authorized teaching staff.' : 'Lesson notes, vocabulary and writing practice.') }}</p></div>
    @if(session('success'))<p role="status" class="rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ __(session('success')) }}</p>@endif
    @if($errors->any())<p role="alert" class="rounded-xl bg-rose-50 p-4 text-rose-800">{{ $errors->first() }}</p>@endif
    @if($errors->has('revision') && $editing)<a class="student-text-link" href="{{ route($prefix.'edit',[...$indexParams,'bin'=>$editing]) }}">{{ __('Load current version') }}</a>@endif
    @if($portal)
    <form method="GET" action="{{ route('student.bins.index') }}" class="student-note-search"><label class="min-w-0 flex-1 text-sm font-semibold">{{ __('Search notes') }}<input type="search" name="q" value="{{ $filters['q'] ?? '' }}" class="mt-2 block min-h-11 w-full rounded-xl border border-stone-300 px-3"></label><div class="student-note-search-actions"><button class="student-button student-button-primary">{{ __('Search') }}</button><a href="{{ route('student.bins.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-nile-800">{{ __('Clear Search') }}</a></div></form>
    @else
    <x-report-filters :filters="$filters" :action="route($prefix.'index',$indexParams)" :fields="['q'=>['Search notes','search']]" />
    @endif
    <form method="POST" action="{{ $editing ? route($prefix.'update',[...$indexParams,'bin'=>$editing]) : route($prefix.'store',$indexParams) }}" class="{{ $portal ? 'student-surface student-form' : 'rounded-2xl border border-slate-200 bg-white p-5' }} space-y-4" x-data="{
        applyTemplate(e) {
            const val = e.target.value;
            if (!val) return;
            const data = JSON.parse(val);
            if (data.title) this.$refs.titleInput.value = data.title;
            if (data.body) this.$refs.bodyInput.value = data.body;
        }
    }">@csrf @if($editing) @method('PUT') <input type="hidden" name="revision" value="{{ old('revision', $editing->revision) }}"> @endif
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-semibold">{{ __($portal ? ($editing ? 'Edit Note' : 'Create Note') : ($editing ? 'Edit note' : 'Create educational note')) }}</h2>
            @if(! $portal)
                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-500 font-medium">{{ __('Insert Template:') }}</span>
                    <select @change="applyTemplate($event)" class="rounded-lg border border-slate-300 bg-slate-50 px-2.5 py-1 text-xs">
                        <option value="">{{ __('Select a note template...') }}</option>
                        @foreach(\App\Domains\Administration\Models\StaffNoteTemplate::orderBy('title')->get() as $tpl)
                            <option value="{{ json_encode(['title' => $tpl->title, 'body' => $tpl->body]) }}">{{ $tpl->title }} ({{ ucfirst($tpl->category) }})</option>
                        @endforeach
                    </select>
                </div>
            @endif
        </div>
        <label class="block text-sm font-semibold">{{ __('Title') }}<input x-ref="titleInput" @if($portal) id="note-title" @error('title') aria-invalid="true" aria-describedby="note-title-error" @enderror @endif name="title" required maxlength="160" value="{{ old('title',$editing?->title) }}" class="mt-1 w-full rounded-xl border border-slate-300 p-3"></label>
        @if($portal) @error('title')<p id="note-title-error" class="text-sm text-rose-800">{{ __($message) }}</p>@enderror @endif
        <label class="block text-sm font-semibold">{{ __($portal ? 'Your note' : 'Text') }}<textarea x-ref="bodyInput" @if($portal) id="note-body" @error('body') aria-invalid="true" aria-describedby="note-body-error" @enderror @endif name="body" required maxlength="30000" rows="7" class="mt-1 w-full rounded-xl border border-slate-300 p-3">{{ old('body',$editing?->body) }}</textarea></label>
        @if($portal) @error('body')<p id="note-body-error" class="text-sm text-rose-800">{{ __($message) }}</p>@enderror @endif
        @if(! $portal)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="student_visible" value="1" @checked(old('student_visible',$editing?->student_visible ?? true))> {{ __('Visible to this student') }}</label>@endif
        <button class="{{ $portal ? 'student-button student-button-primary' : 'rounded-xl bg-amber-600 px-5 py-2.5 font-semibold text-white' }}">{{ __($portal ? 'Save Note' : 'Save note') }}</button>@if($editing)<a href="{{ route($prefix.'index',$indexParams) }}" class="ml-3 text-sm">{{ __('Cancel') }}</a>@endif
    </form>
    @php
        $loadedBins = method_exists($bins, 'getCollection') ? $bins->getCollection() : collect($bins);
        $noteGroups = $portal
            ? ['Shared With You' => $loadedBins->filter(fn($bin) => $bin->created_by_type !== 'student'), 'My Notes' => $loadedBins->where('created_by_type', 'student')]
            : ['' => $loadedBins];
    @endphp
    @foreach($noteGroups as $groupLabel => $groupBins)
    @if($portal)<section class="space-y-4"><h2 class="text-xl font-semibold">{{ __($groupLabel) }}</h2><p class="text-sm text-stone-500">{{ __($groupLabel === 'Shared With You' ? 'Notes and explanations shared with you.' : 'Your study notes, vocabulary and writing practice.') }}</p>@endif
    @forelse($groupBins as $bin)<article id="note-{{ $bin->id }}" data-bin class="{{ $portal ? 'student-surface' : 'rounded-2xl border border-slate-200 bg-white p-5' }}">
        <div class="{{ $portal ? 'student-note-heading' : 'flex items-start justify-between gap-3' }}"><h2 class="break-words text-lg font-semibold">{{ $bin->title }}</h2><div class="flex shrink-0 items-center gap-1"><x-copy-button :bin="true" label="Copy note text" />@if($portal)<x-student-bookmark-button kind="note" :target-id="$bin->id" :bookmark="$noteBookmarks[$bin->id] ?? null" icon-only />@endif</div></div>
        <p class="mt-1 text-xs text-slate-500">{{ __($portal ? ($bin->created_by_type === 'student' ? 'My Notes' : 'Shared With You') : ($bin->created_by_type === 'student' ? 'Student-created' : 'Tutor / staff-created')) }}{{ __(! $portal && ! $bin->student_visible ? ' · Staff only' : '') }} · {{ $portal ? $bin->updated_at->copy()->timezone(session('student_display_timezone', $student->preferred_timezone ?: 'Africa/Cairo'))->locale(app()->getLocale())->translatedFormat(localized_date_format('j M Y, g:i A T')) : app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($bin->updated_at) }}</p>
        <div @if($portal) dir="auto" @endif data-bin-body class="mt-4 break-words whitespace-pre-wrap {{ $portal ? 'student-note-body' : 'text-sm' }}">{{ $bin->body }}</div>
        <div class="mt-4 flex gap-4">@if(\Illuminate\Support\Facades\Gate::forUser($actor)->allows('update',$bin))<a href="{{ route($prefix.'edit',[...$indexParams,'bin'=>$bin]) }}" class="text-sm font-semibold {{ $portal ? 'inline-flex min-h-11 items-center text-nile-800' : 'text-amber-800' }}">{{ __('Edit') }}</a>@endif
        @if(! $portal && \Illuminate\Support\Facades\Gate::forUser($actor)->allows('delete',$bin))<form method="POST" action="{{ route($prefix.'destroy',[...$indexParams,'bin'=>$bin]) }}">@csrf @method('DELETE')<details class="text-sm"><summary class="cursor-pointer font-semibold text-rose-700">{{ __('Remove note') }}</summary><p class="mt-2 text-slate-600">{{ __('Deletion history will be retained.') }}</p><label class="mt-2 flex items-center gap-2"><input type="checkbox" name="confirmation" value="DELETE" required>{{ __('Confirm removal') }}</label><button class="mt-2 rounded-lg bg-rose-700 px-3 py-2 font-semibold text-white">{{ __('Delete note') }}</button></details></form>@endif</div>
        @if($portal)<x-student-bookmark-button kind="note" :target-id="$bin->id" :bookmark="$noteBookmarks[$bin->id] ?? null" compact />@endif
    </article>@empty @if(! $editing)<p class="rounded-2xl bg-white p-6 text-slate-500">{{ __($portal ? (filled($filters['q'] ?? null) ? 'No Notes Found. Try a different word or phrase.' : 'No notes to display in this section on this page. Create a note or return when notes have been shared with you.') : 'No educational notes match your search.') }}</p>@endif @endforelse
    @if($portal)</section>@endif
    @endforeach
    @if(method_exists($bins,'links')){{ $bins->links() }}@endif
</div>
@endsection

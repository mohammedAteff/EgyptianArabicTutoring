@extends('layouts.admin')
@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div><a href="{{ route('admin.lms.courses.index') }}" class="text-xs font-semibold text-slate-500 hover:text-amber-700">← Back to courses</a><h1 class="mt-3 font-serif text-2xl font-bold text-slate-900" dir="auto">{{ $graph['title'] }}</h1><p class="mt-2 text-sm text-slate-500">Course Studio · <span class="font-semibold capitalize">{{ $course->status }}</span>@if($hasDraft)<span class="ml-2 rounded-lg bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">Draft changes pending</span>@endif</p></div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.lms.courses.preview',$course) }}" target="_blank" rel="noopener" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700">Author preview</a>
            @if($course->status!=='archived')
            <form method="POST" action="{{ route('admin.lms.courses.lifecycle',$course) }}">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="status" value="published"><x-lms.button>Publish course</x-lms.button></form>
            @else
            <form method="POST" action="{{ route('admin.lms.courses.lifecycle',$course) }}">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="status" value="draft"><x-lms.button>Restore to Draft</x-lms.button></form>
            @endif
            @if($course->status==='published')
            <form method="POST" action="{{ route('admin.lms.courses.lifecycle',$course) }}">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="status" value="unpublished"><x-lms.button tone="neutral">Unpublish</x-lms.button></form>
            @endif
        </div>
    </div>
    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm leading-6 text-amber-900"><strong>Draft editing:</strong> save each form as you work. Learners keep the current live course until you publish. Section and lesson states are preserved; only Published sections and lessons become available.</div>
    <fieldset @disabled($course->status==='archived') class="min-w-0 space-y-6">
        @include('admin.lms.courses._videos')
        @if(auth()->user()?->role==='super_admin')@include('admin.lms.courses._protection')@endif
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">Course details</h2>
                <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="space-y-4">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="metadata">
                    <x-lms.input label="Course title" name="title" :value="old('operation')==='metadata'?old('title',$graph['title']):$graph['title']" :required="true" maxlength="200"/>
                    <x-lms.input label="Course URL name" name="slug" :value="old('operation')==='metadata'?old('slug',$graph['slug']):$graph['slug']" :required="true" maxlength="160" pattern="[a-z0-9]+(-[a-z0-9]+)*"/>
                    <p class="text-xs text-slate-500">{{ $course->kind==='private'?'Private Student ownership is retained.':'Catalog course.' }}</p>
                    <x-lms.button tone="neutral">Save details draft</x-lms.button>
                </form>
            </section>
            <section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
                <h2 class="text-lg font-bold text-slate-900">Course access</h2>
                @php($accessForm = old('operation')==='access' ? array_replace($graph['access'],array_intersect_key(session()->getOldInput(),$graph['access'])) : $graph['access'])
                <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="space-y-4" x-data="{mode:@js($accessForm['access_mode'])}">
                    @csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="access">
                    <div><label for="studio-audience" class="mb-1.5 block text-sm font-semibold text-slate-700">Who can access this course?</label><select id="studio-audience" name="audience" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm">@foreach(['public'=>'Public','member'=>'Member','all_students'=>'All Students','selected_students'=>'Selected Students'] as $value=>$label)<option value="{{ $value }}" @selected($accessForm['audience']===$value) @disabled($course->kind==='private'&&$value!=='selected_students')>{{ $label }}</option>@endforeach</select></div>
                    <div><label for="studio-mode" class="mb-1.5 block text-sm font-semibold text-slate-700">Access duration</label><select id="studio-mode" name="access_mode" x-model="mode" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm">@foreach(['permanent'=>'Permanent','fixed'=>'Fixed expiration','relative'=>'Relative days'] as $value=>$label)<option value="{{ $value }}" @selected($accessForm['access_mode']===$value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-lms.input label="Available from (UTC)" name="starts_at" type="datetime-local" step="1" :value="$accessForm['starts_at']?substr($accessForm['starts_at'],0,19):''"/>
                        <div x-show="mode==='fixed'"><x-lms.input label="Expires at (UTC)" name="expires_at" type="datetime-local" step="1" :value="$accessForm['expires_at']?substr($accessForm['expires_at'],0,19):''" x-bind:disabled="mode!=='fixed'"/></div>
                        <div x-show="mode==='relative'"><x-lms.input label="Elapsed days" name="relative_days" type="number" min="1" max="36500" :value="$accessForm['relative_days']" x-bind:disabled="mode!=='relative'"/></div>
                    </div>
                    <p class="text-xs leading-5 text-slate-500">Member means an authenticated verified Student. Relative days are 24-hour periods from the existing enrollment anchor. Selected Students need an explicit access grant; this classification creates no grants.</p>
                    <x-lms.button tone="neutral">Save access draft</x-lms.button>
                </form>
            </section>
        </div>
        <section class="rounded-3xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
            <h2 class="text-lg font-bold text-slate-900">Course attachments</h2><p class="mt-2 text-sm text-slate-500">Upload once and reuse in lessons. Existing Resources can be selected directly in content blocks.</p>
            <form method="POST" action="{{ route('admin.lms.courses.upload',$course) }}" enctype="multipart/form-data" class="mt-5 grid items-end gap-4 md:grid-cols-3">
                @csrf<input type="hidden" name="version" value="{{ $course->lock_version }}">
                <div><label for="attachment-kind" class="mb-1.5 block text-sm font-semibold text-slate-700">Attachment type</label><select id="attachment-kind" name="kind" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm"><option value="image">Image (JPG / PNG / WebP)</option><option value="file">PDF / ZIP / Word / audio file</option></select></div>
                <div><label for="attachment-file" class="mb-1.5 block text-sm font-semibold text-slate-700">Choose a file</label><input id="attachment-file" name="file" type="file" required accept=".jpg,.jpeg,.png,.webp,.pdf,.zip,.doc,.docx,.mp3,.wav,.m4a" class="min-h-11 w-full rounded-xl border border-slate-300 p-2 text-sm"></div>
                <x-lms.button tone="neutral">Upload attachment</x-lms.button>
            </form>
            <p class="mt-3 text-xs text-slate-500">Images up to 10 MB; other files up to 50 MB. Course attachments remain in private storage.</p>
            @if($assets->isNotEmpty())<details class="mt-5 rounded-xl border border-slate-200 p-3"><summary class="cursor-pointer text-sm font-semibold text-slate-700">{{ $assets->count() }} available attachments</summary><ul class="mt-3 space-y-2">@foreach($assets as $asset)<li class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600"><span dir="auto">{{ $asset->original_name??'Course attachment' }} <span class="text-xs text-slate-400">({{ $asset->kind }})</span></span><a class="min-h-11 px-2 py-3 text-xs font-semibold text-amber-700" href="{{ route('admin.lms.courses.assets',[$course,$asset]) }}" target="_blank" rel="noopener">Open attachment</a></li>@endforeach</ul></details>@endif
        </section>
        <section class="space-y-5" aria-labelledby="course-structure-title">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="course-structure-title" class="text-xl font-bold text-slate-900">Sections & lessons</h2><p class="text-xs text-slate-500">Use ↑ and ↓ to change the saved order.</p></div>
            @forelse($graph['sections'] as $sectionIndex=>$section)
            @php($sectionForm = old('operation')==='edit_section' && old('key')===$section['key'] ? array_replace($section,array_intersect_key(session()->getOldInput(),array_flip(['title','status']))) : $section)
            <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xs">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50 p-5"><h3 class="font-bold text-slate-900" dir="auto">Section {{ $sectionIndex+1 }} · {{ $section['title'] }}</h3>@include('admin.lms.courses._order',['course'=>$course,'operation'=>'reorder_section','key'=>$section['key'],'index'=>$sectionIndex,'count'=>count($graph['sections']),'label'=>$section['title']])</div>
                <div class="space-y-6 p-5 sm:p-6">
                    <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="grid items-end gap-4 md:grid-cols-3">
                        @csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="edit_section"><input type="hidden" name="key" value="{{ $section['key'] }}">
                        <x-lms.input label="Section title" name="title" :id="'section-'.$sectionIndex.'-title'" :value="$sectionForm['title']" :required="true" maxlength="200"/>
                        <x-lms.state :id="'section-'.$sectionIndex.'-status'" :value="$sectionForm['status']"/>
                        <x-lms.button tone="neutral">Save section draft</x-lms.button>
                    </form>
                    @foreach($section['lessons'] as $lessonIndex=>$lesson)
                    @php($lessonForm = old('operation')==='edit_lesson' && old('key')===$lesson['key'] ? array_replace($lesson,array_intersect_key(session()->getOldInput(),array_flip(['title','slug','status']))) : $lesson)
                    <details open class="rounded-2xl border border-slate-200">
                        <summary class="cursor-pointer rounded-t-2xl bg-slate-50 p-4 text-sm font-bold text-slate-800"><span dir="auto">Lesson {{ $lessonIndex+1 }} · {{ $lesson['title'] }}</span><span class="ml-2 rounded-lg bg-white px-2 py-1 text-xs font-semibold capitalize text-slate-500">{{ $lesson['status'] }}</span></summary>
                        <div class="space-y-5 p-4 sm:p-5">
                            <div class="flex justify-end">@include('admin.lms.courses._order',['course'=>$course,'operation'=>'reorder_lesson','key'=>$lesson['key'],'index'=>$lessonIndex,'count'=>count($section['lessons']),'label'=>$lesson['title']])</div>
                            @if(auth()->user()?->role==='super_admin')@include('admin.lms.courses._protection', ['protectionLesson' => $lesson])@endif
                            <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="grid gap-4 md:grid-cols-3">
                                @csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="edit_lesson"><input type="hidden" name="key" value="{{ $lesson['key'] }}">
                                <x-lms.input label="Lesson title" name="title" :id="'lesson-'.$sectionIndex.'-'.$lessonIndex.'-title'" :value="$lessonForm['title']" :required="true" maxlength="200"/>
                                <x-lms.input label="Lesson URL name" name="slug" :id="'lesson-'.$sectionIndex.'-'.$lessonIndex.'-slug'" :value="$lessonForm['slug']" maxlength="160"/>
                                <x-lms.state :id="'lesson-'.$sectionIndex.'-'.$lessonIndex.'-status'" :value="$lessonForm['status']"/>
                                <div class="md:col-span-3"><x-lms.button tone="neutral">Save lesson draft</x-lms.button></div>
                            </form>
                            @if(count($graph['sections'])>1)
                            <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="flex flex-col gap-3 rounded-xl bg-slate-50 p-3 sm:flex-row sm:items-end">
                                @csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="move_lesson"><input type="hidden" name="key" value="{{ $lesson['key'] }}">
                                <div class="min-w-0 flex-1"><label for="move-{{ $sectionIndex }}-{{ $lessonIndex }}" class="mb-1.5 block text-xs font-semibold text-slate-600">Move lesson to section</label><select id="move-{{ $sectionIndex }}-{{ $lessonIndex }}" name="destination" class="min-h-11 w-full rounded-xl border border-slate-300 px-3 text-sm">@foreach($graph['sections'] as $destination)@if($destination['status']!=='archived')<option value="{{ $destination['key'] }}" @selected($destination['key']===$section['key'])>{{ $destination['title'] }}</option>@endif@endforeach</select></div>
                                <x-lms.button tone="neutral">Move lesson</x-lms.button>
                            </form>
                            @endif
                            <div class="space-y-4">
                                <h4 class="text-sm font-bold text-slate-800">Ordered lesson content</h4>
                                @foreach($lesson['blocks'] as $blockIndex=>$block)
                                @if($block['status']!=='withdrawn')
                                <div class="space-y-4 rounded-2xl border border-slate-200 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-2"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Content {{ $blockIndex+1 }} · {{ str_replace('_',' ',$block['kind']) }}</p>@include('admin.lms.courses._order',['course'=>$course,'operation'=>'reorder_block','key'=>$block['key'],'index'=>$blockIndex,'count'=>count($lesson['blocks']),'label'=>'content '.($blockIndex+1).' in '.$lesson['title']])</div>
                                    @if(in_array($block['kind'],\App\Domains\Lms\Services\LmsContentService::AUTHORABLE,true))
                                        @include('admin.lms.courses._block-form',['course'=>$course,'lesson'=>$lesson,'block'=>$block,'resources'=>$resources,'assets'=>$assets])
                                    @else
                                        <p class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900">This content type is unavailable. Remove it or keep this lesson in Draft.</p>
                                    @endif
                                    <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" onsubmit="return confirm('Remove this content block? Shared Resources and attachment files are retained.');">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="remove_block"><input type="hidden" name="key" value="{{ $block['key'] }}"><x-lms.button tone="danger">Remove content</x-lms.button></form>
                                </div>
                                @endif
                                @endforeach
                                <details class="rounded-2xl border border-dashed border-amber-300 bg-amber-50/30 p-4"><summary class="cursor-pointer text-sm font-bold text-amber-800">+ Add lesson content</summary><div class="mt-4">@include('admin.lms.courses._block-form',['course'=>$course,'lesson'=>$lesson,'block'=>null,'resources'=>$resources,'assets'=>$assets])</div></details>
                            </div>
                        </div>
                    </details>
                    @endforeach
                    @php($newLessonForm = old('operation')==='add_lesson' && old('parent_key')===$section['key'] ? session()->getOldInput() : [])
                    <details @if($newLessonForm) open @endif class="rounded-2xl border border-dashed border-slate-300 p-4"><summary class="cursor-pointer text-sm font-bold text-slate-700">+ Add lesson to this section</summary>
                        <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="mt-4 grid gap-4 md:grid-cols-3">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="add_lesson"><input type="hidden" name="parent_key" value="{{ $section['key'] }}">
                            <x-lms.input label="Lesson title" name="title" :id="'new-lesson-'.$sectionIndex.'-title'" :value="$newLessonForm['title']??''" :required="true" maxlength="200"/>
                            <x-lms.input label="Lesson URL name" name="slug" :id="'new-lesson-'.$sectionIndex.'-slug'" :value="$newLessonForm['slug']??''" maxlength="160" placeholder="Optional; generated from the title"/>
                            <x-lms.state :id="'new-lesson-'.$sectionIndex.'-status'" :value="$newLessonForm['status']??'draft'"/>
                            <div class="md:col-span-3"><x-lms.button tone="neutral">Add lesson</x-lms.button></div>
                        </form>
                    </details>
                </div>
            </article>
            @empty
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center"><p class="font-semibold text-slate-700">No sections yet.</p><p class="mt-2 text-sm text-slate-500">Add your first section, then add its lessons and content.</p></div>
            @endforelse
            <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="grid items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5 md:grid-cols-3">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="add_section">
                <x-lms.input label="New section title" name="title" id="new-section-title" :value="old('operation')==='add_section'?old('title',''):''" :required="true" maxlength="200"/>
                <x-lms.state id="new-section-status" :value="old('operation')==='add_section'?old('status','draft'):'draft'"/>
                <x-lms.button>+ Add section</x-lms.button>
            </form>
        </section>
    </fieldset>
    <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
        <form method="POST" action="{{ route('admin.lms.courses.duplicate',$course) }}">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><x-lms.button tone="neutral">Duplicate as Draft</x-lms.button></form>
        @if($hasDraft)<form method="POST" action="{{ route('admin.lms.courses.discard',$course) }}" onsubmit="return confirm('Discard all pending draft changes? The current live course is retained.');">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><x-lms.button tone="danger">Discard draft changes</x-lms.button></form>@endif
        @if($course->status!=='archived')<form method="POST" action="{{ route('admin.lms.courses.lifecycle',$course) }}" onsubmit="return confirm('Archive this course and close learner access?');">@csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="status" value="archived"><x-lms.button tone="danger">Archive course</x-lms.button></form>@endif
    </div>
</div>
@endsection

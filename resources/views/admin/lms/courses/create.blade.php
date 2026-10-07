@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <a href="{{ route('admin.lms.courses.index') }}" class="text-xs font-semibold text-slate-500 hover:text-amber-700">← Back to courses</a>
    <div><h1 class="font-serif text-2xl font-bold text-slate-900">Create a course</h1><p class="mt-2 text-sm text-slate-500">Start with a safe Draft, then add sections, lessons and content.</p></div>
    <form method="POST" action="{{ route('admin.lms.courses.store') }}" class="space-y-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-xs sm:p-8" x-data="{kind:@js(old('kind','catalog'))}">
        @csrf
        <x-lms.input label="Course title" name="title" :value="old('title')" :required="true" maxlength="200" placeholder="Arabic foundations"/>
        <x-lms.input label="Course URL name" name="slug" :value="old('slug')" :required="true" maxlength="160" pattern="[a-z0-9]+(-[a-z0-9]+)*" hint="Use lowercase letters, numbers and hyphens, for example arabic-foundations."/>
        <div><label for="studio-kind" class="mb-1.5 block text-sm font-semibold text-slate-700">Course type</label><select id="studio-kind" name="kind" x-model="kind" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm"><option value="catalog" @selected(old('kind','catalog')==='catalog')>Catalog course</option><option value="private" @selected(old('kind')==='private')>Private Student course</option></select></div>
        <div x-show="kind==='private'"><label for="studio-owner" class="mb-1.5 block text-sm font-semibold text-slate-700">Student owner</label><select id="studio-owner" name="owner_student_id" :disabled="kind!=='private'" :required="kind==='private'" class="min-h-11 w-full rounded-xl border border-slate-300 bg-slate-50 px-3.5 text-sm"><option value="">Choose a verified Student</option>@foreach($students as $student)<option value="{{ $student->id }}" @selected((int)old('owner_student_id')===(int)$student->id)>{{ $student->first_name }} {{ $student->last_name }}</option>@endforeach</select><p class="mt-2 text-xs text-slate-500">Private ownership is retained through editing and duplication. Only this Student can receive private course access.</p></div>
        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-5"><a href="{{ route('admin.lms.courses.index') }}" class="px-3 py-2 text-xs font-semibold text-slate-500">Cancel</a><x-lms.button>Create Draft</x-lms.button></div>
    </form>
</div>
@endsection

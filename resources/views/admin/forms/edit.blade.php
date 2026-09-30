@extends('layouts.admin')

@section('content')
<div class="mb-6"><a href="{{ route('admin.forms.index') }}" class="text-sm font-medium text-nile-800 underline">← Forms</a><h1 class="mt-3 text-2xl font-semibold text-nile-900">{{ $form ? 'Edit form' : 'Create form' }}</h1></div>
@if($form && $form->published_version_id && (int) $form->published_version_id !== (int) $form->active_version_id)
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">
        Version {{ $form->publishedVersion?->version_number }} remains published to students. Version {{ $form->activeVersion?->version_number }} is an unpublished draft until you publish it.
    </div>
@endif
@if($errors->any())<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Please correct the form:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ $form ? route('admin.forms.update', $form) : route('admin.forms.store') }}" class="space-y-6 rounded-xl border border-stone-200 bg-white p-5 sm:p-7">
    @csrf
    @if($form) @method('PUT')<input type="hidden" name="base_version_id" value="{{ $form->active_version_id }}"><input type="hidden" name="lock_version" value="{{ $form->lock_version }}">@endif
    <div class="grid gap-5 sm:grid-cols-2">
        <label class="block text-sm font-medium">Title<input name="title" required maxlength="255" value="{{ old('title', $form?->title) }}" class="mt-1 block w-full rounded-lg border-stone-300"></label>
        <label class="block text-sm font-medium">Slug<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $form?->slug) }}" class="mt-1 block w-full rounded-lg border-stone-300"></label>
        <label class="block text-sm font-medium">Trigger<select name="trigger" class="mt-1 block w-full rounded-lg border-stone-300">@foreach(['none'=>'No automatic prompt','pre_booking'=>'Pre-booking (Intake)','after_booking'=>'After booking','after_reschedule'=>'After reschedule','next_session_check'=>'Before next session'] as $value=>$label)<option value="{{ $value }}" @selected(old('trigger', $form?->triggers->first()?->trigger_name ?? 'none') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="block text-sm font-medium">Description<textarea name="description" rows="2" class="mt-1 block w-full rounded-lg border-stone-300">{{ old('description', $form?->description) }}</textarea></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_mandatory" value="1" @checked(old('is_mandatory', $form?->is_mandatory))> Required for assigned students</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_edit_after_submission" value="1" @checked(old('can_edit_after_submission', $form?->can_edit_after_submission))> Allow revisions after submission</label>
    </div>
    @if($form)<label class="block text-sm font-medium">Version changelog<input name="changelog" maxlength="2000" class="mt-1 block w-full rounded-lg border-stone-300" placeholder="Describe this version if the current version is frozen"></label>@endif
    <div>
        <div class="flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-lg font-semibold">Questions</h2><p class="mt-1 text-sm text-stone-600">Edit the JSON list below. Choice questions need an options list; conditional logic uses version 1.</p></div><button type="button" id="insert-example" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-medium hover:bg-stone-50">Insert example question</button></div>
        <textarea id="questions-json" name="questions_json" required rows="20" spellcheck="false" class="mt-3 block w-full rounded-lg border-stone-300 font-mono text-xs leading-5">{{ old('questions_json', $questionsJson) }}</textarea>
        <p class="mt-2 text-xs text-stone-500">Supported types: short_text, long_text, email, phone, number, date, single_choice, multiple_choice, dropdown, yes_no, rating_scale, info_block. Assistant-hidden questions stay out of assistant response views and exports.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded-lg bg-nile-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-nile-900">Save draft</button>
        @if($form)<button type="submit" formaction="{{ route('admin.forms.publish', $form) }}" class="rounded-lg border border-green-700 px-4 py-2.5 text-sm font-semibold text-green-800 hover:bg-green-50">Publish version {{ $form->activeVersion?->version_number }}</button>@endif
    </div>
</form>
@if($form && $form->status !== 'archived')
<form class="mt-4" method="POST" action="{{ route('admin.forms.archive', $form) }}">@csrf<input type="hidden" name="lock_version" value="{{ $form->lock_version }}"><button class="text-sm font-medium text-red-700 underline" onclick="return confirm('Archive this form? Existing student submissions will be preserved.')">Archive form</button></form>
@endif
<script>
document.getElementById('insert-example')?.addEventListener('click', () => {
    const editor = document.getElementById('questions-json');
    try {
        const questions = JSON.parse(editor.value || '[]');
        if (!Array.isArray(questions)) throw new Error('A JSON list is required.');
        const number = questions.length + 1;
        questions.push({question_key: `question_${number}`, label: 'New question', question_type: 'short_text', is_required: false, assistant_visible: true, sort_order: number - 1, validation_rules: {max_length: 255}, presentation_config: {}, conditional_logic: null, options: []});
        editor.value = JSON.stringify(questions, null, 2);
    } catch (error) { window.alert(error.message); }
});
</script>
@endsection

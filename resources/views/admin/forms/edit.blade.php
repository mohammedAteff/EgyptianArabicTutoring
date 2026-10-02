@extends('layouts.admin')

@section('content')
<div class="mb-6"><a href="{{ route('admin.forms.index') }}" class="text-sm font-medium text-nile-800 underline">← Forms</a><h1 class="mt-3 text-2xl font-semibold text-nile-900">{{ $form ? 'Edit form' : 'Create form' }}</h1></div>
@if($form && $form->published_version_id && (int) $form->published_version_id !== (int) $form->active_version_id)
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" role="status">
        Version {{ $form->publishedVersion?->version_number }} remains published to students. Version {{ $form->activeVersion?->version_number }} is an unpublished draft until you publish it.
    </div>
@endif
@if($errors->any())<div role="alert" class="mb-5 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800"><p class="font-semibold">Please correct the form:</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form x-data="formBuilder(@js(json_decode(old('questions_json', $questionsJson), true) ?? []))" method="POST" action="{{ $form ? route('admin.forms.update', $form) : route('admin.forms.store') }}" class="space-y-6 rounded-xl border border-stone-200 bg-white p-5 sm:p-7">
    @csrf
    @if($form) @method('PUT')<input type="hidden" name="base_version_id" value="{{ $form->active_version_id }}"><input type="hidden" name="lock_version" value="{{ $form->lock_version }}">@endif
    <div class="grid gap-5 sm:grid-cols-2">
        <label class="block text-sm font-medium">Title<input name="title" required maxlength="255" value="{{ old('title', $form?->title) }}" class="mt-1 block w-full rounded-lg border-stone-300"></label>
        <label class="block text-sm font-medium">Slug<input name="slug" required pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug', $form?->slug) }}" class="mt-1 block w-full rounded-lg border-stone-300"></label>
        <fieldset class="rounded-lg border border-stone-300 p-3"><legend class="text-sm font-medium">When to show this form</legend><input type="hidden" name="triggers_present" value="1">@foreach(['pre_booking'=>'Short form before first booking','after_booking'=>'Long form after booking','after_reschedule'=>'After reschedule','next_session_check'=>'Before next session'] as $value=>$label)<label class="flex items-center gap-2 py-1 text-sm"><input type="checkbox" name="triggers[]" value="{{ $value }}" @checked(in_array($value, old('triggers', $form?->triggers->pluck('trigger_name')->all() ?? [])))>{{ $label }}</label>@endforeach</fieldset>
        <label class="block text-sm font-medium">Description<textarea name="description" rows="2" class="mt-1 block w-full rounded-lg border-stone-300">{{ old('description', $form?->description) }}</textarea></label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_mandatory" value="1" @checked(old('is_mandatory', $form?->is_mandatory))> Required for assigned students</label>
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="can_edit_after_submission" value="1" @checked(old('can_edit_after_submission', $form?->can_edit_after_submission))> Allow revisions after submission</label>
    </div>
    @if($form)<label class="block text-sm font-medium">Version changelog<input name="changelog" maxlength="2000" class="mt-1 block w-full rounded-lg border-stone-300" placeholder="Describe this version if the current version is frozen"></label>@endif
    <input type="hidden" name="questions_json" :value="serialize()">
    <div class="flex flex-wrap items-center justify-between gap-3"><h2 class="text-lg font-semibold">Questions</h2><div class="flex gap-2"><button type="button" @click="preview = !preview" class="rounded-lg border border-stone-300 px-4 py-2" x-text="preview ? 'Edit questions' : 'Preview'"></button><button type="button" @click="add()" class="rounded-lg bg-nile-800 px-4 py-2 text-white">Add question</button></div></div>
    <template x-for="(question, index) in questions" :key="question.question_key">
        <section class="space-y-4 rounded-xl border border-stone-300 bg-stone-50 p-4">
            <div x-show="!preview" class="space-y-4">
                <div class="flex flex-wrap gap-2"><button type="button" @click="move(index,-1)" :disabled="index === 0" class="rounded border px-3 py-1 disabled:opacity-40" aria-label="Move question up">↑</button><button type="button" @click="move(index,1)" :disabled="index === questions.length-1" class="rounded border px-3 py-1 disabled:opacity-40" aria-label="Move question down">↓</button><button type="button" @click="questions.splice(index,1)" class="rounded border px-3 py-1 text-red-700">Delete question</button></div>
                <div class="grid gap-3 sm:grid-cols-2"><label class="block text-sm">Question text<input x-model="question.label" required class="mt-1 w-full rounded-lg border border-stone-300 p-2"></label><label class="block text-sm">Answer type<select x-model="question.question_type" class="mt-1 w-full rounded-lg border border-stone-300 p-2"><template x-for="type in types"><option :value="type" x-text="type.replaceAll('_',' ')"></option></template></select></label></div>
                <label class="block text-sm">Help text<textarea x-model="question.description" class="mt-1 w-full rounded-lg border border-stone-300 p-2"></textarea></label>
                <div class="flex flex-wrap gap-4"><label><input type="checkbox" x-model="question.is_required"> Required</label><label><input type="checkbox" x-model="question.assistant_visible"> Visible to assistants</label></div>
                <div x-show="choice(question)"><p class="text-sm font-semibold">Choices</p><template x-for="(option, optionIndex) in question.options" :key="optionIndex"><div class="mt-2 flex gap-2"><input x-model="option.label" aria-label="Choice label" class="min-w-0 flex-1 rounded border border-stone-300 p-2"><input x-model="option.value" aria-label="Stable choice value" class="min-w-0 flex-1 rounded border border-stone-300 p-2"><button type="button" @click="question.options.splice(optionIndex,1)" class="text-red-700">Remove</button></div></template><button type="button" @click="addOption(question)" class="mt-2 rounded border px-3 py-2">Add choice</button></div>
                <div x-show="question.question_type === 'rating_scale'" class="flex gap-3"><label>Minimum<input type="number" x-model.number="question.presentation_config.min" class="w-20 rounded border p-2"></label><label>Maximum<input type="number" x-model.number="question.presentation_config.max" class="w-20 rounded border p-2"></label></div>
                <button type="button" x-show="!question.conditional_logic" @click="condition(question)" class="text-sm underline">Show only when an answer matches</button>
                <template x-if="question.conditional_logic"><div class="space-y-2"><label>Match <select x-model="question.conditional_logic.mode" class="rounded border p-2"><option value="all">All conditions</option><option value="any">Any condition</option></select></label><template x-for="(rule, ruleIndex) in question.conditional_logic.conditions" :key="ruleIndex"><div class="flex flex-wrap gap-2"><select x-model="rule.question_key" class="rounded border p-2"><option value="">Choose another question</option><template x-for="candidate in questions.filter(item => item.question_key !== question.question_key)"><option :value="candidate.question_key" x-text="candidate.label"></option></template></select><select x-model="rule.operator" class="rounded border p-2"><option value="equals">Equals</option><option value="not_equals">Does not equal</option><option value="contains">Contains</option><option value="is_answered">Is answered</option><option value="is_not_answered">Is unanswered</option></select><input x-model="rule.value" aria-label="Comparison value" class="rounded border p-2"></div></template><button type="button" @click="question.conditional_logic.conditions.push({question_key: '', operator: 'equals', value: ''})" class="mr-3 text-sm underline">Add condition</button><button type="button" @click="question.conditional_logic = null" class="text-sm underline">Always show this question</button></div></template>
            </div>
            <div x-show="preview"><p class="font-semibold" x-text="question.label + (question.is_required ? ' *' : '')"></p><p class="text-sm text-stone-600" x-text="question.description"></p><template x-if="choice(question)"><select class="mt-2 w-full rounded border p-2"><option>Choose an answer</option><template x-for="option in question.options"><option x-text="option.label"></option></template></select></template><template x-if="!choice(question) && question.question_type !== 'info_block'"><input disabled placeholder="Student answer" class="mt-2 w-full rounded border p-2"></template></div>
        </section>
    </template>
    <p x-show="!questions.length" class="text-sm text-stone-600">Add your first question to get started.</p>
    <div class="flex flex-wrap gap-3">
        <button type="submit" class="rounded-lg bg-nile-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-nile-900">Save draft</button>
    </div>
</form>
@if($form && $form->status !== 'archived')
<form class="mt-4" method="POST" action="{{ route('admin.forms.archive', $form) }}">@csrf<input type="hidden" name="lock_version" value="{{ $form->lock_version }}"><button class="text-sm font-medium text-red-700 underline" onclick="return confirm('Archive this form? Existing student submissions will be preserved.')">Archive form</button></form>
@endif
@if($form)
<form class="mt-5" method="POST" action="{{ route('admin.forms.publish', $form) }}">@csrf<input type="hidden" name="base_version_id" value="{{ $form->active_version_id }}"><input type="hidden" name="lock_version" value="{{ $form->lock_version }}"><p class="mb-2 text-sm text-stone-600">Save your changes before publishing. Publishing uses the saved version.</p><button class="rounded-lg border border-green-700 px-4 py-2 font-semibold text-green-800">Publish saved version {{ $form->activeVersion?->version_number }}</button></form>
@endif
@endsection

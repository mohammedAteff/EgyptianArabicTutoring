@extends('layouts.student', ['title' => $form->title])

@section('content')
<a href="{{ route('student.dashboard') }}" class="text-sm font-medium text-nile-800 underline">← Student dashboard</a>
<h1 class="mt-3 text-3xl font-semibold tracking-tight text-nile-900">{{ $form->title }}</h1>
@if($form->description)<p class="mt-2 text-stone-600">{{ $form->description }}</p>@endif
<p class="mt-2 text-xs text-stone-500">Form version {{ $version->version_number }}</p>
@if($updateAvailable)<p role="status" class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">An updated version is available. This draft stays with version {{ $version->version_number }} until you finish it.</p>@endif
@if($updateAvailable && !$startNewVersion)<a href="{{ route('student.forms.show', [$form->slug, 'new_version' => 1]) }}" class="mt-3 inline-block rounded-lg border border-amber-700 px-4 py-2 text-sm font-semibold text-amber-900">Start updated version</a>@endif
@if($startNewVersion)<p class="mt-4 rounded-lg bg-blue-50 px-4 py-3 text-sm text-blue-900">Starting a new response for version {{ $version->version_number }}. Your previous version remains unchanged.</p>@endif
@if(session('success'))<p role="status" class="mt-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</p>@endif
@if($errors->any())<div role="alert" class="mt-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('student.forms.save', $form->slug) }}" class="mt-6 space-y-5" data-questionnaire data-read-only="{{ $readOnly ? '1' : '0' }}">
    @csrf
    <input type="hidden" name="submission_id" value="{{ $submission?->id }}"><input type="hidden" name="form_version_id" value="{{ $version->id }}">
    <p data-save-status role="status" class="text-sm text-stone-600">{{ $readOnly ? 'Submitted' : 'Changes save automatically' }}</p>
    @foreach($version->questions as $question)
        @php $value = old("answers.{$question->question_key}", $answers[$question->question_key] ?? null); @endphp
        @if($question->question_type === 'info_block')
            <section class="rounded-xl border border-stone-200 bg-blue-50 p-5"><h2 class="font-semibold text-nile-900">{{ $question->label }}</h2>@if($question->description)<p class="mt-2 whitespace-pre-line text-sm text-stone-700">{{ $question->description }}</p>@endif</section>
        @else
            <fieldset data-question-key="{{ $question->question_key }}" data-conditional='@json($question->conditional_logic)' class="rounded-xl border border-stone-200 bg-white p-5">
                <legend class="font-semibold text-stone-900">{{ $question->label }} @if($question->is_required)<span class="text-red-700" aria-label="required">*</span>@endif</legend>
                @if($question->description)<p class="mt-1 text-sm text-stone-600">{{ $question->description }}</p>@endif
                @switch($question->question_type)
                    @case('long_text')<textarea name="answers[{{ $question->question_key }}]" rows="4" @required($question->is_required) maxlength="{{ $question->validation_rules['max_length'] ?? 10000 }}" class="mt-3 block w-full rounded-lg border-stone-300">{{ $value }}</textarea>@break
                    @case('single_choice')
                    @case('dropdown')<select name="answers[{{ $question->question_key }}]" @required($question->is_required) class="mt-3 block w-full rounded-lg border-stone-300"><option value="">Choose an option</option>@foreach($question->options as $option)<option value="{{ $option->value }}" @selected((string) $value === $option->value)>{{ $option->label }}</option>@endforeach</select>@break
                    @case('multiple_choice')<div class="mt-3 space-y-2">@foreach($question->options as $option)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="answers[{{ $question->question_key }}][]" value="{{ $option->value }}" @checked(in_array($option->value, (array) $value, true))>{{ $option->label }}</label>@endforeach</div>@break
                    @case('yes_no')<select name="answers[{{ $question->question_key }}]" @required($question->is_required) class="mt-3 block w-full rounded-lg border-stone-300"><option value="">Choose</option><option value="1" @selected($value === true || $value === '1')>Yes</option><option value="0" @selected($value === false || $value === '0')>No</option></select>@break
                    @case('rating_scale')<label class="mt-3 flex items-center gap-3">{{ $question->presentation_config['left_label'] ?? '' }}<input type="range" name="answers[{{ $question->question_key }}]" min="{{ $question->presentation_config['min'] ?? 1 }}" max="{{ $question->presentation_config['max'] ?? 5 }}" value="{{ $value ?? ($question->presentation_config['min'] ?? 1) }}" @required($question->is_required)><span>{{ $question->presentation_config['right_label'] ?? '' }}</span></label>@break
                    @default
                        <input type="{{ in_array($question->question_type, ['email', 'number', 'date'], true) ? $question->question_type : ($question->question_type === 'phone' ? 'tel' : 'text') }}" name="answers[{{ $question->question_key }}]" value="{{ $value }}" @required($question->is_required) @if($question->question_type === 'phone') pattern="\+[1-9][0-9]{7,14}" placeholder="+201012345678" @endif @if($question->question_type === 'short_text') maxlength="{{ $question->validation_rules['max_length'] ?? 255 }}" @endif class="mt-3 block w-full rounded-lg border-stone-300">
                @endswitch
            </fieldset>
        @endif
    @endforeach
    @unless($readOnly)<div class="flex flex-wrap gap-3"><button name="intent" value="draft" class="rounded-lg border border-stone-300 bg-white px-4 py-2.5 text-sm font-semibold">Save draft</button><button name="intent" value="submit" class="rounded-lg bg-nile-800 px-4 py-2.5 text-sm font-semibold text-white">{{ $submission?->status === 'submitted' ? 'Save responses' : 'Submit responses' }}</button></div>@else<p class="text-sm text-stone-600">Your responses are final. Contact your tutor if you need a correction.</p>@endunless
</form>
<script>
(() => {
    const form = document.querySelector('[data-questionnaire]');
    if (!form) return;
    const readOnly = form.dataset.readOnly === '1';
    const fields = [...form.querySelectorAll('[data-question-key]')];
    const values = () => {
        const result = {};
        for (const field of fields) {
            if (field.hidden) continue;
            const key = field.dataset.questionKey;
            const controls = [...field.querySelectorAll(`[name^="answers[${key}]"]`)];
            const checkboxes = controls.filter(control => control.type === 'checkbox');
            const checked = checkboxes.filter(control => control.checked).map(control => control.value);
            const control = controls.find(item => item.type !== 'checkbox') || controls[0];
            result[key] = checkboxes.length ? checked : (control?.value ?? null);
        }
        return result;
    };
    const passes = (condition, data) => {
        const value = data[condition.question_key] ?? null;
        const target = condition.value;
        const answered = value !== null && value !== '' && !(Array.isArray(value) && !value.length);
        switch (condition.operator) {
            case 'equals': return JSON.stringify(value) === JSON.stringify(target);
            case 'not_equals': return JSON.stringify(value) !== JSON.stringify(target);
            case 'contains': return Array.isArray(value) ? value.includes(target) : String(value ?? '').toLowerCase().includes(String(target ?? '').toLowerCase());
            case 'not_contains': return Array.isArray(value) ? !value.includes(target) : !String(value ?? '').toLowerCase().includes(String(target ?? '').toLowerCase());
            case 'in': return (Array.isArray(target) ? target : [target]).some(item => JSON.stringify(item) === JSON.stringify(value));
            case 'not_in': return !(Array.isArray(target) ? target : [target]).some(item => JSON.stringify(item) === JSON.stringify(value));
            case 'greater_than': return Number(value) > Number(target);
            case 'less_than': return Number(value) < Number(target);
            case 'is_answered': return answered;
            case 'is_not_answered': return !answered;
            default: return false;
        }
    };
    const refresh = () => {
        let data = values();
        for (let pass = 0; pass <= fields.length; pass++) {
            for (const field of fields) {
                const logic = JSON.parse(field.dataset.conditional || 'null');
                const conditions = logic?.conditions || [];
                const visible = !conditions.length || ((logic.mode || 'all') === 'any' ? conditions.some(item => passes(item, data)) : conditions.every(item => passes(item, data)));
                field.hidden = !visible;
                field.querySelectorAll('input,select,textarea').forEach(control => control.disabled = readOnly || !visible);
            }
            data = values();
        }
    };
    form.addEventListener('input', refresh);
    form.addEventListener('change', refresh);
    refresh();
    if (readOnly) return;
    const status = form.querySelector('[data-save-status]');
    let timer, saving = false, pending = false, submitting = false;
    const save = async () => {
        if (saving || submitting) { pending = true; return; }
        saving = true; pending = false; status.textContent = 'Saving…';
        try {
            const response = await fetch(@js(route('student.forms.autosave', $form->slug)), {method: 'POST', headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value}, body: JSON.stringify({answers: values(), submission_id: form.querySelector('[name="submission_id"]').value || null, form_version_id: Number(form.querySelector('[name="form_version_id"]').value)})});
            if (!response.ok) throw new Error(response.status === 409 ? 'Form changed. Reload before saving.' : 'Could not save. Use Save draft to retry.');
            const data = await response.json(); form.querySelector('[name="submission_id"]').value = data.draft_id;
            status.textContent = 'Saved';
        } catch (error) { status.textContent = error.message; }
        finally { saving = false; if (pending && !submitting) save(); }
    };
    const queueSave = () => { clearTimeout(timer); timer = setTimeout(save, 700); };
    form.addEventListener('input', queueSave); form.addEventListener('change', queueSave);
    form.addEventListener('submit', async (event) => {
        if (submitting) return;
        event.preventDefault(); clearTimeout(timer); pending = false; submitting = true;
        while (saving) await new Promise(resolve => setTimeout(resolve, 50));
        setTimeout(() => form.requestSubmit(event.submitter), 0);
    });
})();
</script>
@endsection

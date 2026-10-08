@php
    $questionRows = collect($payload['questions'] ?? [])->map(function ($q) {
        return ['type'=>$q['type'],'prompt'=>$q['prompt'],'points'=>$q['points'],'options_text'=>implode("\n",$q['options'] ?? []),
            'answer_text'=>match($q['type']) {
                'choice'=>(string)($q['answer']+1),
                'multiple'=>implode(',',array_map(fn($n)=>$n+1,$q['answer'])),
                'boolean'=>$q['answer'] ? 'true' : 'false',
                'matching'=>implode("\n",$q['answer']),
                'blank'=>implode("\n",$q['accepted']),
                default=>'',
            }];
    })->values()->all();
    if (!$questionRows) $questionRows = [['type'=>'choice','prompt'=>'','points'=>1,'options_text'=>'','answer_text'=>'']];
@endphp
<div x-show="['quiz','assignment'].includes(kind)" class="space-y-4 rounded-xl bg-slate-50 p-4">
    <label class="block text-sm font-semibold">Assessment title<input dir="auto" name="definition[title]" value="{{ $payload['title'] ?? '' }}" maxlength="200" :disabled="!['quiz','assignment'].includes(kind)" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
    <div x-show="kind==='assignment'" class="space-y-4">
        <label class="block text-sm">Instructions<textarea dir="auto" name="definition[instructions]" maxlength="10000" rows="4" :disabled="kind!=='assignment'" class="mt-2 w-full rounded-lg border border-slate-300 p-3">{{ $payload['instructions'] ?? '' }}</textarea></label>
        <fieldset><legend class="mb-2 text-sm font-semibold">Accepted response types</legend>@foreach(['text'=>'Text','file'=>'Private file','audio'=>'Audio upload / recording','video'=>'Small video upload','external_link'=>'External HTTPS link'] as $type=>$label)<label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="definition[types][]" value="{{ $type }}" :disabled="kind!=='assignment'" @checked(in_array($type,$payload['types'] ?? ['text'],true))>{{ $label }}</label>@endforeach</fieldset>
    </div>
    <div x-show="kind==='quiz'" x-data="{questions:@js($questionRows)}" class="space-y-4">
        <div class="grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">Passing score (%)<input type="number" name="definition[passing_score]" min="0" max="100" value="{{ $payload['passing_score'] ?? 80 }}" :disabled="kind!=='quiz'" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
            <label class="block text-sm">Attempt limit<input type="number" name="definition[attempt_limit]" min="1" max="100" value="{{ $payload['attempt_limit'] ?? 3 }}" :disabled="kind!=='quiz'" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
        </div>
        <label class="block text-sm">Answer review<select name="definition[review_policy]" :disabled="kind!=='quiz'" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"><option value="after_grading" @selected(($payload['review_policy'] ?? '')==='after_grading')>Show after grading</option><option value="never" @selected(($payload['review_policy'] ?? '')==='never')>Keep correct answers private</option></select></label>
        <p class="text-xs text-slate-500">Questions appear in this order. Each question awards all or none of its points; written answers are reviewed manually.</p>
        <template x-for="(q,i) in questions" :key="i">
            <fieldset class="space-y-3 rounded-xl border border-slate-200 bg-white p-4">
                <legend class="px-1 text-sm font-bold" x-text="'Question '+(i+1)"></legend>
                <label class="block text-sm">Question type<select x-model="q.type" :name="'definition[questions]['+i+'][type]'" :disabled="kind!=='quiz'" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"><option value="choice">Multiple choice</option><option value="multiple">Multiple answer</option><option value="boolean">True / false</option><option value="blank">Fill in the blank</option><option value="matching">Matching</option><option value="written">Short written answer</option></select></label>
                <label class="block text-sm">Question<textarea dir="auto" x-model="q.prompt" :name="'definition[questions]['+i+'][prompt]'" :disabled="kind!=='quiz'" maxlength="3000" rows="3" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></textarea></label>
                <label class="block text-sm">Points<input type="number" x-model="q.points" :name="'definition[questions]['+i+'][points]'" :disabled="kind!=='quiz'" min="1" max="100" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
                <label x-show="['choice','multiple','matching'].includes(q.type)" class="block text-sm">Options / matching source items (one per line)<textarea dir="auto" x-model="q.options_text" :name="'definition[questions]['+i+'][options_text]'" :disabled="kind!=='quiz'" rows="4" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></textarea></label>
                <label x-show="q.type!=='written'" class="block text-sm"><span x-text="({choice:'Correct option number (starting at 1)',multiple:'Correct option numbers separated by commas',boolean:'Correct answer: true or false',blank:'Accepted answers (one per line)',matching:'Matching targets in source order (one per line)'})[q.type]"></span><textarea dir="auto" x-model="q.answer_text" :name="'definition[questions]['+i+'][answer_text]'" :disabled="kind!=='quiz'" rows="3" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></textarea></label>
                <button type="button" @click="questions.splice(i,1)" class="min-h-11 text-sm font-semibold text-red-700">Remove question</button>
            </fieldset>
        </template>
        <button type="button" @click="if(questions.length<100) questions.push({type:'choice',prompt:'',points:1,options_text:'',answer_text:''})" class="min-h-11 rounded-xl border border-slate-300 px-4 text-sm font-semibold">+ Add question</button>
        <noscript><p class="text-sm">Enable JavaScript to edit quiz questions.</p></noscript>
    </div>
</div>

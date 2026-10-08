@php($learning = $lesson['learning_rules'] ?? ['required'=>true,'methods'=>['manual'],'video_threshold'=>95,'prerequisite_key'=>null,'drip_mode'=>'immediate','drip_days'=>null,'drip_at'=>null])
<details class="rounded-xl border border-slate-200 p-4">
    <summary class="cursor-pointer text-sm font-bold text-slate-700">Completion, prerequisites & schedule</summary>
    <form method="POST" action="{{ route('admin.lms.courses.update',$course) }}" class="mt-4 space-y-4">
        @csrf<input type="hidden" name="version" value="{{ $course->lock_version }}"><input type="hidden" name="operation" value="learning"><input type="hidden" name="key" value="{{ $lesson['key'] }}">
        <label class="flex min-h-11 items-center gap-3 text-sm"><input type="hidden" name="required" value="0"><input type="checkbox" name="required" value="1" @checked($learning['required'])>Required for course completion</label>
        <fieldset class="space-y-2"><legend class="mb-2 text-sm font-semibold">Completion requirements · all selected requirements must be met</legend>
            @foreach(['manual'=>'Student marks complete','video'=>'Protected video watch threshold','quiz_complete'=>'Quiz graded','quiz_pass'=>'Quiz passed','assignment_submit'=>'Assignment submitted','assignment_approve'=>'Assignment approved'] as $method=>$label)
                <label class="flex min-h-11 items-center gap-3 text-sm"><input type="checkbox" name="methods[]" value="{{ $method }}" @checked(in_array($method,$learning['methods'],true))>{{ $label }}</label>
            @endforeach
        </fieldset>
        <label class="block text-sm">Video watch threshold (%)<input type="number" name="video_threshold" min="1" max="100" value="{{ $learning['video_threshold'] }}" required class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
        <label class="block text-sm">Prerequisite lesson<select name="prerequisite_key" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"><option value="">None</option>
            @foreach($graph['sections'] as $prerequisiteSection)
                @foreach($prerequisiteSection['lessons'] as $prerequisite)
                    @if($prerequisite['key']!==$lesson['key'])<option value="{{ $prerequisite['key'] }}" @selected($learning['prerequisite_key']===$prerequisite['key'])>{{ $prerequisiteSection['title'] }} · {{ $prerequisite['title'] }}</option>@endif
                @endforeach
            @endforeach
        </select></label>
        <label class="block text-sm">Lesson schedule<select name="drip_mode" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3">@foreach(['immediate'=>'Immediately','relative'=>'Days after enrollment','fixed'=>'Fixed date and time'] as $mode=>$label)<option value="{{ $mode }}" @selected($learning['drip_mode']===$mode)>{{ $label }}</option>@endforeach</select></label>
        <label class="block text-sm">Elapsed days after enrollment<input type="number" name="drip_days" min="0" max="3650" value="{{ $learning['drip_days'] }}" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
        <label class="block text-sm">Fixed unlock (Business Timezone)<input type="datetime-local" name="drip_local" value="{{ $learning['drip_at'] ? \Carbon\CarbonImmutable::parse($learning['drip_at'])->timezone(app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone())->format('Y-m-d\\TH:i') : '' }}" class="mt-2 min-h-11 w-full rounded-lg border border-slate-300 px-3"></label>
        <p class="text-xs text-slate-500">Access is still required when the schedule unlocks. Updating required content preserves history and reopens current completion.</p>
        <x-lms.button tone="neutral">Save learning rules draft</x-lms.button>
    </form>
</details>

@props(['rule' => null, 'weekdays', 'defaultBufferMinutes' => 15])
@php
    $formKey = $rule ? 'rule-'.$rule->id : 'create';
    $submitted = old('availability_form') === $formKey;
    $field = fn (string $key, mixed $default = null) => $submitted ? old($key, $default) : ($rule?->{$key} ?? $default);
    $start = substr((string) $field('start_time', '09:00'), 0, 5);
    $end = substr((string) $field('end_time', '13:00'), 0, 5);
    $buffer = $field('buffer_minutes', $defaultBufferMinutes);
    $duration = $field('session_duration_minutes', '');
    $buffers = array_values(array_unique([...range(0, 60, 5), (int) $buffer]));
    sort($buffers);
@endphp
<div class="space-y-4" x-data="{ start: @js($start), end: @js($end), duration: @js($duration), buffer: @js($buffer) }">
    <input type="hidden" name="availability_form" value="{{ $formKey }}">
    @if($submitted && $errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-800">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <label class="block text-xs font-semibold text-slate-600">Weekday
        <select name="weekday" required class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm">@foreach($weekdays as $i => $name)<option value="{{ $i }}" @selected((int) $field('weekday', 0) === $i)>{{ $name }}</option>@endforeach</select>
    </label>
    <div class="grid grid-cols-2 gap-3">
        <label class="text-xs font-semibold text-slate-600">Start time<input type="time" name="start_time" x-model="start" required value="{{ $start }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">End time<input type="time" name="end_time" x-model="end" required value="{{ $end }}" class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">Lesson length (minutes)<input type="number" name="session_duration_minutes" x-model="duration" min="15" max="240" value="{{ $duration }}" placeholder="Use lesson type" class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">Buffer between lessons<select name="buffer_minutes" x-model="buffer" required class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm">@foreach($buffers as $minutes)<option value="{{ $minutes }}" @selected((int) $buffer === $minutes)>{{ $minutes === 0 ? 'No buffer' : $minutes.' minutes' }}</option>@endforeach</select></label>
        <label class="text-xs font-semibold text-slate-600">Minimum notice (hours)<input type="number" name="min_notice_hours" value="{{ $field('min_notice_hours', 12) }}" min="0" max="72" required class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm"></label>
        <label class="text-xs font-semibold text-slate-600">Booking horizon (days)<input type="number" name="max_horizon_days" value="{{ $field('max_horizon_days', 60) }}" min="1" max="180" required class="mt-1 w-full rounded-xl border border-slate-300 bg-slate-50 p-2.5 text-sm"></label>
    </div>
    <p class="rounded-xl bg-amber-50 p-3 text-xs leading-relaxed text-amber-900" aria-live="polite">
        <span x-show="Number(duration) > 0" x-text="Number(duration) + '-minute lessons with a ' + Number(buffer) + '-minute gap. Start times repeat every ' + (Number(duration) + Number(buffer)) + ' minutes from ' + start + '.'"></span>
        <span x-show="!Number(duration)">Lesson length follows the selected lesson type, with <span x-text="Number(buffer)">{{ $buffer }}</span> minutes between lessons.</span>
        The last lesson must finish by <span x-text="end">{{ $end }}</span>. Unused minutes at the end are allowed.
    </p>
</div>

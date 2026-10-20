@props(['action', 'timezone', 'sessionTypes' => null, 'label' => 'Save unavailability', 'studentView' => false])
<form method="POST" action="{{ $action }}" class="grid gap-4 sm:grid-cols-2">
@csrf<input type="hidden" name="idempotency_key" value="{{ \Illuminate\Support\Str::uuid() }}">
@if($sessionTypes)<label class="sm:col-span-2 text-sm font-semibold text-slate-700">Lesson type<select name="session_type_id" required class="mt-1 block w-full rounded-xl border border-slate-300 bg-white p-3">@foreach($sessionTypes as $type)<option value="{{ $type->id }}" @selected((string) old('session_type_id', request('session_type_id')) === (string) $type->id)>{{ $type->title }}</option>@endforeach</select></label>@endif
<label class="text-sm font-semibold text-slate-700">From date<input type="date" name="date_from" required value="{{ old('date_from', request('date_from')) }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
<label class="text-sm font-semibold text-slate-700">Through date<input type="date" name="date_to" required value="{{ old('date_to', request('date_to')) }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
@if($sessionTypes)
<label class="text-sm font-semibold text-slate-700">Earliest preferred start (optional)<input type="time" name="preferred_time_from" value="{{ old('preferred_time_from', request('preferred_time_from')) }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
<label class="text-sm font-semibold text-slate-700">Latest preferred start (optional)<input type="time" name="preferred_time_to" value="{{ old('preferred_time_to', request('preferred_time_to')) }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"></label>
<p class="text-xs text-slate-500 sm:col-span-2">Use the same start time twice for one exact time, or choose a range. An earlier latest time continues after midnight. Leave both blank for any time. All times use the timezone below.</p>
@endif
<label class="text-sm font-semibold text-slate-700">Timezone<input name="timezone" required maxlength="64" value="{{ old('timezone', $sessionTypes ? request('timezone', $timezone) : $timezone) }}" class="mt-1 block w-full rounded-xl border border-slate-300 p-3"><span class="mt-1 block text-xs font-normal text-slate-500">For example: Africa/Cairo or Europe/London. Both dates include the full day in this timezone.</span></label>
<x-operational-reason-select />
<label class="sm:col-span-2 text-sm font-semibold text-slate-700">Notes (optional)<textarea name="notes" maxlength="1000" rows="2" class="mt-1 block w-full rounded-xl border border-slate-300 p-3">{{ old('notes') }}</textarea></label>
<button @disabled($studentView && $sessionTypes !== null && $sessionTypes->isEmpty()) class="min-h-11 justify-self-start rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50 sm:col-span-2">{{ $label }}</button>
@if($studentView && $sessionTypes !== null && $sessionTypes->isEmpty())<p role="status" class="text-sm text-stone-600 sm:col-span-2">No lesson formats are available for new preferences right now. Contact Abdallah about your preferred period.</p>@endif
</form>

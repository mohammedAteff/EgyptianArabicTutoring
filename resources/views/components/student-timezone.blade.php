@props(['timezone'])
<div class="mt-5 rounded-xl border border-stone-200 bg-white p-4" x-data="{ init() { const detected = Intl.DateTimeFormat().resolvedOptions().timeZone; const selected = localStorage.getItem('awa.student.manualTimezone') || detected; if (selected && selected !== this.$el.dataset.timezone) { const next = new URL(location.href); next.searchParams.set('timezone', selected); location.replace(next.href); } } }" data-timezone="{{ $timezone }}">
    <form method="GET" class="flex flex-wrap items-end gap-3" x-on:submit="localStorage.setItem('awa.student.manualTimezone', $event.target.elements.timezone.value)">
        @foreach(request()->except(['timezone', 'date']) as $key => $value) @if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
        <label class="flex-1 text-sm font-medium">Your current timezone<input name="timezone" required value="{{ $timezone }}" list="current-student-timezones" class="mt-1 block w-full rounded-lg border border-stone-300 p-3"></label>
        <datalist id="current-student-timezones">@foreach(app(\App\Domains\Timezone\Services\TimezoneService::class)->getAvailableTimezones() as $zone)<option value="{{ $zone['id'] }}">{{ $zone['label'] }}</option>@endforeach</datalist>
        <button class="rounded-lg border border-nile-800 px-4 py-3 text-sm font-semibold text-nile-800">Change timezone</button>
        <button type="button" class="px-3 py-3 text-sm font-semibold text-nile-800 underline" x-on:click="localStorage.removeItem('awa.student.manualTimezone'); const next = new URL(location.href); next.searchParams.set('timezone', Intl.DateTimeFormat().resolvedOptions().timeZone); location.assign(next.href)">Use device timezone</button>
    </form>
</div>

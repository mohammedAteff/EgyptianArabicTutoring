@props(['timezone', 'studentView' => false])
@if($studentView)
<p class="mt-5 text-xs font-semibold uppercase tracking-wider text-stone-500">Your Timezone</p>
<p class="mt-2 text-sm text-stone-600">Your lesson times are shown in {{ $timezone }}. Changing the display timezone does not change the appointment itself.</p>
@endif
<div class="mt-5" data-timezone="{{ $timezone }}" x-data x-init="
    let chosen = null;
    try { chosen = localStorage.getItem('awa.student.manualTimezone'); } catch (e) {}
    chosen = chosen || Intl.DateTimeFormat().resolvedOptions().timeZone;
    if (chosen && chosen !== $el.dataset.timezone && !new URL(location.href).searchParams.has('timezone')) {
        const url = new URL(location.href); url.searchParams.set('timezone', chosen); location.replace(url.toString());
    }
">
    <x-timezone-selector :timezone="$timezone" />
</div>

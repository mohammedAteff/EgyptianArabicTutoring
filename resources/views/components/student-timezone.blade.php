@props(['timezone'])
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
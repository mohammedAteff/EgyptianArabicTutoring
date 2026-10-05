@props(['timezone', 'livewire' => false])
@php
    $displayService = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class);
    $selectedDisplay = $displayService->formatSlotForDisplay($timezone, now('UTC'));
    $options = app(\App\Domains\Timezone\Services\TimezoneService::class)->getCuratedList();
    if (!collect($options)->contains('id', $timezone)) { $options[] = ['id' => $timezone]; }
@endphp
<div data-timezone-selector class="relative mb-6" x-data="{
    open: false, search: '',
    selectTimezone(zone) {
        try { localStorage.setItem('awa.student.manualTimezone', zone); } catch (error) {}
        const url = new URL(location.href);
        url.searchParams.set('timezone', zone);
        url.searchParams.delete('date');
        location.assign(url.toString());
    },
    trapFocus(event) {
        const controls = Array.from($refs.dialog.querySelectorAll('button, input')).filter(control => control.offsetParent !== null && !control.disabled);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    }
}">
    <div class="flex flex-col justify-between gap-4 rounded-2xl border border-stone-200 bg-white p-4 shadow-sm sm:flex-row sm:items-center">
        <div class="min-w-0"><p class="text-xs font-medium text-stone-500">{{ __('Showing times in your timezone:') }}</p>
            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm font-semibold text-stone-900">
                <x-timezone-flag :display="$selectedDisplay" :alt="__('Flag for :country', ['country' => $selectedDisplay['timezone_country_name']])" />
                {{ $selectedDisplay['city'] }} <span class="break-all text-xs font-normal text-stone-500">({{ $timezone }}, {{ $selectedDisplay['utc_offset'] }})</span>
            </p>
        </div>
        <button type="button" x-ref="trigger" @click="open = true; search = ''; $nextTick(() => $refs.search.focus())" :aria-expanded="open.toString()" class="min-h-11 rounded-xl border border-terracotta-200 bg-terracotta-50 px-4 py-2 text-sm font-semibold text-terracotta-700">{{ __('Change Timezone') }}</button>
    </div>
    <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 p-4 backdrop-blur-sm" @keydown.escape.window="if (open) { open = false; $refs.trigger.focus(); }" @click.self="open = false; $refs.trigger.focus()">
        <div x-ref="dialog" @keydown.tab="trapFocus($event)" role="dialog" aria-modal="true" aria-label="{{ __('Select Your Timezone') }}" class="w-full max-w-lg space-y-4 rounded-3xl border border-stone-200 bg-white p-5 shadow-2xl sm:p-6">
            <div class="flex items-center justify-between gap-3"><h2 class="text-lg font-bold">{{ __('Select Your Timezone') }}</h2><button type="button" aria-label="{{ __('Close timezone selector') }}" @click="open = false; $refs.trigger.focus()" class="min-h-11 min-w-11 rounded-xl text-stone-600">✕</button></div>
            <label class="block text-sm font-medium">{{ __('Search timezones') }}<input x-ref="search" x-model="search" type="search" placeholder="{{ __('City, region or timezone') }}" class="mt-2 min-h-11 w-full rounded-xl border border-stone-200 px-4 py-2"></label>
            <div class="max-h-72 space-y-1 overflow-y-auto">
                @foreach($options as $option)
                    @php $optionDisplay = $displayService->formatSlotForDisplay($option['id'], now('UTC')); @endphp
                    <button type="button" data-timezone-option data-search="{{ mb_strtolower($optionDisplay['label'].' '.$optionDisplay['timezone_country_name']) }}" x-show="!search.trim() || $el.dataset.search.includes(search.trim().toLowerCase())" aria-pressed="{{ $timezone === $option['id'] ? 'true' : 'false' }}"
                        @if($livewire) wire:key="timezone-option-{{ $option['id'] }}" wire:click="selectTimezone('{{ $option['id'] }}')" @click="open = false; $refs.trigger.focus()"
                        @else @click="selectTimezone(@js($option['id']))"
                        @endif
                        class="flex min-h-11 w-full items-center justify-between gap-2 rounded-xl px-3 py-2 text-left text-sm hover:bg-stone-50 {{ $timezone === $option['id'] ? 'bg-terracotta-50 font-bold text-terracotta-700' : 'text-stone-700' }}">
                        <span class="inline-flex items-center gap-2"><x-timezone-flag :display="$optionDisplay" :alt="__('Flag for :country', ['country' => $optionDisplay['timezone_country_name']])" /><span>{{ $optionDisplay['label'] }}</span></span>
                    </button>
                @endforeach
                <p x-show="search.trim() && !Array.from($el.parentElement.querySelectorAll('[data-timezone-option]')).some(el => el.dataset.search.includes(search.trim().toLowerCase()))" class="p-4 text-center text-sm text-stone-500">{{ __('No matching timezones found. Try searching for a major capital or city.') }}</p>
            </div>
        </div>
    </div>
</div>

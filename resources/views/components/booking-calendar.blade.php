@props(['month', 'timezone', 'slots', 'selectedDate' => null, 'livewire' => false, 'previousUrl' => null, 'nextUrl' => null, 'variant' => null, 'canViewNextMonth' => true])
@php
    $calendar = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month, $timezone);
    $today = now($timezone)->toDateString();
    $weekdays = app()->getLocale() === 'en' ? ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] : ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $leadingDays = app()->getLocale() === 'en' ? $calendar->dayOfWeek : ($calendar->dayOfWeek + 6) % 7;
@endphp
<div @if($variant === 'public') data-public-booking-calendar @endif class="rounded-2xl border border-stone-200 bg-white p-5" role="group" aria-label="{{ __('Appointment calendar') }}">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h3 class="text-lg font-bold text-stone-900">{{ localized_date($calendar, 'F Y') }}</h3>
        <div class="flex gap-2">
            @if($livewire)
                <button type="button" wire:click="previousMonth" aria-label="{{ __('Previous Month') }}" class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">←</button>
                <button type="button" wire:click="nextMonth" aria-label="{{ __('Next Month') }}" @disabled($variant === 'public' && !$canViewNextMonth) class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">→</button>
            @else
                <a href="{{ $previousUrl }}" aria-label="{{ __('Previous Month') }}" class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">←</a>
                <a href="{{ $nextUrl }}" aria-label="{{ __('Next Month') }}" class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">→</a>
            @endif
        </div>
    </div>
    @if($variant === 'public')<div data-public-month-grid>@endif
    <div class="mb-2 grid grid-cols-7 text-center text-xs font-bold text-stone-400">@foreach($weekdays as $day)<span>{{ __($day) }}</span>@endforeach</div>
    <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
        @for($blank = 0; $blank < $leadingDays; $blank++)<div class="aspect-square"></div>@endfor
        @for($day = 1; $day <= $calendar->daysInMonth; $day++)
            @php
                $date = $calendar->setDay($day)->toDateString();
                $available = !empty($slots[$date]) && $date >= $today;
            @endphp
            <button type="button" @disabled(!$available) aria-label="{{ localized_date($calendar->setDay($day), 'l, F j, Y') }}"
                @if($variant === 'public') aria-pressed="{{ $selectedDate === $date ? 'true' : 'false' }}" @if($livewire) wire:loading.attr="disabled" @endif @endif
                @if($livewire && $available) wire:click="selectDate('{{ $date }}')" @elseif($available) x-on:click="selectedDate = '{{ $date }}'; $dispatch('date-selected')" @endif
                @if(!$livewire) x-bind:class="selectedDate === '{{ $date }}' ? 'bg-terracotta-600 text-white' : 'bg-stone-50 text-stone-900'" @endif
                class="aspect-square rounded-2xl border text-sm font-semibold transition-colors {{ !$available ? 'cursor-not-allowed border-transparent text-stone-300' : ($livewire && $selectedDate === $date ? 'border-terracotta-600 bg-terracotta-600 text-white' : 'border-stone-200 hover:border-terracotta-500') }}">
                {{ $day }}@if($available)<span class="mx-auto mt-1 block h-1 w-1 rounded-full bg-terracotta-400"></span>@endif
            </button>
        @endfor
    </div>
    @if($variant === 'public')
        </div>
        @php
            $availableDates = collect($slots)->sortKeys()->filter(fn ($dateSlots, $date) => !empty($dateSlots) && $date >= $today && str_starts_with($date, $month.'-'))->keys();
        @endphp
        <div data-public-date-disclosure wire:key="booking-date-disclosure-{{ $month }}-{{ $timezone }}"
            x-data="{ visibleDates: 7, init() { const selected = this.$el.querySelector('[data-date-index][aria-pressed=true]'); if (selected) this.visibleDates = Math.max(7, Math.ceil((Number(selected.dataset.dateIndex) + 1) / 7) * 7); } }">
        <div id="booking-available-dates" data-public-date-list role="group" aria-label="{{ __('Available dates') }}">
            @foreach($availableDates as $date)
                    <button type="button" wire:key="booking-date-list-{{ $date }}" aria-label="{{ localized_date(\Carbon\CarbonImmutable::parse($date, $timezone), 'l, F j, Y') }}" aria-pressed="{{ $selectedDate === $date ? 'true' : 'false' }}"
                        data-date-index="{{ $loop->index }}" x-show="{{ $loop->index }} < visibleDates" @if($loop->index >= 7) x-cloak @endif
                        @if($livewire) wire:click="selectDate('{{ $date }}')" wire:loading.attr="disabled"
                        @else x-on:click="selectedDate = '{{ $date }}'; $dispatch('date-selected')" x-bind:aria-pressed="(selectedDate === '{{ $date }}').toString()"
                        @endif>
                        {{ localized_date(\Carbon\CarbonImmutable::parse($date, $timezone), 'l, F j, Y') }}
                    </button>
            @endforeach
        </div>
        @if($availableDates->count() > 7)
            <button type="button" data-show-more-dates x-cloak x-show="visibleDates < {{ $availableDates->count() }}"
                @click="const firstNewDate = visibleDates; visibleDates += 7; $nextTick(() => { if (!$el.getClientRects().length) $el.previousElementSibling.querySelectorAll('button')[firstNewDate]?.focus(); })" aria-controls="booking-available-dates" :aria-expanded="(visibleDates > 7).toString()"
                class="studio-button studio-button-secondary mt-4 w-full">{{ __('Show More Dates') }}</button>
        @endif
        </div>
    @endif
</div>

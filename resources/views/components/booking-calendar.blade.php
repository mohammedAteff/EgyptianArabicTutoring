@props(['month', 'timezone', 'slots', 'selectedDate' => null, 'livewire' => false, 'previousUrl' => null, 'nextUrl' => null])
@php
    $calendar = \Carbon\CarbonImmutable::createFromFormat('!Y-m', $month, $timezone);
    $today = now($timezone)->toDateString();
@endphp
<div class="rounded-2xl border border-stone-200 bg-white p-5" aria-label="Appointment calendar">
    <div class="mb-5 flex items-center justify-between gap-3">
        <h3 class="text-lg font-bold text-stone-900">{{ $calendar->format('F Y') }}</h3>
        <div class="flex gap-2">
            @if($livewire)
                <button type="button" wire:click="previousMonth" aria-label="Previous Month" class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">←</button>
                <button type="button" wire:click="nextMonth" aria-label="Next Month" class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">→</button>
            @else
                <a href="{{ $previousUrl }}" aria-label="Previous Month" class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">←</a>
                <a href="{{ $nextUrl }}" aria-label="Next Month" class="rounded-xl border border-stone-200 px-3 py-2 hover:bg-stone-50">→</a>
            @endif
        </div>
    </div>
    <div class="mb-2 grid grid-cols-7 text-center text-xs font-bold text-stone-400">@foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $day)<span>{{ __($day) }}</span>@endforeach</div>
    <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
        @for($blank = 0; $blank < $calendar->dayOfWeek; $blank++)<div class="aspect-square"></div>@endfor
        @for($day = 1; $day <= $calendar->daysInMonth; $day++)
            @php
                $date = $calendar->setDay($day)->toDateString();
                $available = !empty($slots[$date]) && $date >= $today;
            @endphp
            <button type="button" @disabled(!$available) aria-label="{{ $calendar->setDay($day)->format('l, F j, Y') }}"
                @if($livewire && $available) wire:click="selectDate('{{ $date }}')" @elseif($available) x-on:click="selectedDate = '{{ $date }}'; $dispatch('date-selected')" @endif
                @if(!$livewire) x-bind:class="selectedDate === '{{ $date }}' ? 'bg-terracotta-600 text-white' : 'bg-stone-50 text-stone-900'" @endif
                class="aspect-square rounded-2xl border text-sm font-semibold transition-colors {{ !$available ? 'cursor-not-allowed border-transparent text-stone-300' : ($livewire && $selectedDate === $date ? 'border-terracotta-600 bg-terracotta-600 text-white' : 'border-stone-200 hover:border-terracotta-500') }}">
                {{ $day }}@if($available)<span class="mx-auto mt-1 block h-1 w-1 rounded-full bg-terracotta-400"></span>@endif
            </button>
        @endfor
    </div>
</div>

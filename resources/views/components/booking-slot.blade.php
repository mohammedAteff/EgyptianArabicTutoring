@props(['appointment', 'timezone'])
@php
    $instant = \Carbon\CarbonImmutable::parse($appointment['slot_start_utc'], 'UTC');
    $display = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->formatSlotForDisplay($timezone, $instant);
    $businessTimezone = $appointment['business_timezone'] ?? app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone();
    $end = \Carbon\CarbonImmutable::parse($appointment['slot_end_utc'], 'UTC');
@endphp
<div data-booking-slot>
    <p class="text-sm font-bold text-stone-900">{{ $instant->setTimezone($timezone)->format('g:i A') }} – {{ $end->setTimezone($timezone)->format('g:i A') }}</p>
    <p class="mt-1 inline-flex flex-wrap items-center gap-1.5 text-xs text-stone-600"><x-timezone-flag :display="$display" :alt="__('Flag for :country', ['country' => $display['timezone_country_name']])" /> {{ $display['city'] }} · {{ $timezone }} · {{ $display['utc_offset'] }}</p>
    <p class="mt-1 text-xs text-stone-500">{{ __('Tutor equivalent: :start – :end', ['start' => $instant->setTimezone($businessTimezone)->format('g:i A'), 'end' => $end->setTimezone($businessTimezone)->format('g:i A')]) }} · {{ $businessTimezone }}</p>
</div>
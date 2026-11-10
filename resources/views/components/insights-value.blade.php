@props(['metric'])
@if($metric['value'] === null)
    {{ __('insights.states.unavailable') }}
@elseif($metric['unit'] === 'seconds')
    {{ intdiv($metric['value'], 3600) }}:{{ str_pad((string) intdiv($metric['value'] % 3600, 60), 2, '0', STR_PAD_LEFT) }}:{{ str_pad((string) ($metric['value'] % 60), 2, '0', STR_PAD_LEFT) }}
@else
    {{ Illuminate\Support\Number::format($metric['value'], locale: app()->getLocale()) }}
@endif

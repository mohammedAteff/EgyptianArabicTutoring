@props(['display', 'alt' => '', 'class' => 'w-4 h-3 object-cover rounded-sm'])

@if($display['flag_asset'])
    <img src="{{ asset($display['flag_asset']) }}" alt="{{ $alt }}" class="{{ $class }}">
@elseif($display['flag_symbol'])
    <span role="img" aria-label="{{ $alt ?: $display['timezone_country_name'] }}" class="inline-flex items-center justify-center leading-none {{ $class }}">{{ $display['flag_symbol'] }}</span>
@else
    <span aria-hidden="true" class="inline-flex items-center justify-center text-[9px] font-bold {{ $class }}">{{ $display['timezone_country_code'] ?? 'TZ' }}</span>
@endif

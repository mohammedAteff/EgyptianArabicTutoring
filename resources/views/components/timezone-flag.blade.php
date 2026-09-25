@props(['display', 'alt' => '', 'class' => 'w-4 h-3 object-cover rounded-sm'])

@php
    $flagAsset = $display['flag_asset'] ?? null;
    $hasTerritoryFlag = !empty($display['timezone_country_code'])
        && $flagAsset
        && !str_ends_with($flagAsset, '/globe.svg');
    $accessibleLabel = $hasTerritoryFlag ? $alt : __('World timezone without a specific territory flag');
@endphp

@if(!empty($display['flag_asset']))
    <img src="{{ asset($flagAsset) }}" alt="{{ $accessibleLabel }}" class="{{ $class }}">
@else
    <svg role="img" aria-label="{{ $accessibleLabel }}" class="{{ $class }}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" />
        <path d="M3 12h18M12 3c2.4 2.4 3.5 5.4 3.5 9S14.4 18.6 12 21c-2.4-2.4-3.5-5.4-3.5-9S9.6 5.4 12 3Z" stroke="currentColor" stroke-width="1.5" />
    </svg>
@endif

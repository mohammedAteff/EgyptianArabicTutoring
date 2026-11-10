@props(['text', 'value'])
@php
    $parts = preg_split('/((?<!\p{N})'.preg_quote((string) $value, '/').'(?!\p{N}))/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
@endphp
@foreach($parts as $index => $part)@if($index % 2 === 1)<strong class="studio-statistic-value">{{ $part }}</strong>@else{{ $part }}@endif@endforeach

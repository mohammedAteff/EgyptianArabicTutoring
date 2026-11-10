@props(['text'])
@php($parts = preg_split('/(\[CONFIRM:\s*[A-Z0-9-]+\])/u', $text, flags: PREG_SPLIT_DELIM_CAPTURE) ?: [$text])
<p {{ $attributes->class(['legal-paragraph']) }}>@foreach($parts as $part)@if(preg_match('/^\[CONFIRM:\s*([A-Z0-9-]+)\]$/u', $part, $marker))<span class="legal-review-marker">{{ __('To confirm') }} · {{ $marker[1] }}</span>@else{{ $part }}@endif@endforeach</p>

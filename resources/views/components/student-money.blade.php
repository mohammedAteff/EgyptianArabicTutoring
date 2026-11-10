@props(['amount', 'currency'])
<span class="student-money"><span>{{ localized_number($amount, 2) }}</span> <span>{{ $currency }}</span></span>

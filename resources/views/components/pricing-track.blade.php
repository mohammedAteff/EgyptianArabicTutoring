@props(['track', 'bookingUrl', 'sharedBenefits' => []])
<article {{ $attributes->class(['pricing-track']) }} aria-describedby="pricing-credit-conditions">
    <header class="pricing-track-heading">
        <h3>{{ $track['title'] }}</h3>
        <p class="pricing-track-summary">{{ $track['intro'][0] }}</p>
    </header>
    <div class="pricing-track-price">
        <p class="pricing-amount">${{ $track['price'] }}</p>
        <p>{{ website_text('pricing', 'Standard package price') }}</p>
    </div>
    <div class="pricing-track-facts">
        <dl>
            <div class="pricing-learning-facts">
                <dt class="sr-only">{{ website_text('pricing', 'Sessions and learning hours') }}</dt>
                <dd>{{ $track['subtitle'] }}<span>{{ website_text('pricing', '2 hours per session') }}</span></dd>
            </div>
            <div class="pricing-validity">
                <dt>{{ website_text('pricing', 'Validity:') }}</dt>
                <dd><strong>{{ $track['validity_days'] }} {{ __('calendar days') }}</strong><span> {{ __('from the first payment, including a partial payment.') }}</span></dd>
            </div>
        </dl>
        <p class="pricing-rate">${{ $track['session_rate'] }} {{ __('per session · $') }}{{ $track['hourly_rate'] }} {{ __('per hour') }}</p>
    </div>
    <div class="pricing-track-credit">
        <dl>
            <div><dt>{{ website_text('pricing', 'Eligible diagnostic credit') }}</dt><dd>−${{ $track['credit'] }}</dd></div>
            <div><dt>{{ website_text('pricing', 'Package balance after eligible credit') }}</dt><dd>${{ $track['remaining'] }}</dd></div>
        </dl>
        <p class="pricing-total">${{ $track['credit'] }} {{ __('paid for the diagnostic + $') }}{{ $track['remaining'] }} {{ __('remaining = $') }}{{ $track['price'] }} {{ __('total when eligible.') }}</p>
        <p class="pricing-track-eligibility">{{ website_text('pricing', 'If you purchase this coaching track within 48 hours after completing your diagnostic, the $25 already paid for the diagnostic is credited toward the standard package price.') }}</p>
    </div>
    <div class="pricing-track-details">
        @if($specificBenefits = array_diff(array_slice($track['benefits'], 1), $sharedBenefits))
            <ul class="pricing-benefits">@foreach($specificBenefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
        @endif
        <details class="studio-read-more pricing-track-description">
            <summary>{{ website_text('pricing', 'About this track') }}<span class="sr-only"> — {{ $track['title'] }}</span></summary>
            <div class="pricing-track-intro">
                <p>{{ $track['benefits'][0] }}</p>
                @foreach(array_slice($track['intro'], 1) as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            </div>
        </details>
    </div>
    <div class="pricing-track-action">
        <a href="{{ website_link('pricing', 'booking', $bookingUrl) }}" data-cta="booking" class="studio-button studio-button-secondary">{{ website_text('pricing', 'Start with the Diagnostic') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
    </div>
</article>

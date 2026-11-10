
@extends('layouts.public')
@section('body-class', 'studio-pricing-page')
@section('content')
<x-public-english-fallback :is-fallback="$isFallback ?? false" />
<div lang="{{ $copyLocale ?? 'en' }}" class="pricing-page">
    @php
        $tracks = config('public_content.tracks');
        $trackBenefits = array_column($tracks, 'benefits');
        $sharedBenefits = count($trackBenefits) > 1 && config('public_content.pricing.3._visible', true)
            ? array_values(array_intersect(...$trackBenefits)) : [];
        $diagnosticSection = config('public_content.pricing.1');
    @endphp
    @foreach(config('public_content.pricing') as $index => $section)
        @if(! ($section['_visible'] ?? true)) @continue @endif
        @if($index === 3)
            <div class="pricing-support-band">
        @elseif($index === 5)
            <div class="pricing-policy-band">
        @elseif($index === 6)
            <div class="pricing-refund-band">
        @endif
        <section @class(['pricing-section', 'pricing-hero' => $index === 0, 'pricing-start' => $index === 1, 'pricing-tracks' => $index === 2, 'pricing-support' => $index === 3, 'pricing-options' => $index === 4, 'pricing-arrangements' => $index === 5, 'pricing-questions' => $index === 6, 'pricing-invitation' => $index === 7]) id="pricing-section-{{ $index + 1 }}">
            @if($index === 3)<div class="pricing-support-intro">@endif
            <div class="pricing-heading">
                <p class="studio-eyebrow">{{ __($section['badge']) }}</p>
                @if($index === 0)<h1>{{ __($section['title']) }}</h1>@else<h2>{{ __($section['title']) }}</h2>@endif
            </div>
            @if($index === 3)
                <div class="pricing-lead"><x-public-copy-block :block="$section['blocks'][0]" /></div>
                </div>
            @endif
            @if($index === 0)
                <div class="pricing-hero-copy">
                    @foreach($section['blocks'] as $block)<x-public-copy-block :block="$block" />@endforeach
                </div>
            @elseif($index === 1)
                <div class="pricing-lead"><x-public-copy-block :block="$section['blocks'][0]" /></div>
                <div class="pricing-diagnostic">
                    <div class="pricing-diagnostic-offering">
                        <x-public-studio-illustration name="roadmap" class="pricing-roadmap" />
                        <x-public-copy-block :block="$section['blocks'][1]" />
                        <div class="pricing-diagnostic-price"><x-public-copy-block :block="$section['blocks'][2]" /></div>
                        <x-public-copy-block :block="$section['blocks'][3]" />
                        <a href="{{ website_link('pricing', 'booking', $bookingUrl) }}" data-cta="booking" data-primary-cta="diagnostic" class="studio-button">{{ website_text('pricing', 'Book Your Diagnostic') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                    </div>
                    <div class="pricing-diagnostic-details">
                        <ul class="pricing-diagnostic-desktop">
                            @foreach(array_slice($section['blocks'], 4, 4) as $block)<li><x-public-copy-block :block="$block" /></li>@endforeach
                        </ul>
                        <div class="pricing-diagnostic-mobile">
                            <ul class="pricing-diagnostic-outcomes">
                                @foreach(['Your goals and background.', 'A relaxed level assessment.', 'Your learning direction.', 'A personalized written roadmap.'] as $outcome)
                                    <li>{{ website_text('pricing', $outcome) }}</li>
                                @endforeach
                            </ul>
                            <p class="pricing-note pricing-diagnostic-roadmap">{{ website_text('pricing', 'Your written roadmap is prepared after the diagnostic.') }}</p>
                            <details class="studio-read-more pricing-diagnostic-process">
                                <summary>{{ website_text('pricing', 'Explore the diagnostic process') }}</summary>
                                <div>
                                    <ul>
                                        @foreach(array_slice($section['blocks'], 4, 4) as $block)<li><x-public-copy-block :block="$block" /></li>@endforeach
                                    </ul>
                                </div>
                            </details>
                        </div>
                        <div class="pricing-note"><x-public-copy-block :block="$section['blocks'][8]" /></div>
                    </div>
                </div>
                @foreach(array_slice($section['blocks'], 16) as $block)<x-public-copy-block :block="$block" />@endforeach
            @elseif($index === 2)
                <div class="pricing-lead">
                    @foreach($section['blocks'] as $block)<x-public-copy-block :block="$block" />@endforeach
                </div>
                <aside class="pricing-credit" aria-labelledby="pricing-credit-title">
                    <h3 id="pricing-credit-title">{{ $diagnosticSection['blocks'][9]['text'] }}</h3>
                    <div id="pricing-credit-conditions"><x-public-copy-block :block="$diagnosticSection['blocks'][10]" /></div>
                    <x-public-copy-block :block="$diagnosticSection['blocks'][15]" />
                    <details class="studio-read-more">
                        <summary>{{ website_text('pricing', 'See a credit example') }}</summary>
                        <div class="pricing-credit-example">
                            @foreach(array_slice($diagnosticSection['blocks'], 11, 4) as $block)<x-public-copy-block :block="$block" />@endforeach
                        </div>
                    </details>
                </aside>
                <div class="pricing-track-grid">
                    @foreach($tracks as $track)<x-pricing-track :track="$track" :booking-url="$bookingUrl" :shared-benefits="$sharedBenefits" />@endforeach
                </div>
            @elseif($index === 3)
                <div class="pricing-support-content">
                @if($sharedBenefits)
                    <ul class="pricing-benefits pricing-shared-benefits">@foreach($sharedBenefits as $benefit)<li>{{ $benefit }}</li>@endforeach</ul>
                @endif
                <details class="studio-read-more pricing-support-details">
                    <summary>{{ website_text('pricing', 'Explore lesson support and materials') }}</summary>
                    <div class="pricing-support-grid">
                        @foreach([1, 3, 5, 7, 9] as $blockIndex)
                            <div>
                                <x-public-copy-block :block="$section['blocks'][$blockIndex]" />
                                <x-public-copy-block :block="$section['blocks'][$blockIndex + 1]" />
                            </div>
                        @endforeach
                    </div>
                </details>
                @foreach(array_slice($section['blocks'], 11) as $block)<div class="pricing-note"><x-public-copy-block :block="$block" /></div>@endforeach
                </div>
            @elseif($index === 4)
                <div class="pricing-lead">
                    @foreach(array_slice($section['blocks'], 0, 2) as $block)<x-public-copy-block :block="$block" />@endforeach
                </div>
                <div class="pricing-option-list">
                    @foreach([2, 7] as $blockIndex)
                        <article class="pricing-option">
                            <div>
                                <x-public-copy-block :block="$section['blocks'][$blockIndex]" />
                                <div class="pricing-option-price"><x-public-copy-block :block="$section['blocks'][$blockIndex + 1]" /></div>
                                <div class="pricing-note"><x-public-copy-block :block="$section['blocks'][$blockIndex + 2]" /></div>
                            </div>
                            <div>
                                <x-public-copy-block :block="$section['blocks'][$blockIndex + 3]" />
                                <div class="pricing-option-eligibility"><x-public-copy-block :block="$section['blocks'][$blockIndex + 4]" /></div>
                            </div>
                        </article>
                    @endforeach
                </div>
                @foreach(array_slice($section['blocks'], 12) as $block)<div class="pricing-note"><x-public-copy-block :block="$block" /></div>@endforeach
                <p class="pricing-note">{{ app(\App\Domains\CMS\Services\WebsiteCopyService::class)->value('pricing', 'ui.3d78c6b253bebc371ace1d70d5cd3f0aaae4ff64', config('website_copy_text.pricing.3d78c6b253bebc371ace1d70d5cd3f0aaae4ff64')) }}</p>
            @elseif($index === 5)
                <div class="pricing-payment">
                    <div>
                        <x-public-copy-block :block="$section['blocks'][0]" />
                        @foreach(array_slice($section['blocks'], 1, 3) as $block)<x-public-copy-block :block="$block" />@endforeach
                    </div>
                    <div>
                        <x-public-copy-block :block="$section['blocks'][10]" />
                        @foreach(array_slice($section['blocks'], 11, 3) as $block)<x-public-copy-block :block="$block" />@endforeach
                    </div>
                </div>
                <div class="studio-faq-list pricing-disclosures">
                    <details>
                        <summary>{{ $section['blocks'][4]['text'] }}</summary>
                        <div class="pricing-disclosure-copy">
                            <p>{{ __('Package validity begins from the first payment, including a partial payment.') }}</p>
                            @foreach(array_slice($section['blocks'], 6, 4) as $block)<x-public-copy-block :block="$block" />@endforeach
                        </div>
                    </details>
                    <details>
                        <summary>{{ $section['blocks'][14]['text'] }}</summary>
                        <div class="pricing-disclosure-copy">
                            @foreach(array_slice($section['blocks'], 15, 14) as $block)<x-public-copy-block :block="$block" />@endforeach
                        </div>
                    </details>
                    <details>
                        <summary>{{ $section['blocks'][29]['text'] }}</summary>
                        <div class="pricing-disclosure-copy">
                            @foreach(array_slice($section['blocks'], 30) as $block)<x-public-copy-block :block="$block" />@endforeach
                        </div>
                    </details>
                </div>
                <a href="{{ website_link('pricing', 'policy', route('policy')) }}" class="studio-link pricing-policy-link">{{ website_text('pricing', 'Read the Full Booking, Cancellation & Refund Policy') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
            @elseif($index === 6)
                <div class="pricing-lead">
                    @foreach(array_slice($section['blocks'], 0, 2) as $block)<x-public-copy-block :block="$block" />@endforeach
                </div>
                <div class="studio-faq-list pricing-disclosures">
                    @foreach([[2, 12], [12, 16], [16, 18], [18, 25], [25, count($section['blocks'])]] as [$start, $end])
                        <details>
                            <summary>{{ $section['blocks'][$start]['text'] }}</summary>
                            <div class="pricing-disclosure-copy">
                                @foreach(array_slice($section['blocks'], $start + 1, $end - $start - 1) as $block)<x-public-copy-block :block="$block" />@endforeach
                            </div>
                        </details>
                    @endforeach
                </div>
                <a href="{{ website_link('pricing', 'policy', route('policy')) }}" class="studio-link pricing-policy-link">{{ website_text('pricing', 'Read the Full Booking, Cancellation & Refund Policy') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
            @elseif($index === 7)
                <div class="pricing-invitation-copy">
                    @foreach(array_slice($section['blocks'], 0, 2) as $block)<x-public-copy-block :block="$block" />@endforeach
                </div>
                <div class="pricing-invitation-action">
                    <div class="pricing-option-price"><x-public-copy-block :block="$section['blocks'][2]" /></div>
                    <a href="{{ website_link('pricing', 'booking', $bookingUrl) }}" data-cta="booking" class="studio-button">{{ website_text('pricing', 'Book Your Diagnostic') }} <x-public-studio-icon name="arrow" class="studio-link-arrow" /></a>
                    <x-public-copy-block :block="$section['blocks'][3]" />
                    <x-public-copy-block :block="config('public_content.pricing.1.blocks.10')" />
                    @foreach(array_slice($section['blocks'], 5) as $block)<x-public-copy-block :block="$block" />@endforeach
                </div>
            @endif
            @if(!empty($section['extra_content']))<div class="article-copy pricing-extra">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($section['extra_content']) !!}</div>@endif
        </section>
        @if(in_array($index, [3, 5, 6], true))</div>@endif
    @endforeach
</div>
@endsection

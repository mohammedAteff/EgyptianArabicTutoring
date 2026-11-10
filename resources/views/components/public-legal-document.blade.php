@props(['document', 'title', 'description', 'current', 'language' => null, 'version' => null, 'emptyMessage' => null])
@php($policies = ['terms' => 'Terms & Conditions', 'policy' => 'Booking & Refund Policy', 'privacy' => 'Privacy Policy'])
<article {{ $attributes->class(['legal-page']) }} lang="{{ app()->getLocale() }}" id="legal-top">
    <header class="legal-hero">
        <p class="studio-eyebrow">{{ __('Clear information, at every step') }}</p>
        <h1 lang="{{ $language ?? app()->getLocale() }}">{{ $title }}</h1>
        <p class="legal-description">{{ $description }}</p>
        @if($version)<p class="legal-version">{{ $version }}</p>@endif
        <nav class="legal-policy-links" aria-label="{{ __('Our policies') }}">
            @foreach($policies as $key => $label)<a href="{{ localized_url($key) }}" @if($key === $current) aria-current="page" @endif>{{ __($label) }}</a>@endforeach
        </nav>
    </header>

    @if($document['needsReview'])
        <aside class="legal-review-notice" aria-label="{{ __('Local review copy') }}">
            <strong>{{ __('Local review copy') }}</strong>
            <p>{{ __('This working copy is published locally for review. Highlighted details still need confirmation before release on the live website.') }}</p>
        </aside>
    @endif

    <div class="legal-layout">
        @if($document['sections'])
            <aside class="legal-sidebar">
                <nav class="legal-contents-desktop" aria-labelledby="legal-contents-label">
                    <h2 id="legal-contents-label">{{ __('On this page') }}</h2>
                    <ol>@foreach($document['sections'] as $section)<li><a href="#{{ $section['id'] }}"><span aria-hidden="true">{{ $section['number'] }}</span>{{ $section['title'] }}</a></li>@endforeach</ol>
                </nav>
                <details class="legal-contents-mobile">
                    <summary>{{ __('On this page') }}<span aria-hidden="true"></span></summary>
                    <nav aria-label="{{ __('Page sections') }}"><ol>@foreach($document['sections'] as $section)<li><a href="#{{ $section['id'] }}"><span aria-hidden="true">{{ $section['number'] }}</span>{{ $section['title'] }}</a></li>@endforeach</ol></nav>
                </details>
            </aside>
        @endif
        <div class="legal-reading" lang="{{ $language ?? app()->getLocale() }}">
            @if($document['introduction'])
                <div class="legal-introduction">
                    @foreach($document['introduction'] as $block)
                        @if($block['html'])<div class="legal-paragraph">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($block['text']) !!}</div>@else<x-public-legal-paragraph :text="$block['text']" />@endif
                    @endforeach
                </div>
            @endif
            @foreach($document['sections'] as $section)
                <section class="legal-section" id="{{ $section['id'] }}" aria-labelledby="{{ $section['id'] }}-heading" tabindex="-1">
                    <header><span class="legal-section-number" aria-hidden="true">{{ $section['number'] }}</span><h2 id="{{ $section['id'] }}-heading">{{ $section['title'] }}</h2></header>
                    <div class="legal-section-copy">
                        @foreach($section['blocks'] as $block)
                            @if($block['type'] === 'heading')<h3>{{ $block['text'] }}</h3>@elseif($block['html'])<div class="legal-paragraph">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($block['text']) !!}</div>@else<x-public-legal-paragraph :text="$block['text']" />@endif
                        @endforeach
                    </div>
                </section>
            @endforeach
            @if(!$document['introduction'] && !$document['sections'])<p class="legal-introduction" role="status">{{ $emptyMessage }}</p>@endif
            <a class="legal-back-top studio-link" href="#legal-top">{{ __('Back to top') }} <span aria-hidden="true">↑</span></a>
        </div>
    </div>

    <footer class="legal-help" lang="{{ app()->getLocale() }}">
        <div><p class="studio-eyebrow">{{ __('A little more guidance') }}</p><h2>{{ __('Questions before your next step?') }}</h2><p>{{ __('Find practical answers about lessons, packages and learning together.') }}</p></div>
        <div class="legal-help-links"><a class="studio-link" href="{{ localized_url('faq') }}">{{ __('Frequently Asked Questions') }} <span aria-hidden="true">→</span></a><a class="studio-link" href="{{ localized_url('pricing') }}">{{ __('Explore Lessons & Pricing') }} <span aria-hidden="true">→</span></a></div>
    </footer>
</article>

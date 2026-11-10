@props(['links'])
@if($links->isNotEmpty())
<div class="floating-social" data-floating-social>
    <nav class="floating-social-rail" aria-label="{{ __('Social channels') }}">
        @foreach($links as $social)
            <a class="floating-social-item" href="{{ $social->getFormattedUrl() }}" target="_blank" rel="noopener noreferrer" data-social-platform="{{ $social->platform }}" data-social-placement="floating_social" aria-label="{{ $social->label ?: ucfirst($social->platform) }}">
                <x-social-platform-icon :platform="$social->platform" /><span class="floating-social-label">{{ $social->label ?: ucfirst($social->platform) }}</span>
            </a>
        @endforeach
    </nav>
    <div class="floating-social-compact" hidden>
        <section class="floating-social-panel" id="floating-social-panel" aria-label="{{ __('Social channels') }}" hidden>
            <div class="floating-social-heading"><strong>{{ __('Social channels') }}</strong><button type="button" data-social-close aria-label="{{ __('Close social links') }}">×</button></div>
            <nav aria-label="{{ __('Social destinations') }}">
                @foreach($links as $social)
                    <a href="{{ $social->getFormattedUrl() }}" target="_blank" rel="noopener noreferrer" data-social-platform="{{ $social->platform }}" data-social-placement="floating_social"><x-social-platform-icon :platform="$social->platform" /><span>{{ $social->label ?: ucfirst($social->platform) }}</span></a>
                @endforeach
            </nav>
        </section>
        <button type="button" class="floating-social-launcher" aria-expanded="false" aria-controls="floating-social-panel" data-social-launcher>
            <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
            <span>{{ __('Follow us') }}</span>
        </button>
    </div>
</div>
@endif

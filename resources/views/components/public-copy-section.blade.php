@props(['section', 'level' => 2, 'showBlocks' => true, 'locale' => 'en', 'previewBlocks' => null, 'detailsLabel' => null])
@if($section['_visible'] ?? true)
<div {{ $attributes->class(['space-y-5']) }} lang="{{ $copyLocale ?? $locale }}">
    <p class="text-xs font-bold uppercase tracking-widest text-terracotta-600">{{ __($section['badge']) }}</p>
    @if($level === 1)
        <h1 class="text-4xl font-bold tracking-tight text-stone-900 sm:text-5xl lg:text-6xl">{{ __($section['title']) }}</h1>
    @else
        <h2 class="text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">{{ __($section['title']) }}</h2>
    @endif
    @if($showBlocks)
        <div class="space-y-4 text-base leading-relaxed text-stone-600 sm:text-lg">
            @foreach($previewBlocks === null ? $section['blocks'] : array_slice($section['blocks'], 0, $previewBlocks) as $block)
                <x-public-copy-block :block="$block" />
            @endforeach
            @if($previewBlocks !== null && count($section['blocks']) > $previewBlocks)
                <details class="studio-read-more">
                    <summary>{{ __($detailsLabel ?? __('Read more')) }}<span class="sr-only"> — {{ __($section['title']) }}</span></summary>
                    <div>
                        @foreach(array_slice($section['blocks'], $previewBlocks) as $block)
                            <x-public-copy-block :block="$block" />
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
    @endif
    @if(!empty($section['extra_content']))<div class="article-copy">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($section['extra_content']) !!}</div>@endif
</div>
@endif

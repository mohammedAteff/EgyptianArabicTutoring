@props(['section', 'level' => 2, 'showBlocks' => true, 'locale' => 'en'])
@if($section['_visible'] ?? true)
<div {{ $attributes->class(['space-y-5']) }} lang="{{ $copyLocale ?? $locale }}">
    <p class="text-xs font-bold uppercase tracking-widest text-terracotta-600">{{ $section['badge'] }}</p>
    @if($level === 1)
        <h1 class="text-4xl font-bold tracking-tight text-stone-900 sm:text-5xl lg:text-6xl">{{ $section['title'] }}</h1>
    @else
        <h2 class="text-3xl font-bold tracking-tight text-stone-900 sm:text-4xl">{{ $section['title'] }}</h2>
    @endif
    @if($showBlocks)
        <div class="space-y-4 text-base leading-relaxed text-stone-600 sm:text-lg">
            @foreach($section['blocks'] as $block)
                @if($block['type'] === 'heading')
                    <h3 class="pt-3 text-xl font-bold text-stone-900">{{ $block['text'] }}</h3>
                @elseif(preg_match('/<\/?[a-z][^>]*>/i', $block['text']))
                    <div class="article-copy">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($block['text']) !!}</div>
                @else
                    <p>{{ $block['text'] }}</p>
                @endif
            @endforeach
        </div>
    @endif
    @if(!empty($section['extra_content']))<div class="article-copy">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($section['extra_content']) !!}</div>@endif
</div>
@endif

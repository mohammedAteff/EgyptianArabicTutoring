@props(['block'])
@if($block['type'] === 'heading')
    <h3 class="pt-3 text-xl font-bold text-stone-900">{{ $block['text'] }}</h3>
@elseif(preg_match('/<\/?[a-z][^>]*>/i', $block['text']))
    <div class="article-copy">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($block['text']) !!}</div>
@else
    <p>{{ $block['text'] }}</p>
@endif

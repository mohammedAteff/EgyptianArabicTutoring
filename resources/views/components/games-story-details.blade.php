@props(['feature', 'copyLocale' => 'en'])
<div class="games-story" lang="{{ $copyLocale }}">
    @if(!empty($feature['features']))
        <div class="games-options">
            <div class="games-modes">
                @foreach(array_slice($feature['features'], 0, 2) as $option)
                    <div class="games-mode"><x-public-copy-block :block="is_array($option) ? $option : ['type' => 'paragraph', 'text' => $option]" /></div>
                @endforeach
            </div>
            @if(count($feature['features']) > 2)
                <div class="games-options-support">
                    @foreach(array_slice($feature['features'], 2) as $option)
                        <x-public-copy-block :block="is_array($option) ? $option : ['type' => 'paragraph', 'text' => $option]" />
                    @endforeach
                </div>
            @endif
        </div>
    @endif
    @if(!empty($feature['paragraphs']) || !empty($feature['blocks']) || !empty($feature['extra_content']))
        <details class="games-challenge-details">
            <summary>{{ website_text('games', 'More about the challenge') }}</summary>
            <div class="games-story-prose">
                @foreach($feature['paragraphs'] ?? [] as $paragraph)
                    <x-public-copy-block :block="is_array($paragraph) ? $paragraph : ['type' => 'paragraph', 'text' => $paragraph]" />
                @endforeach
                @foreach($feature['blocks'] ?? [] as $block)<x-public-copy-block :block="$block" />@endforeach
                @if(!empty($feature['extra_content']))<div class="article-copy">{!! app(\App\Domains\CMS\Services\RichTextSanitizer::class)->sanitize($feature['extra_content']) !!}</div>@endif
            </div>
        </details>
    @endif
</div>

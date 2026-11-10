@props(['blocks'])
@php
    $closing = [];
    if (($blocks[0]['type'] ?? null) === 'paragraph' && ($blocks[1]['type'] ?? null) === 'paragraph'
        && ($blocks[2]['type'] ?? null) === 'heading' && ($blocks[4]['type'] ?? null) === 'heading' && ($blocks[6]['type'] ?? null) === 'heading'
        && ($blocks[3]['type'] ?? null) === 'paragraph' && ($blocks[5]['type'] ?? null) === 'paragraph' && ($blocks[7]['type'] ?? null) === 'paragraph' && ($blocks[8]['type'] ?? null) === 'paragraph') {
        $closing = array_slice($blocks, 8);
        $blocks = array_slice($blocks, 0, 8);
    }
    $introduction = [];
    $groups = [];
    foreach($blocks as $block) {
        if (($block['type'] ?? null) === 'heading') {
            $groups[] = ['heading' => $block, 'blocks' => []];
        } elseif ($groups === []) {
            $introduction[] = $block;
        } else {
            $groups[array_key_last($groups)]['blocks'][] = $block;
        }
    }
@endphp
@if($introduction !== [])<div class="games-benefits-intro">@foreach($introduction as $block)<x-public-copy-block :block="$block" />@endforeach</div>@endif
@if($groups !== [])
    <ol class="games-benefit-grid" role="list">
        @foreach($groups as $group)
            <li class="games-benefit">
                <span class="games-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                <div><x-public-copy-block :block="$group['heading']" /><div class="games-benefit-body">@foreach($group['blocks'] as $block)<x-public-copy-block :block="$block" />@endforeach</div></div>
            </li>
        @endforeach
    </ol>
@endif
@if($closing !== [])<div class="games-benefits-close">@foreach($closing as $block)<x-public-copy-block :block="$block" />@endforeach</div>@endif

@props(['blocks', 'variant' => 'narrative', 'body' => null])
@inject('presenter', 'App\Domains\CMS\Services\AboutPresentationService')
@php
    ['introduction' => $introduction, 'groups' => $groups] = $presenter->present($blocks, $variant, $body);
@endphp
@if($variant === 'offer' && $groups === [])
    <div class="about-profile-offer-panel">
        <div class="about-profile-prose">
            @foreach($introduction as $html){!! $html !!}@endforeach
        </div>
        {{ $slot }}
    </div>
@else
    @if($introduction !== [])
        <div class="about-profile-prose about-profile-reading">
            @if($variant === 'introduction')
                <div class="about-profile-lead">{!! $introduction[0] !!}</div>
                <div class="about-profile-story">@foreach(array_slice($introduction, 1) as $html){!! $html !!}@endforeach</div>
            @else
                @foreach($introduction as $html){!! $html !!}@endforeach
            @endif
        </div>
    @endif
    @if($groups !== [])
        @if($variant === 'principles')
            <ol class="about-profile-principles" role="list">
                @foreach($groups as $group)
                    <li class="about-profile-principle">
                        <span class="about-profile-number" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                        <div>
                            <h3>{!! $group['heading'] !!}</h3>
                            <div class="about-profile-prose">
                                @foreach($group['blocks'] as $html){!! $html !!}@endforeach
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>
        @else
            <div @class(['about-profile-groups', 'about-profile-'.$variant.'-groups', 'about-profile-offer-panel' => $variant === 'offer'])>
                @foreach($groups as $group)
                    <section class="about-profile-group">
                        <h3>{!! $group['heading'] !!}</h3>
                        <div class="about-profile-prose">
                            @foreach($group['blocks'] as $html){!! $html !!}@endforeach
                        </div>
                    </section>
                @endforeach
                @if($variant === 'offer')
                    {{ $slot }}
                @endif
            </div>
        @endif
    @endif
@endif

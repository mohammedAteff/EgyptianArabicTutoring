@props(['measurement', 'aggregate' => false])
<section class="insights-panel min-w-0 space-y-4" aria-labelledby="estimated-learning-title">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <h2 id="estimated-learning-title" class="min-w-0 text-xl font-semibold">{{ __('activity.title') }}</h2>
        <p class="insights-muted text-sm">{{ __('activity.states.'.$measurement['state']) }}</p>
    </div>
    <p class="text-3xl font-semibold tabular-nums">@if($measurement['seconds'] !== null){{ App\Domains\Reporting\Services\TutorWorkReport::duration($measurement['seconds']) }} <span class="insights-muted text-sm font-normal">{{ __('activity.duration') }}</span>@else<span aria-hidden="true">—</span><span class="sr-only">{{ __('activity.states.unavailable') }}</span>@endif</p>
    @if($measurement['state'] === 'partial' && $measurement['collection'] === 'disabled')<p class="insights-muted text-sm">{{ __('activity.states.disabled') }}</p>@endif
    <p class="insights-muted text-sm">{{ __('activity.explanation') }}</p>
    <p class="insights-muted text-sm">{{ __('activity.distinction') }}</p>
    @if($aggregate && $measurement['seconds'] !== null)<p class="insights-muted text-sm">{{ trans_choice('activity.contributors', $measurement['contributors'], ['count' => $measurement['contributors']]) }}</p>@endif
    @if($measurement['overlap_seconds'] > 0)<p class="text-sm">{{ __('activity.overlap') }}: {{ App\Domains\Reporting\Services\TutorWorkReport::duration($measurement['overlap_seconds']) }}</p>@endif
    @if($measurement['interruptions'] > 0)<p class="insights-muted text-sm">{{ __('activity.interruptions') }}: {{ $measurement['interruptions'] }}</p>@endif
    <details>
        <summary class="insights-link min-h-11 cursor-pointer py-3">{{ __('activity.distribution') }}</summary>
        <ul class="mt-3 space-y-2 text-sm">@forelse($measurement['trends'] as $date => $seconds)<li class="flex flex-wrap justify-between gap-2"><span>{{ $date }}</span><strong class="tabular-nums">{{ App\Domains\Reporting\Services\TutorWorkReport::duration($seconds) }}</strong></li>@empty<li class="insights-muted">{{ __('activity.empty') }}</li>@endforelse</ul>
    </details>
</section>

@php
    $countersPayload = app(\App\Domains\Analytics\Services\EngagementCounterService::class)->getCachedPublicPayload();

    $hasActiveCounter = (
        ($countersPayload['live_users']['enabled'] ?? false) ||
        ($countersPayload['monthly_traffic']['enabled'] ?? false) ||
        ($countersPayload['learning_hours']['enabled'] ?? false)
    );
@endphp

@if($hasActiveCounter)
<div x-data="{ show: true }" x-show="show" x-transition.opacity class="studio-statistics w-full border-b py-2.5 px-4">
    <div class="studio-statistics-inner">
        @if(!empty($countersPayload['live_users']['enabled']))
            <div class="studio-statistic">
                <x-public-studio-icon name="activity" />
                <span><x-public-counter-text :text="$countersPayload['live_users']['text']" :value="localized_number($countersPayload['live_users']['count'])" /></span>
            </div>
        @endif
        @if(!empty($countersPayload['monthly_traffic']['enabled']))
            <div class="studio-statistic">
                <x-public-studio-icon name="traffic" />
                <span><x-public-counter-text :text="$countersPayload['monthly_traffic']['text']" :value="localized_number($countersPayload['monthly_traffic']['count'])" /></span>
            </div>
        @endif
        @if(!empty($countersPayload['learning_hours']['enabled']))
            <div class="studio-statistic">
                <x-public-studio-icon name="practice-time" />
                <div>
                    <span>{{ $countersPayload['learning_hours']['headline'] }}</span>
                    <strong class="studio-statistic-value ml-1">{{ $countersPayload['learning_hours']['formatted_time'] }}</strong>
                    <span class="ml-1">{{ $countersPayload['learning_hours']['subtitle'] }}</span>
                </div>
            </div>
        @endif
    </div>
</div>
@endif

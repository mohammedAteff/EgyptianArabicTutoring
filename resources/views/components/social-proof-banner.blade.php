@php
    $countersPayload = app(\App\Domains\Analytics\Services\EngagementCounterService::class)->getCachedPublicPayload();

    $hasActiveCounter = (
        ($countersPayload['live_users']['enabled'] ?? false) ||
        ($countersPayload['monthly_traffic']['enabled'] ?? false) ||
        ($countersPayload['learning_hours']['enabled'] ?? false)
    );
@endphp

@if($hasActiveCounter)
<div x-data="{ show: true }" x-show="show" x-transition.opacity class="w-full bg-linear-to-r from-amber-500/10 via-amber-600/10 to-amber-700/10 border-b border-amber-200/50 dark:border-amber-900/30 py-2.5 px-4">
    <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-center gap-6 sm:gap-8 text-xs font-medium text-slate-700 dark:text-slate-300">
        
        {{-- Counter 3: Live Users Online --}}
        @if(!empty($countersPayload['live_users']['enabled']))
            <div class="flex items-center gap-2">
                <span class="relative flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                </span>
                <span>{{ $countersPayload['live_users']['text'] }}</span>
            </div>
        @endif

        {{-- Counter 2: Monthly Traffic --}}
        @if(!empty($countersPayload['monthly_traffic']['enabled']))
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                </svg>
                <span>{{ $countersPayload['monthly_traffic']['text'] }}</span>
            </div>
        @endif

        {{-- Counter 1: Collective Learning Activity Hours --}}
        @if(!empty($countersPayload['learning_hours']['enabled']))
            <div class="flex items-center gap-2 text-center sm:text-left">
                <svg class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                    <span class="font-normal">{{ $countersPayload['learning_hours']['headline'] }}</span>
                    <span class="font-bold text-amber-900 dark:text-amber-300 ml-1">{{ $countersPayload['learning_hours']['formatted_time'] }}</span>
                    <span class="font-normal ml-1">{{ $countersPayload['learning_hours']['subtitle'] }}</span>
                </div>
            </div>
        @endif

    </div>
</div>
@endif

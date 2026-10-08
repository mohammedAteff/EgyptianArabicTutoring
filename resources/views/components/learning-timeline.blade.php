<details id="{{ $timelineId }}" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
    <summary class="min-h-11 cursor-pointer text-lg font-semibold">Recent learning & teaching</summary>
    <ol class="mt-4 space-y-4">@forelse($timelineItems->take(20) as $event)<li class="border-l-2 border-slate-200 pl-4"><p class="text-sm font-semibold">{{ $event['label'] }}</p><p dir="auto" class="mt-1 break-words text-sm text-slate-600">{{ $event['detail'] }}</p><time datetime="{{ $event['at']->toIso8601String() }}" class="mt-1 block text-xs text-slate-500">{{ $event['at']->timezone($timelineTimezone)->format('j M Y, H:i T') }}</time></li>@empty<li class="text-sm text-slate-500">Learning and delivered sessions will appear here as you progress.</li>@endforelse</ol>
</details>

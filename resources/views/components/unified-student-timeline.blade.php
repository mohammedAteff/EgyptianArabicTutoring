@props([
    'student',
    'timelineItems' => collect(),
    'timelineTimezone' => config('app.timezone', 'UTC'),
    'currentFilter' => 'all',
    'timelineId' => 'unified-student-timeline',
])

<details id="{{ $timelineId }}" class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6" open>
    <summary class="min-h-11 cursor-pointer text-lg font-bold text-slate-900 font-serif flex items-center justify-between">
        <span>Unified Student Timeline</span>
        <span class="text-xs font-sans font-semibold text-slate-500 bg-slate-100 px-2.5 py-1 rounded-full">{{ $timelineItems->count() }} events</span>
    </summary>

    <!-- Category Filters -->
    <div class="mt-4 flex flex-wrap gap-1.5 border-b border-slate-100 pb-3" role="tablist" aria-label="Timeline Filters">
        @foreach(['all' => 'All Events', 'learning' => 'Learning & LMS', 'billing' => 'Billing & Credits', 'bookings' => 'Bookings', 'notes' => 'Educational Notes'] as $filterKey => $filterLabel)
            <a href="?timeline_filter={{ $filterKey }}#{{ $timelineId }}"
               class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors {{ $currentFilter === $filterKey ? 'bg-amber-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                {{ $filterLabel }}
            </a>
        @endforeach
    </div>

    <!-- Timeline List -->
    <ol class="mt-5 space-y-4">
        @forelse($timelineItems as $event)
            <li class="relative border-l-2 border-slate-200 pl-4 py-1">
                <span class="absolute -left-1.5 top-2.5 h-3 w-3 rounded-full bg-slate-300 ring-4 ring-white"></span>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $event['badge_class'] ?? 'bg-slate-100 text-slate-700' }}">
                        {{ $event['label'] }}
                    </span>
                    <time datetime="{{ $event['at']->toIso8601String() }}" class="text-xs text-slate-500">
                        {{ $event['at']->timezone($timelineTimezone)->format('j M Y, H:i T') }}
                    </time>
                </div>
                <p dir="auto" class="mt-1.5 text-sm text-slate-700 font-medium break-words">
                    {{ $event['detail'] }}
                </p>
            </li>
        @empty
            <li class="py-4 text-center text-sm text-slate-500">
                No events recorded for this category.
            </li>
        @endforelse
    </ol>
</details>

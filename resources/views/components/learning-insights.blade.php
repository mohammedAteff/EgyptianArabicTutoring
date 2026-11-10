<div @class(['learning-insights mx-auto min-w-0 max-w-6xl space-y-7', 'learning-insights--student' => ! $admin])>
    <header class="space-y-3">
        @if($admin && $subject)<a class="insights-link" href="{{ route($baseRoute, array_diff_key($context->filters(), ['student_id' => true])) }}">← {{ __('insights.all_students') }}</a>@endif
        <p class="insights-eyebrow">{{ __('insights.recorded_overview') }}</p>
        <h1 class="text-3xl font-semibold">{{ $admin ? 'Student Insights' : __('insights.title') }}</h1>
        <p class="insights-muted max-w-3xl">{{ __('insights.intro') }}</p>
        @if($admin)<p class="insights-muted">@if($subject){{ $subject->first_name }} {{ $subject->last_name }} · Student #{{ $subject->id }}@else{{ __('insights.population', ['count' => $population]) }}@endif</p>@endif
    </header>

    @if($errors->any())<div role="alert" class="insights-panel space-y-2"><h2 class="font-semibold">{{ __('insights.filter_error') }}</h2>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

    @if($admin)
        @php
            $reportFilters = array_filter($context->filters(), fn ($value) => $value !== null);
            $reportFields = [
                'range' => [__('insights.range'), collect(['today', '7d', '30d', '90d', 'month', 'last_month', 'custom'])->mapWithKeys(fn ($range) => [$range => __('insights.ranges.'.$range)])->all()],
                'category' => [__('insights.category'), collect(['sessions', 'homework', 'lms'])->mapWithKeys(fn ($category) => [$category => __('insights.categories.'.$category)])->all()],
                'timezone' => [__('insights.timezone'), 'text'],
                'start_date' => [__('insights.start_date'), 'date'],
                'end_date' => [__('insights.end_date'), 'date'],
            ];
            if (! $subject) {
                $reportFilters['q'] = request('q');
                $reportFields['q'] = [__('insights.search'), 'text'];
            }
            if (in_array(request('dataset'), ['activity_records', 'estimated_allocations'], true)) {
                $reportFilters['dataset'] = request('dataset');
            }
            $reportFields['dataset'] = ['Export content', ['activity_records' => 'Contributing records', 'estimated_allocations' => 'Estimated time allocations']];
        @endphp
        <x-report-filters :filters="$reportFilters" :fields="$reportFields" :action="route($baseRoute)" export-route="admin.student-insights.export" :fixed="$subject ? ['student_id' => $subject->id] : []" :default-labels="['category' => __('insights.categories.all'), 'dataset' => 'Summary']" />
    @else
    <details class="insights-panel insights-disclosure insights-filters" @if($context->range === 'custom' || $errors->any()) open @endif>
        <summary><div><h2 class="text-xl font-semibold">{{ __('insights.filters') }}</h2><p class="insights-muted text-sm">{{ __('insights.ranges.'.$context->range) }} · {{ __('insights.categories.'.$context->category) }} · {{ $context->timezone }}</p></div><x-student-portal-icon name="chevron" /></summary>
    <form method="GET" action="{{ route($baseRoute) }}" class="insights-panel insights-filter-form space-y-4" aria-label="{{ __('insights.filters') }}">
        @if($admin && $subject)<input type="hidden" name="student_id" value="{{ $subject->id }}">@endif
        @unless($admin)<input type="hidden" name="lang" value="{{ app()->getLocale() }}">@endunless
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <label class="insights-label">{{ __('insights.range') }}<select name="range" class="insights-control">@foreach(['today', '7d', '30d', '90d', 'month', 'last_month', 'custom'] as $range)<option value="{{ $range }}" @selected($context->range === $range)>{{ __('insights.ranges.'.$range) }}</option>@endforeach</select></label>
            <label class="insights-label">{{ __('insights.category') }}<select name="category" class="insights-control">@foreach(['all', 'sessions', 'homework', 'lms'] as $category)<option value="{{ $category }}" @selected($context->category === $category)>{{ __('insights.categories.'.$category) }}</option>@endforeach</select></label>
            <label class="insights-label">{{ __('insights.timezone') }}<input name="timezone" class="insights-control" value="{{ $context->timezone }}" maxlength="100" required spellcheck="false" autocapitalize="none" aria-describedby="insights-timezone-help"></label>
            <label class="insights-label">{{ __('insights.start_date') }}<input type="date" name="start_date" class="insights-control" value="{{ $context->range === 'custom' ? $context->startDate : '' }}"></label>
            <label class="insights-label">{{ __('insights.end_date') }}<input type="date" name="end_date" class="insights-control" value="{{ $context->range === 'custom' ? $context->endDate : '' }}"></label>
            @if($admin && ! $subject)<label class="insights-label">{{ __('insights.search') }}<input name="q" class="insights-control" value="{{ request('q') }}" maxlength="100"></label>@endif
        </div>
        <p id="insights-timezone-help" class="insights-muted text-sm">{{ __('insights.timezone_help') }} {{ __('insights.custom_help') }}</p>
        <div class="flex flex-wrap items-center gap-3"><button class="insights-button insights-button-primary">{{ __('insights.apply') }}</button><a class="insights-button" href="{{ route($baseRoute, array_filter(['student_id' => $admin ? $subject?->id : null, 'lang' => $admin ? null : app()->getLocale()])) }}">{{ __('insights.reset') }}</a></div>
    </form>
    </details>
    @endif

    <div class="insights-muted space-y-2 text-sm">
        <p><strong>{{ $context->startDate }} – {{ $context->endDate }}</strong> · {{ $context->timezone }} · {{ __('insights.categories.'.$context->category) }}</p>
        <p>{{ __('insights.generated') }} {{ $context->generatedAt->setTimezone($context->timezone)->format('Y-m-d H:i:s T') }}. {{ __('insights.live_report') }}</p>
        @if($context->until->gt($context->generatedAt))<p>{{ __('insights.incomplete') }}</p>@endif
    </div>

    <section aria-labelledby="insights-period-title" class="space-y-4">
        <h2 id="insights-period-title" class="text-xl font-semibold">{{ __('insights.period_summary') }}</h2>
        <dl class="insights-summary-grid grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach(collect($metrics)->filter(fn ($metric, $key) => in_array($key, ['sessions_completed', 'session_duration_seconds', 'homework_items_submitted', 'lessons_first_completed', 'recorded_learning_days', 'students_with_recorded_activity'], true))->take(4) as $metric)
            <div class="insights-panel"><dt class="insights-muted text-sm">{{ __('insights.metrics.'.$metric['id']) }}</dt><dd class="mt-3 text-3xl font-semibold"><x-insights-value :metric="$metric" /></dd><dd class="insights-muted mt-2 text-sm">{{ trans_choice('insights.units.'.$metric['unit'], $metric['value'] ?? 0) }}@if($metric['state'] !== 'recorded') · {{ __('insights.states.'.$metric['state']) }}@endif</dd></div>
            @endforeach
        </dl>
        <p class="insights-muted text-sm">{{ __('insights.duration_note') }}</p>
    </section>

    <x-estimated-learning :measurement="$estimatedActivity" :aggregate="$context->studentId === null" />
    @unless($admin)<div class="insights-detail-grid">@endunless
    <x-insights-section :collapsible="! $admin" id="insights-comparison-title" :title="__('insights.comparison')">
        <p class="insights-muted text-sm">{{ __('insights.previous_period') }}: {{ $previousContext->startDate }} – {{ $previousContext->endDate }}. {{ __('insights.comparison_note') }}</p>
        <div class="insights-table-scroll" tabindex="0" role="region" aria-label="{{ __('insights.comparison') }}"><table class="insights-table"><thead><tr><th scope="col">{{ __('insights.measure') }}</th><th scope="col">{{ __('insights.selected') }}</th><th scope="col">{{ __('insights.previous') }}</th></tr></thead><tbody>
            @foreach($metrics as $id => $metric)<tr><th scope="row">{{ __('insights.metrics.'.$id) }}<small class="insights-muted block">{{ trans_choice('insights.units.'.$metric['unit'], $metric['value'] ?? 0) }}@if($metric['reliability'] === 'B') · {{ __('insights.timing_qualified') }}@endif</small></th><td><x-insights-value :metric="$metric" />@if($metric['state'] === 'partial')<small class="block">{{ __('insights.states.partial') }}</small>@endif @if($metric['excluded_invalid_durations'])<small class="block">{{ trans_choice('insights.excluded_durations', $metric['excluded_invalid_durations']) }}</small>@endif</td><td><x-insights-value :metric="$previousMetrics[$id]" /></td></tr>@endforeach
        </tbody></table></div>
    </x-insights-section>

    <x-insights-section :collapsible="! $admin" id="insights-trends-title" :title="__('insights.trends')">
        <p class="insights-muted text-sm">{{ $context->days() > 90 ? __('insights.monthly') : __('insights.daily') }}. {{ __('insights.trend_note') }}</p>
        @php($trendMax = max(1, collect($trends)->max('records') ?? 1))
        <div class="insights-chart" aria-hidden="true">@foreach($trends as $bucket)<span style="--bar-height: {{ max(2, (int) round(100 * $bucket['records'] / $trendMax)) }}%" @class(['insights-bar', 'insights-bar-empty' => ! $bucket['records']])></span>@endforeach</div>
        <details><summary class="insights-link">{{ __('insights.trend_details') }}</summary><div class="insights-table-scroll" tabindex="0" role="region" aria-label="{{ __('insights.trend_details') }}"><table class="insights-table"><thead><tr><th>{{ __('insights.date') }}</th><th>{{ __('insights.qualifying_records') }}</th><th>{{ $subject ? __('insights.metrics.recorded_learning_days') : __('insights.metrics.students_with_recorded_activity') }}</th></tr></thead><tbody>@foreach($trends as $bucket)<tr><th scope="row">{{ $bucket['bucket'] }}</th><td>{{ $bucket['records'] }}</td><td>{{ $subject ? $bucket['days'] : $bucket['students'] }}</td></tr>@endforeach</tbody></table></div></details>
    </x-insights-section>

    <x-insights-section :collapsible="! $admin" :open="request()->filled('month')" id="insights-calendar-title" :title="__('insights.calendar').' · '.Carbon\CarbonImmutable::parse($context->month.'-01')->locale(app()->getLocale())->translatedFormat('F Y')">
        <nav class="flex flex-wrap gap-2" aria-label="{{ __('insights.calendar_navigation') }}">@foreach(['previous', 'next'] as $direction)@if($calendar[$direction])<a class="insights-button" href="{{ route($baseRoute, array_filter($context->filters() + ['month' => $calendar[$direction], 'lang' => $admin ? null : app()->getLocale()], fn ($v, $k) => $v !== null && ($admin || $k !== 'student_id'), ARRAY_FILTER_USE_BOTH)) }}#insights-calendar-title">{{ __('insights.'.$direction.'_month') }}</a>@endif @endforeach</nav>
        <p class="insights-muted text-sm">{{ __('insights.calendar_note') }}</p>
        <div class="insights-calendar" role="list" aria-label="{{ __('insights.calendar') }}">
            @foreach(['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $weekday)<div class="insights-weekday" aria-hidden="true">{{ __('insights.weekdays.'.$weekday) }}</div>@endforeach
            @for($blank = 0; $blank < $calendar['offset']; $blank++)<div aria-hidden="true"></div>@endfor
            @foreach($calendar['days'] as $day)<div class="insights-day insights-day-{{ $day['state'] }}" role="listitem"><span aria-hidden="true">{{ $day['number'] }}@if($day['state'] === 'activity')<span class="insights-day-marker">●</span>@endif</span><span class="sr-only">{{ $day['date'] }}: {{ __('insights.calendar_states.'.$day['state']) }}@if($day['state'] === 'activity'), {{ trans_choice('insights.record_count', $day['count']) }}@endif</span></div>@endforeach
        </div>
        <details><summary class="insights-link">{{ __('insights.calendar_details') }}</summary><ul class="mt-3 space-y-2 text-sm">@foreach($calendar['days'] as $day)<li>{{ $day['date'] }} · {{ __('insights.calendar_states.'.$day['state']) }}@if($day['state'] === 'activity') · {{ trans_choice('insights.record_count', $day['count']) }}@endif @if(isset($estimatedActivity['days'][$day['date']])) · {{ __('activity.calendar') }}: {{ App\Domains\Reporting\Services\TutorWorkReport::duration($estimatedActivity['days'][$day['date']]) }}@endif</li>@endforeach</ul></details>
    </x-insights-section>

    @if($current['states'] || ($subject && in_array($context->category, ['all', 'lms'], true)))
    <x-insights-section :collapsible="! $admin" :panel="false" id="insights-current-title" :title="__('insights.current_title')"><p class="insights-muted text-sm">{{ __('insights.current_note') }}</p>
        <dl class="insights-panel grid gap-5 sm:grid-cols-2">@foreach($current['states'] as $id => $value)<div class="flex items-start justify-between gap-4"><dt>{{ __('insights.current_metrics.'.$id) }}</dt><dd class="font-semibold">{{ $value }}</dd></div>@endforeach</dl>
        @if($subject && in_array($context->category, ['all', 'lms'], true))<div class="space-y-3"><h3 class="font-semibold">{{ __('insights.course_progress') }}</h3>@forelse($current['courses'] as $course)<article class="insights-panel space-y-3"><h4 class="font-semibold">{{ $course['title'] }}</h4><p>{{ __('insights.course_status.'.$course['status']) }} · {{ __('insights.required_progress', ['completed' => $course['completed'], 'required' => $course['required']]) }} · {{ $course['percent'] === null ? __('insights.not_applicable') : $course['percent'].'%' }}</p>@unless($admin)<a class="insights-link" href="{{ $course['url'] }}">{{ __('insights.open_course') }}</a>@endunless</article>@empty<p class="insights-panel insights-muted">{{ __('insights.no_current_courses') }}</p>@endforelse</div>@endif
        @unless($admin)<div class="flex flex-wrap gap-3">@if(in_array($context->category, ['all', 'homework'], true))<a class="insights-link" href="{{ route('student.teaching.index') }}">{{ __('Practice & Feedback') }}</a>@endif @if(in_array($context->category, ['all', 'lms'], true))<a class="insights-link" href="{{ route('student.learning.index') }}">{{ __('My Learning') }}</a>@endif</div>@endunless
    </x-insights-section>@endif
    @unless($admin)</div>@endunless

    @if($admin)
    @if(isset($students))<section class="space-y-4"><h2 class="text-xl font-semibold">Students · {{ $students->total() }}</h2><p class="insights-muted text-sm">Current retained students, including suspended and unverified accounts and students without course access. Deleted and merged-away accounts are excluded. Search narrows this list only; the population summary above remains unchanged.</p>@foreach($students as $member)<article class="insights-panel space-y-3"><a class="insights-link" href="{{ route($baseRoute, array_replace($context->filters(), ['student_id' => $member->id])) }}">{{ $member->first_name }} {{ $member->last_name }} · #{{ $member->id }}</a><dl class="grid gap-3 sm:grid-cols-2">@foreach($studentMetrics[$member->id] as $id => $metric)<div class="flex justify-between gap-4 text-sm"><dt>{{ __('insights.metrics.'.$id) }}</dt><dd><x-insights-value :metric="$metric" /></dd></div>@endforeach</dl></article>@endforeach {{ $students->links() }}</section>@endif
    @if(isset($records))<section class="space-y-4"><h2 class="text-xl font-semibold">Contributing records</h2><p class="insights-muted text-sm">Safe record references only. Historical content may be unavailable; no authored messages or assessment answers are included.</p>@forelse($records as $record)<article class="insights-panel space-y-2 text-sm"><p class="font-semibold">{{ __('insights.record_types.'.$record->record_type) }} #{{ $record->record_id }}</p><p>{{ $record->local_date }} @if($record->occurred_at) · {{ Carbon\CarbonImmutable::parse($record->occurred_at, 'UTC')->setTimezone($context->timezone)->format('H:i:s T') }}@endif · {{ $record->status }}</p><p>Item #{{ $record->item_id }}@if($record->course_id) · Course #{{ $record->course_id }} · Content unavailable in this report @endif</p><p class="insights-muted break-words">{{ App\Domains\Reporting\Services\StudentInsightsExport::metricIds($record->record_type) }}</p>@if($record->record_type === 'session')<p>{{ $record->interval_start }} – {{ $record->interval_end }} UTC · {{ $record->duration_seconds ?? 'Unavailable' }} seconds</p>@endif</article>@empty<p class="insights-panel">No qualifying records for this context.</p>@endforelse {{ $records->links() }}</section>@endif
    @endif

    @unless($admin)<details class="insights-panel insights-disclosure insights-about"><summary><h2 class="font-semibold">{{ __('insights.about') }}</h2><x-student-portal-icon name="chevron" /></summary>@endunless
    <aside class="insights-muted space-y-3 border-t pt-5 text-sm" aria-label="{{ __('insights.about') }}">@if($admin)<h2 class="font-semibold">{{ __('insights.about') }}</h2>@endif<p>{{ __('insights.coverage_note') }}</p><p>{{ __('insights.homework_note') }}</p><p>{{ __('insights.completion_note') }}</p><p>{{ __('insights.not_study_time') }}</p><p>{{ __('insights.version') }} {{ $context::VERSION }}</p></aside>
    @unless($admin)</details>@endunless
</div>

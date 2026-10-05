@extends('layouts.admin')
@section('content')
<div class="space-y-6">
    <div>
        <p class="mb-1 text-xs font-semibold text-amber-700">Insights &amp; Telemetry / Maintenance Mode</p>
        <h1 class="text-2xl font-black tracking-tight text-slate-900">Maintenance Mode Analytics</h1>
        <p class="mt-1 text-sm text-slate-500">Visitor traffic intercepted while maintenance mode was active.</p>
    </div>
    <x-report-filters :filters="$filters" :fields="array_merge(app(\App\Domains\Reporting\Services\ReportPeriod::class)->fields(), ['country'=>['Country code','text'], 'path'=>['Page / path contains','search']])" :action="route('admin.analytics.maintenance')" export-route="admin.analytics.maintenance.export" />
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach(['Total Intercepted Hits' => number_format($maintenanceHitsCount), 'Unique Visitors' => number_format($maintenanceUniqueVisitors), 'Maintenance Bounce Rate' => number_format($maintenanceBounceRate, 1).'%'] as $label => $value)
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs"><span class="text-xs font-bold uppercase tracking-wider text-slate-500">{{ $label }}</span><div class="mt-2 text-2xl font-black text-slate-900">{{ $value }}</div></div>
        @endforeach
    </div>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
        <h2 class="mb-4 font-bold text-slate-900">Country Breakdown</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm text-slate-600">
            <thead class="border-b border-slate-200 bg-slate-50 text-xs text-slate-500"><tr><th class="px-4 py-3">Country / Region</th><th class="px-4 py-3">Code</th><th class="px-4 py-3">Hits</th><th class="px-4 py-3">Unique Visitors</th><th class="px-4 py-3">Bounce Rate</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($maintenanceCountries as $country)
                <tr><td class="px-4 py-3"><span class="inline-flex items-center gap-2"><img src="{{ $country['flag_url'] }}" alt="" class="h-3.5 w-5 rounded-xs border border-slate-200 object-cover"><span>{{ $country['country_name'] }}</span></span></td><td class="px-4 py-3 font-mono">{{ $country['country_code'] }}</td><td class="px-4 py-3">{{ number_format($country['hits']) }}</td><td class="px-4 py-3">{{ number_format($country['visitors']) }}</td><td class="px-4 py-3">{{ $country['bounce_rate'] }}%</td></tr>
            @empty<tr><td colspan="5" class="p-6 text-center text-slate-400">No intercepted traffic in this period.</td></tr>@endforelse</tbody>
        </table></div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-xs sm:p-6">
        <h2 class="mb-4 font-bold text-slate-900">Traffic Details</h2>
        <div class="overflow-x-auto"><table class="w-full text-left text-xs text-slate-600">
            <thead class="border-b border-slate-200 bg-slate-50 text-slate-500"><tr><th class="p-3">Timestamp ({{ $businessTimezone }})</th><th class="p-3">Country</th><th class="p-3">Requested Page / Path</th><th class="p-3">Referrer / Source</th></tr></thead>
            <tbody class="divide-y divide-slate-100">@forelse($maintenanceRows as $visit)
                <tr><td class="whitespace-nowrap p-3">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime(\Carbon\CarbonImmutable::parse($visit->created_at, 'UTC'), $businessTimezone) }}</td><td class="p-3"><span class="inline-flex items-center gap-2"><img src="{{ $visit->flag_url }}" alt="" class="h-3.5 w-5 rounded-xs border border-slate-200 object-cover"><span>{{ $visit->country_name }} ({{ $visit->country_code }})</span></span></td><td class="max-w-xs break-words p-3">{{ $visit->url }}</td><td class="max-w-xs break-words p-3">{{ $visit->referrer ?: 'Direct / None' }}</td></tr>
            @empty<tr><td colspan="4" class="p-6 text-center text-slate-400">No matching visits.</td></tr>@endforelse</tbody>
        </table></div>
        <div class="mt-4">{{ $maintenanceRows->links() }}</div>
    </section>
</div>
@endsection

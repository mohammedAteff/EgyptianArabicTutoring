@extends('layouts.admin')

@section('content')
<div class="space-y-8">

    <!-- Top Header & Time Range Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.analytics') }}" class="text-xs font-semibold text-slate-500 hover:text-amber-600 transition-colors">Analytics & Funnels</a>
                <span class="text-xs text-slate-400">/</span>
                <span class="text-xs font-semibold text-amber-700">Section Attention</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Content & Section Attention Panel</h1>
            <p class="text-sm text-slate-500 mt-1">Granular telemetry tracking element visibility, dwell duration, and drop-off rate across page sections.</p>
        </div>

    </div>

    <!-- Section Attention Table -->
<x-report-filters :filters="$filters" :fields="array_merge(app(\App\Domains\Reporting\Services\ReportPeriod::class)->fields(), ['section'=>['Section ID contains','search']])" :action="route('admin.analytics.sections')" export-route="admin.analytics.sections.export" />
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
        <div class="mb-6">
            <h2 class="text-base font-bold text-slate-900">Tracked Sections & Dwell Metrics</h2>
            <p class="text-xs text-slate-500 mt-0.5">Section views fire on &ge;50% viewport visibility. Dwell seconds accrue exclusively to the dominant visible section. Drop-off uses the last observed section; active sessions are provisional. Rates use retained session evidence; — means unavailable. Drop-off is the last observed section in a session.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Section ID</th>
                        <th class="py-3 px-4">Page Template</th>
                        <th class="py-3 px-4 text-right">Total Views</th>
                        <th class="py-3 px-4 text-right">Total Dwell Time</th>
                        <th class="py-3 px-4 text-right">Avg Attention (s)</th>
                        <th class="py-3 px-4 text-right">Entry Bounce Rate (%)</th>
                        <th class="py-3 px-4 text-right">Drop-off Rate (%)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($sections as $row)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900">
                                #{{ $row['section_id'] }}
                            </td>
                            <td class="py-3 px-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold
                                    {{ $row['page_template'] === 'landing' ? 'bg-amber-100 text-amber-800' :
                                       ($row['page_template'] === 'blog' ? 'bg-blue-100 text-blue-800' :
                                       ($row['page_template'] === 'resource' ? 'bg-emerald-100 text-emerald-800' : 'bg-purple-100 text-purple-800')) }}">
                                    {{ ucfirst($row['page_template']) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-slate-900">
                                {{ number_format($row['total_views']) }}
                            </td>
                            <td class="py-3 px-4 text-right text-slate-700">
                                @php
                                    $dwellSec = (int) $row['total_dwell_seconds'];
                                    $minutes = floor($dwellSec / 60);
                                    $seconds = $dwellSec % 60;
                                    $dwellFormatted = $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
                                @endphp
                                <span title="{{ $dwellSec }} seconds">{{ $dwellFormatted }}</span>
                            </td>
                            <td class="py-3 px-4 text-right font-semibold text-slate-800">
                                {{ $row['avg_attention_duration'] }}s
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-amber-700">
                                {{ $row['entry_bounce_rate'] === null ? '—' : $row['entry_bounce_rate'].'%' }}
                            </td>
                            <td class="py-3 px-4 text-right font-medium {{ $row['drop_off_rate'] > 60 ? 'text-rose-600' : 'text-slate-700' }}">
                                {{ $row['drop_off_rate'] === null ? '—' : $row['drop_off_rate'].'%' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400 italic">
                                No section attention data recorded for this time period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

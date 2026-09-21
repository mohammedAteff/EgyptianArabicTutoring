@extends('layouts.admin')

@section('content')
<div class="space-y-8">

    <!-- Top Header & Time Range Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Visitor Analytics & Business Funnels</h1>
            <p class="text-sm text-slate-500 mt-1">First-party telemetry measuring real student acquisition and conversion actions.</p>
        </div>

        <!-- Range Pills -->
        <div class="inline-flex bg-slate-200/80 p-1 rounded-xl text-xs font-semibold text-slate-700">
            <a href="{{ route('admin.analytics', ['range' => 'today']) }}" class="px-3 py-1.5 rounded-lg {{ $range === 'today' ? 'bg-white shadow-xs font-bold text-amber-700' : 'hover:text-slate-900' }}">Today</a>
            <a href="{{ route('admin.analytics', ['range' => '7d']) }}" class="px-3 py-1.5 rounded-lg {{ $range === '7d' ? 'bg-white shadow-xs font-bold text-amber-700' : 'hover:text-slate-900' }}">7 Days</a>
            <a href="{{ route('admin.analytics', ['range' => '30d']) }}" class="px-3 py-1.5 rounded-lg {{ $range === '30d' ? 'bg-white shadow-xs font-bold text-amber-700' : 'hover:text-slate-900' }}">30 Days</a>
            <a href="{{ route('admin.analytics', ['range' => 'month']) }}" class="px-3 py-1.5 rounded-lg {{ $range === 'month' ? 'bg-white shadow-xs font-bold text-amber-700' : 'hover:text-slate-900' }}">This Month</a>
            <a href="{{ route('admin.analytics', ['range' => '90d']) }}" class="px-3 py-1.5 rounded-lg {{ $range === '90d' ? 'bg-white shadow-xs font-bold text-amber-700' : 'hover:text-slate-900' }}">90 Days</a>
        </div>
    </div>

    <!-- Active Now Spotlight -->
    <div class="bg-gradient-to-br from-slate-900 via-slate-850 to-slate-900 rounded-2xl p-6 text-white shadow-md border border-slate-800">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-6 pb-6 border-b border-slate-800">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-400 text-xs font-semibold uppercase tracking-wider mb-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                    Live Pulse (5-Minute Activity Window)
                </div>
                <div class="flex items-baseline gap-3">
                    <span class="text-4xl font-black tracking-tight text-white">{{ $activeVisitorsCount }}</span>
                    <span class="text-sm text-slate-400 font-medium">Estimated active visitors</span>
                </div>
                <p class="text-xs text-slate-500 mt-1">Based on non-bot HTTP interactions within the rolling active window.</p>
            </div>
            <div class="text-right">
                <a href="{{ route('admin.reports.index', ['type' => 'traffic']) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-xl text-xs font-semibold text-slate-200 transition-colors">
                    <span>Inspect Raw Traffic Report</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
            </div>
        </div>

        <!-- Recent Active Stream -->
        <div class="mt-4">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-3">Recent Active Visitors (Non-PII Telemetry)</h4>
            @if($activeVisitors->isEmpty())
                <p class="text-xs text-slate-500 italic">No public visitors recorded in the last 5 minutes.</p>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($activeVisitors as $item)
                        <div class="p-3 bg-slate-800/60 rounded-xl border border-slate-700/50 flex flex-col justify-between text-xs">
                            <div class="flex items-center justify-between text-slate-400 mb-1">
                                <span class="font-mono text-amber-400">{{ $item['visitor_token'] }}</span>
                                <span class="text-[11px]">{{ $item['minutes_ago'] === 0 ? 'Just now' : $item['minutes_ago'] . 'm ago' }}</span>
                            </div>
                            <div class="truncate font-medium text-slate-200" title="{{ $item['page'] }}">
                                {{ parse_url($item['page'], PHP_URL_PATH) ?: '/' }}
                            </div>
                            <div class="text-[11px] text-slate-400 mt-1">
                                Source: <span class="text-slate-300">{{ $item['source'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <!-- Primary Booking Conversion Funnel -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h2 class="text-base font-bold text-slate-900">Primary Business Funnel: Website → Confirmed Lesson</h2>
                <div class="flex items-center gap-3 mt-0.5">
                    <p class="text-xs text-slate-500">Authoritative drop-off analysis between booking intent and completion.</p>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold {{ ($primaryFunnel['is_mature'] ?? false) ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800' }}">
                        {{ $primaryFunnel['maturity_label'] ?? 'Immature / In-Progress' }}
                    </span>
                </div>
            </div>
            <div class="px-3 py-1 rounded-lg bg-amber-50 text-amber-800 text-xs font-bold border border-amber-200">
                Overall Conversion: {{ $primaryFunnel['overall_conversion'] }}%
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 relative">
            <!-- Step 1: Unique Visitors -->
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-150 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Stage 1</span>
                    <div class="text-xs font-semibold text-slate-800 mt-0.5">Unique Visitors</div>
                </div>
                <div class="mt-4">
                    <div class="text-2xl font-black text-slate-900">{{ number_format($primaryFunnel['visitors']) }}</div>
                    <div class="text-[11px] text-slate-600 mt-1">100% baseline</div>
                </div>
            </div>

            <!-- Step 2: Booking CTA Reached / Qualified -->
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-150 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Stage 2</span>
                    <div class="text-xs font-semibold text-slate-800 mt-0.5">Booking CTA Reached / Qualified</div>
                </div>
                <div class="mt-4">
                    <div class="text-2xl font-black text-slate-900">{{ number_format($primaryFunnel['cta_qualified'] ?? $primaryFunnel['cta_clicked']) }}</div>
                    <div class="text-[11px] text-amber-700 font-semibold mt-1">{{ $primaryFunnel['cta_rate'] }}% of visitors</div>
                    <div class="text-[10px] text-slate-500 mt-1 flex items-center justify-between">
                        <span>Observed: {{ number_format($primaryFunnel['cta_observed'] ?? 0) }}</span>
                        <span>Imputed: {{ number_format($primaryFunnel['cta_imputed'] ?? 0) }}</span>
                    </div>
                </div>
            </div>

            <!-- Step 3: Booking Started -->
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-150 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Stage 3</span>
                    <div class="text-xs font-semibold text-slate-800 mt-0.5">Wizard Step 1 Reached</div>
                </div>
                <div class="mt-4">
                    <div class="text-2xl font-black text-slate-900">{{ number_format($primaryFunnel['booking_started']) }}</div>
                    <div class="text-[11px] text-amber-700 font-semibold mt-1">{{ $primaryFunnel['start_rate'] }}% from CTA</div>
                </div>
            </div>

            <!-- Step 4: Slot Held -->
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-150 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-600">Stage 4</span>
                    <div class="text-xs font-semibold text-slate-800 mt-0.5">Slot Selected & Held</div>
                </div>
                <div class="mt-4">
                    <div class="text-2xl font-black text-slate-900">{{ number_format($primaryFunnel['slot_held']) }}</div>
                    <div class="text-[11px] text-amber-700 font-semibold mt-1">{{ $primaryFunnel['hold_rate'] }}% progression</div>
                </div>
            </div>

            <!-- Step 5: Booking Completed -->
            <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-200 flex flex-col justify-between">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-700">Stage 5</span>
                    <div class="text-xs font-bold text-emerald-950 mt-0.5">Booking Confirmed</div>
                </div>
                <div class="mt-4">
                    <div class="text-2xl font-black text-emerald-900">{{ number_format($primaryFunnel['booking_completed']) }}</div>
                    <div class="text-[11px] text-emerald-800 font-bold mt-1">{{ $primaryFunnel['complete_rate'] }}% final conversion</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Funnels: Resources & Games -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        <!-- Resource Gate Funnel -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 mb-1">Resource Lead Capture Funnel</h3>
            <p class="text-xs text-slate-500 mb-4">Email-gated curriculum downloads converting anonymous visitors into leads.</p>

            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <span class="font-medium text-slate-700">1. Gate Viewed</span>
                    <span class="font-black text-slate-900">{{ number_format($resourceFunnel['gate_viewed']) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <div>
                        <span class="font-medium text-slate-700">2. Lead Email Submitted</span>
                        <span class="text-[11px] text-amber-700 ml-2 font-semibold">({{ $resourceFunnel['request_rate'] }}%)</span>
                    </div>
                    <span class="font-black text-slate-900">{{ number_format($resourceFunnel['requested']) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-emerald-50/70 border border-emerald-200 text-xs">
                    <div>
                        <span class="font-bold text-emerald-900">3. Material Downloaded</span>
                        <span class="text-[11px] text-emerald-800 ml-2 font-bold">({{ $resourceFunnel['download_rate'] }}%)</span>
                    </div>
                    <span class="font-black text-emerald-950">{{ number_format($resourceFunnel['downloaded']) }}</span>
                </div>
            </div>
        </div>

        <!-- Game Engagement Funnel -->
        <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
            <h3 class="text-sm font-bold text-slate-900 mb-1">Interactive Game Engagement</h3>
            <p class="text-xs text-slate-500 mb-4">Vocabulary drills and audio games engaging students.</p>

            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <span class="font-medium text-slate-700">1. Game Page Opened</span>
                    <span class="font-black text-slate-900">{{ number_format($gameFunnel['opened']) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                    <span class="font-medium text-slate-700">2. Game Play Started</span>
                    <span class="font-black text-slate-900">{{ number_format($gameFunnel['started']) }}</span>
                </div>
                <div class="flex items-center justify-between p-3 rounded-xl bg-amber-50/70 border border-amber-200 text-xs">
                    <div>
                        <span class="font-bold text-amber-900">3. Game Completed</span>
                        <span class="text-[11px] text-amber-800 ml-2 font-bold">({{ $gameFunnel['completion_rate'] }}%)</span>
                    </div>
                    <span class="font-black text-amber-950">{{ number_format($gameFunnel['completed']) }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Audience Activity by Detected Country -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
        <div class="flex items-start justify-between gap-4 mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">Audience Activity by Detected Country</h3>
                <p class="text-xs text-slate-500 mt-0.5">Country dimensions are separate: visitors use immutable acquisition country, sessions use session-start country, and events/bookings use their server snapshot. ZZ means unresolved.</p>
            </div>
            <span class="text-[11px] text-slate-400 whitespace-nowrap">Africa/Cairo calendar days</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">Country</th>
                        <th class="py-3 px-4 text-right">Unique active visitors</th>
                        <th class="py-3 px-4 text-right">Sessions</th>
                        <th class="py-3 px-4 text-right">Booking CTAs</th>
                        <th class="py-3 px-4 text-right">Completed bookings</th>
                        <th class="py-3 px-4 text-right">Resource requests</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($countryActivity as $row)
                        @php
                            $country = strtoupper((string) $row->country_code);
                            $countryName = app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)
                                ->resolveCountryName($country === 'ZZ' ? null : $country);
                            $flagPath = $country !== 'ZZ' && is_file(public_path('assets/flags/4x3/'.strtolower($country).'.svg'))
                                ? asset('assets/flags/4x3/'.strtolower($country).'.svg')
                                : asset('assets/flags/4x3/globe.svg');
                        @endphp
                        <tr>
                            <td class="py-3 px-4 font-semibold text-slate-800">
                                <span class="inline-flex items-center gap-2">
                                    <img src="{{ $flagPath }}" alt="{{ $countryName }}" class="w-5 h-3.5 object-cover rounded-sm">
                                    {{ $countryName }} ({{ $country }})
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-medium">{{ number_format((int) $row->unique_visitors) }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format((int) $row->sessions) }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format((int) $row->booking_cta_clicks) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-emerald-700">{{ number_format((int) $row->bookings_completed) }}</td>
                            <td class="py-3 px-4 text-right">{{ number_format((int) $row->resource_requests) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-8 text-center text-slate-400 italic">No country rollups have been generated for this period yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- UTM Acquisition & Traffic Attribution -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900">UTM Attribution & Campaign Performance</h3>
                <p class="text-xs text-slate-500 mt-0.5">Which channels and content pieces drove actual student conversions.</p>
            </div>
            <a href="{{ route('admin.reports.index', ['type' => 'traffic']) }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                Full Traffic Breakdown →
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">UTM Source</th>
                        <th class="py-3 px-4">UTM Medium</th>
                        <th class="py-3 px-4">UTM Campaign</th>
                        <th class="py-3 px-4 text-right">Unique Visitors</th>
                        <th class="py-3 px-4 text-right">Page Views</th>
                        <th class="py-3 px-4 text-right">Resource Leads</th>
                        <th class="py-3 px-4 text-right">Bookings</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($acquisition as $row)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-semibold text-slate-800">{{ $row->source }}</td>
                            <td class="py-3 px-4 font-mono text-[11px]">{{ $row->medium }}</td>
                            <td class="py-3 px-4 font-mono text-[11px]">{{ $row->campaign }}</td>
                            <td class="py-3 px-4 text-right font-medium">{{ number_format($row->visitors) }}</td>
                            <td class="py-3 px-4 text-right text-slate-500">{{ number_format($row->page_views) }}</td>
                            <td class="py-3 px-4 text-right font-semibold text-amber-700">{{ number_format($row->leads) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-emerald-700">{{ number_format($row->bookings) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 italic">No attribution telemetry recorded for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Operational Reports & Exports</h1>
            <p class="text-sm text-slate-500 mt-1">Five authoritative operational datasets with period comparison and formula-safe CSV/XLSX export.</p>
        </div>

        <!-- Export Buttons -->
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.reports.export', array_merge(request()->query(), ['type' => $reportType, 'format' => 'csv'])) }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-white hover:bg-slate-50 border border-slate-300 rounded-xl text-xs font-bold text-slate-700 shadow-xs transition-colors">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export CSV</span>
            </a>
            <a href="{{ route('admin.reports.export', array_merge(request()->query(), ['type' => $reportType, 'format' => 'xlsx'])) }}" 
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-emerald-700 hover:bg-emerald-800 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span>Export XLSX</span>
            </a>
        </div>
    </div>

    <!-- 5 Fixed Reports Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-slate-200 overflow-x-auto pb-px text-xs font-semibold">
        <a href="{{ route('admin.reports.index', ['type' => 'traffic', 'range' => $range]) }}" 
           class="px-4 py-2.5 border-b-2 transition-colors whitespace-nowrap {{ $reportType === 'traffic' ? 'border-amber-600 text-amber-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
            1. Traffic & Visitors
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'bookings', 'range' => $range]) }}" 
           class="px-4 py-2.5 border-b-2 transition-colors whitespace-nowrap {{ $reportType === 'bookings' ? 'border-amber-600 text-amber-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
            2. Bookings Ledger
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'resources', 'range' => $range]) }}" 
           class="px-4 py-2.5 border-b-2 transition-colors whitespace-nowrap {{ $reportType === 'resources' ? 'border-amber-600 text-amber-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
            3. Resource Downloads & Conversion
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'social', 'range' => $range]) }}" 
           class="px-4 py-2.5 border-b-2 transition-colors whitespace-nowrap {{ $reportType === 'social' ? 'border-amber-600 text-amber-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
            4. Social & Messaging Clicks
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'events', 'range' => $range]) }}" 
           class="px-4 py-2.5 border-b-2 transition-colors whitespace-nowrap {{ $reportType === 'events' ? 'border-amber-600 text-amber-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
            5. Raw Events Log
        </a>
        <a href="{{ route('admin.reports.index', ['type' => 'campaigns', 'range' => $range]) }}" 
           class="px-4 py-2.5 border-b-2 transition-colors whitespace-nowrap {{ $reportType === 'campaigns' ? 'border-amber-600 text-amber-900 font-bold' : 'border-transparent text-slate-500 hover:text-slate-900 hover:border-slate-300' }}">
            6. Campaign & Content Attribution
        </a>
    </div>

    <!-- Date Range & Period Filter Bar -->
    <div class="flex flex-wrap items-center justify-between gap-4 p-4 bg-white rounded-2xl border border-slate-200 shadow-xs">
        <div class="flex items-center gap-2">
            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Date Period:</span>
            <div class="inline-flex bg-slate-100 p-0.5 rounded-xl text-xs font-medium text-slate-600">
                <a href="{{ route('admin.reports.index', array_merge(request()->query(), ['range' => '7d'])) }}" class="px-2.5 py-1 rounded-lg {{ $range === '7d' ? 'bg-white shadow-xs font-bold text-slate-900' : 'hover:text-slate-900' }}">7 Days</a>
                <a href="{{ route('admin.reports.index', array_merge(request()->query(), ['range' => '30d'])) }}" class="px-2.5 py-1 rounded-lg {{ $range === '30d' ? 'bg-white shadow-xs font-bold text-slate-900' : 'hover:text-slate-900' }}">30 Days</a>
                <a href="{{ route('admin.reports.index', array_merge(request()->query(), ['range' => 'this_month'])) }}" class="px-2.5 py-1 rounded-lg {{ $range === 'this_month' ? 'bg-white shadow-xs font-bold text-slate-900' : 'hover:text-slate-900' }}">This Month</a>
                <a href="{{ route('admin.reports.index', array_merge(request()->query(), ['range' => 'last_month'])) }}" class="px-2.5 py-1 rounded-lg {{ $range === 'last_month' ? 'bg-white shadow-xs font-bold text-slate-900' : 'hover:text-slate-900' }}">Last Month</a>
            </div>
        </div>

        <div class="text-xs text-slate-500">
            Active Scope: <span class="font-bold text-slate-700">{{ $start->format('M d, Y') }} — {{ $end->format('M d, Y') }}</span>
        </div>
    </div>

    <!-- REPORT CONTENT BASED ON TYPE -->

    <!-- REPORT 1: TRAFFIC -->
    @if($reportType === 'traffic')
        <div class="space-y-6">
            <!-- Period Comparison KPI Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">
                        @if(!empty($reportData['summary']['visitors_is_daily_sum']))
                            Sum of Daily Unique Visitors
                        @else
                            Unique Visitors
                        @endif
                    </div>
                    <div class="mt-2 flex items-baseline gap-3">
                        <span class="text-3xl font-black text-slate-900">{{ number_format($reportData['summary']['visitors']) }}</span>
                        @if(!empty($reportData['summary']['is_comparable_visitors']) && isset($reportData['summary']['visitor_change_pct']))
                            <span class="text-xs font-semibold {{ $reportData['summary']['visitor_change_pct'] >= 0 ? 'text-emerald-700' : 'text-rose-700' }}">
                                {{ $reportData['summary']['visitor_change_pct'] >= 0 ? '+' : '' }}{{ $reportData['summary']['visitor_change_pct'] }}% vs prev period
                            </span>
                        @else
                            <span class="text-[11px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded" title="Non-comparable: periods have different counting bases">
                                Non-comparable
                            </span>
                        @endif
                    </div>
                    <div class="text-[11px] text-slate-600 mt-1">
                        Previous period: {{ number_format($reportData['summary']['prev_visitors']) }}
                        @if(!empty($reportData['summary']['prev_visitors_is_daily_sum']))
                            <span class="text-slate-500">(sum of daily uniques)</span>
                        @endif
                    </div>
                    @if(!empty($reportData['summary']['visitors_is_daily_sum']))
                        <div class="text-[11px] text-amber-700 mt-1.5 font-medium leading-tight">
                            Raw events pruned across multi-day range; total represents sum of daily uniques rather than deduplicated visitors.
                        </div>
                    @endif
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Sessions</div>
                    <div class="mt-2 flex items-baseline gap-3">
                        <span class="text-3xl font-black text-slate-900">{{ number_format($reportData['summary']['sessions']) }}</span>
                    </div>
                    <div class="text-[11px] text-slate-600 mt-1">Previous period: {{ number_format($reportData['summary']['prev_sessions']) }}</div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Page Views</div>
                    <div class="mt-2 flex items-baseline gap-3">
                        <span class="text-3xl font-black text-slate-900">{{ number_format($reportData['summary']['page_views']) }}</span>
                    </div>
                    <div class="text-[11px] text-slate-600 mt-1">Previous period: {{ number_format($reportData['summary']['prev_page_views']) }}</div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4">Unique Visitors</th>
                                <th class="py-3 px-4">Sessions</th>
                                <th class="py-3 px-4">Page Views</th>
                                <th class="py-3 px-4">Top Acquisition Source</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reportData['rows'] as $row)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-mono font-medium text-slate-800">{{ $row->report_date }}</td>
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ number_format($row->visitors) }}</td>
                                    <td class="py-3 px-4">{{ number_format($row->sessions) }}</td>
                                    <td class="py-3 px-4">{{ number_format($row->page_views) }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 font-mono text-[11px]">{{ $row->top_source }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic">No traffic recorded in this date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- REPORT 2: BOOKINGS -->
    @elseif($reportType === 'bookings')
        <div class="space-y-6">
            <div class="flex items-center gap-4 bg-white p-4 rounded-2xl border border-slate-200 text-xs text-slate-600 shadow-xs">
                <span>Total in period: <strong class="text-slate-900">{{ $reportData['count'] }}</strong></span>
                <span>•</span>
                <span>Confirmed: <strong class="text-emerald-700">{{ $reportData['confirmed_count'] }}</strong></span>
                <span>•</span>
                <span>Completed: <strong class="text-blue-700">{{ $reportData['completed_count'] }}</strong></span>
                <span>•</span>
                <span>Cancelled: <strong class="text-rose-700">{{ $reportData['cancelled_count'] }}</strong></span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Booking</th>
                                <th class="py-3 px-4">Student</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4">Date & Time (Cairo)</th>
                                <th class="py-3 px-4">Student Local Time</th>
                                <th class="py-3 px-4">Source</th>
                                <th class="py-3 px-4">Campaign</th>
                                <th class="py-3 px-4">Content</th>
                                <th class="py-3 px-4">Touch Time</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reportData['rows'] as $b)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-amber-700">{{ $b['booking_code'] }}</td>
                                    <td class="py-3 px-4">
                                        <div class="font-bold text-slate-900">{{ $b['customer_name'] }}</div>
                                        <div class="text-[11px] text-slate-600">{{ $b['customer_email'] }}</div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider
                                            {{ $b['status'] === 'Confirmed' ? 'bg-emerald-100 text-emerald-800' : ($b['status'] === 'Completed' ? 'bg-blue-100 text-blue-800' : 'bg-slate-100 text-slate-700') }}">
                                            {{ $b['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 font-medium text-slate-800">
                                        {{ $b['lesson_date_cairo'] }} at {{ $b['lesson_time_cairo'] }}
                                    </td>
                                    <td class="py-3 px-4 text-slate-600">
                                        {{ $b['lesson_time_student'] }}
                                    </td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 font-mono text-[11px]">{{ $b['source'] }}</span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-[11px]">{{ $b['campaign'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500">{{ $b['content'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500">{{ $b['touch_at'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="py-8 text-center text-slate-400 italic">No bookings recorded in this date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- REPORT 3: RESOURCES -->
    @elseif($reportType === 'resources')
        <div class="space-y-6">
            <div class="flex items-center gap-4 bg-white p-4 rounded-2xl border border-slate-200 text-xs text-slate-600 shadow-xs">
                <span>Total Lead Inquiries in period: <strong class="text-slate-900">{{ number_format($reportData['total_requests']) }}</strong></span>
                <span>•</span>
                <span>Total Material Downloads: <strong class="text-emerald-700">{{ number_format($reportData['total_downloads']) }}</strong></span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Resource Workbook</th>
                                <th class="py-3 px-4">Format</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-right">Email Requests</th>
                                <th class="py-3 px-4 text-right">Downloads</th>
                                <th class="py-3 px-4 text-right">Conversion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reportData['rows'] as $r)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $r['title'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px]">{{ $r['file_type'] }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-700">
                                            {{ $r['status'] }}
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-semibold text-slate-800">{{ number_format($r['requests']) }}</td>
                                    <td class="py-3 px-4 text-right font-bold text-emerald-700">{{ number_format($r['downloads']) }}</td>
                                    <td class="py-3 px-4 text-right font-black text-amber-700">{{ $r['conversion_pct'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-slate-400 italic">No resource download activity found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- REPORT 4: SOCIAL & MESSAGING -->
    @elseif($reportType === 'social')
        <div class="space-y-6">
            <div class="flex items-center gap-4 bg-white p-4 rounded-2xl border border-slate-200 text-xs text-slate-600 shadow-xs">
                <span>Total Messaging Clicks: <strong class="text-slate-900">{{ number_format($reportData['total_clicks']) }}</strong></span>
                <span>•</span>
                <span>WhatsApp: <strong class="text-emerald-700">{{ number_format($reportData['whatsapp_clicks']) }}</strong></span>
                <span>•</span>
                <span>Telegram: <strong class="text-blue-700">{{ number_format($reportData['telegram_clicks']) }}</strong></span>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Platform</th>
                                <th class="py-3 px-4">Event</th>
                                <th class="py-3 px-4">Originating Page</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4 text-right">Click Count</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reportData['rows'] as $s)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-bold text-slate-900">{{ $s['platform'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500">{{ $s['event_name'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600 truncate max-w-xs">{{ $s['page'] }}</td>
                                    <td class="py-3 px-4 font-mono text-slate-800">{{ $s['date'] }}</td>
                                    <td class="py-3 px-4 text-right font-black text-amber-700">{{ number_format($s['clicks']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic">No social or direct messaging clicks recorded in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    <!-- REPORT 5: EVENTS LOG -->
    @elseif($reportType === 'events')
        <div class="space-y-6">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Event Name</th>
                                <th class="py-3 px-4">Page</th>
                                <th class="py-3 px-4">Source</th>
                                <th class="py-3 px-4">Date</th>
                                <th class="py-3 px-4 text-right">Count</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reportData['rows'] as $e)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $e->event_name }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-slate-600 truncate max-w-xs">{{ $e->page }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 font-mono text-[11px]">{{ $e->source }}</span>
                                    </td>
                                    <td class="py-3 px-4 font-mono text-slate-800">{{ $e->report_date }}</td>
                                    <td class="py-3 px-4 text-right font-black text-slate-900">{{ number_format($e->event_count) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-8 text-center text-slate-400 italic">No events found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <!-- REPORT 6: CAMPAIGN & CONTENT ATTRIBUTION -->
    @if($reportType === 'campaigns')
        <div class="space-y-6">
            <!-- Period Comparison KPI Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Active Campaigns</div>
                    <div class="mt-2 text-3xl font-black text-slate-900">{{ number_format($reportData['total_campaigns']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Distinct campaign tags</div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Campaign Visitors</div>
                    <div class="mt-2 text-3xl font-black text-slate-900">{{ number_format($reportData['total_visitors']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Acquired through marketing</div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Downstream Bookings</div>
                    <div class="mt-2 text-3xl font-black text-amber-700">{{ number_format($reportData['total_bookings']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Total bookings attributed</div>
                </div>

                <div class="bg-white p-5 rounded-2xl border border-slate-200 shadow-xs">
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-500">Confirmed / Completed</div>
                    <div class="mt-2 text-3xl font-black text-emerald-700">{{ number_format($reportData['total_confirmed']) }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Successful conversions</div>
                </div>
            </div>

            <!-- Granular Content Drilldown Table -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-200 bg-slate-50/50 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700">Granular Content Attribution (Section 19)</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">Campaign → Content → Visitor Count → Downstream Bookings</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                            <tr>
                                <th class="py-3 px-4">Campaign</th>
                                <th class="py-3 px-4">Content (Ad / Creative / Post)</th>
                                <th class="py-3 px-4">Channel / Source</th>
                                <th class="py-3 px-4 text-right">Unique Visitors</th>
                                <th class="py-3 px-4 text-right">Total Bookings</th>
                                <th class="py-3 px-4 text-right">Confirmed / Completed</th>
                                <th class="py-3 px-4 text-right">Conversion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse($reportData['rows'] as $row)
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="py-3 px-4 font-mono font-bold text-slate-900">{{ $row['campaign'] }}</td>
                                    <td class="py-3 px-4 font-mono text-[11px] text-amber-800 font-semibold">{{ $row['content'] }}</td>
                                    <td class="py-3 px-4">
                                        <span class="px-2 py-0.5 rounded-md bg-slate-100 font-mono text-[11px]">{{ $row['source'] }}</span>
                                    </td>
                                    <td class="py-3 px-4 text-right font-medium text-slate-800">{{ number_format($row['visitors_count']) }}</td>
                                    <td class="py-3 px-4 text-right font-medium text-slate-800">{{ number_format($row['bookings_count']) }}</td>
                                    <td class="py-3 px-4 text-right font-bold text-emerald-700">{{ number_format($row['confirmed_bookings']) }}</td>
                                    <td class="py-3 px-4 text-right font-mono font-bold {{ $row['conversion_rate'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                        {{ $row['conversion_rate'] }}%
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="py-8 text-center text-slate-400 italic">No campaign-attributed activity in this date range.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection

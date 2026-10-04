@extends('layouts.admin')

@section('content')
<p class="mb-4 text-xs text-slate-500">Country totals use the current business timezone. Older aggregates from other timezones are preserved separately; periods without retained raw activity or matching aggregates may be incomplete. Multi-day visitor totals from pruned history sum daily unique visitors.</p>
<div class="space-y-8">

    <!-- Top Header & Time Range Filter -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('admin.analytics') }}" class="text-xs font-semibold text-slate-500 hover:text-amber-600 transition-colors">Analytics & Funnels</a>
                <span class="text-xs text-slate-400">/</span>
                <span class="text-xs font-semibold text-amber-700">Country Breakdown</span>
            </div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Geographical & Country Analytics</h1>
            <p class="text-sm text-slate-500 mt-1">First-party audience segmentation measuring visitors, engagement, and conversion by country.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">

            <!-- Dual-Format Export Buttons -->
            <div class="inline-flex items-center gap-1.5">
                <a href="{{ route('admin.analytics.countries.export', array_merge($filters, ['format' => 'csv'])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white hover:bg-slate-50 border border-slate-300 rounded-lg text-xs font-semibold text-slate-700 shadow-xs transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>CSV</span>
                </a>
                <a href="{{ route('admin.analytics.countries.export', array_merge($filters, ['format' => 'xlsx'])) }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 hover:bg-emerald-100 border border-emerald-300 rounded-lg text-xs font-semibold text-emerald-800 shadow-xs transition-colors">
                    <svg class="w-3.5 h-3.5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Excel (.xlsx)</span>
                </a>
            </div>
        </div>
    </div>

<x-report-filters :filters="$filters" :fields="array_merge(app(\App\Domains\Reporting\Services\ReportPeriod::class)->fields(), ['search'=>['Country name / code','search'], 'sort'=>['Sort by',array_combine(['country_code','country_name','unique_visitors','sessions','bounce_rate','conversion_rate','booking_cta_clicks','bookings_completed'], ['Country code','Country name','Visitors','Sessions','Bounce rate','Conversion rate','Booking CTAs','Completed bookings'])], 'dir'=>['Sort direction',['asc'=>'Ascending','desc'=>'Descending']]])" :action="route('admin.analytics.countries')" export-route="admin.analytics.countries.export" />
    <!-- Country KPI Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Audience Reach</span>
            <div class="text-2xl font-black text-slate-900 mt-2">{{ number_format($totalVisitors) }}</div>
            <p class="text-xs text-slate-500 mt-1">Unique visitors across all regions</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Total Sessions</span>
            <div class="text-2xl font-black text-slate-900 mt-2">{{ number_format($totalSessions) }}</div>
            <p class="text-xs text-slate-500 mt-1">Visitor sessions started in business time</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Overall Bounce Rate</span>
            <div class="text-2xl font-black text-amber-700 mt-2">{{ $overallBounceRate }}%</div>
            <p class="text-xs text-slate-500 mt-1">Sessions &lt; 10s with 1 pageview, 0 conversions</p>
        </div>

        <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Overall Booking Rate</span>
            <div class="text-2xl font-black text-emerald-700 mt-2">{{ $overallConversionRate }}%</div>
            <p class="text-xs text-slate-500 mt-1">Confirmed bookings from visitor sessions</p>
        </div>
    </div>

    <!-- Interactive Country Table & Search -->
    <div class="bg-white rounded-2xl p-6 border border-slate-200 shadow-xs">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h2 class="text-base font-bold text-slate-900">Country Breakdown & Conversion Table</h2>
                <p class="text-xs text-slate-500 mt-0.5">Click column headers to toggle ascending/descending sort. Use search to filter by country.</p>
            </div>
        </div>

        @php
            $nextDir = $dir === 'asc' ? 'desc' : 'asc';
            $sortUrl = function ($column, $currentSort, $currentDir, $range, $search) use ($filters) {
                $direction = ($currentSort === $column && $currentDir === 'desc') ? 'asc' : 'desc';
                return route('admin.analytics.countries', array_merge($filters, ['sort'=>$column, 'dir'=>$direction]));
            };
        @endphp

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-slate-50 text-[11px] uppercase font-bold text-slate-500 border-b border-slate-200">
                    <tr>
                        <th class="py-3 px-4">
                            <a href="{{ $sortUrl('country_name', $sort, $dir, $range, $search) }}" class="inline-flex items-center gap-1 hover:text-slate-900">
                                <span>Country / Region</span>
                                @if($sort === 'country_name')
                                    <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-right">
                            <a href="{{ $sortUrl('unique_visitors', $sort, $dir, $range, $search) }}" class="inline-flex items-center gap-1 hover:text-slate-900 justify-end w-full">
                                <span>Unique Visitors</span>
                                @if($sort === 'unique_visitors')
                                    <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-right">
                            <a href="{{ $sortUrl('sessions', $sort, $dir, $range, $search) }}" class="inline-flex items-center gap-1 hover:text-slate-900 justify-end w-full">
                                <span>Total Sessions</span>
                                @if($sort === 'sessions')
                                    <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-right">
                            <a href="{{ $sortUrl('bounce_rate', $sort, $dir, $range, $search) }}" class="inline-flex items-center gap-1 hover:text-slate-900 justify-end w-full">
                                <span>Bounce Rate (%)</span>
                                @if($sort === 'bounce_rate')
                                    <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-right">
                            <a href="{{ $sortUrl('booking_cta_clicks', $sort, $dir, $range, $search) }}" class="inline-flex items-center gap-1 hover:text-slate-900 justify-end w-full">
                                <span>Booking CTAs</span>
                                @if($sort === 'booking_cta_clicks')
                                    <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-right">
                            <a href="{{ $sortUrl('bookings_completed', $sort, $dir, $range, $search) }}" class="inline-flex items-center gap-1 hover:text-slate-900 justify-end w-full">
                                <span>Completed Bookings</span>
                                @if($sort === 'bookings_completed')
                                    <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                        <th class="py-3 px-4 text-right">
                            <a href="{{ $sortUrl('conversion_rate', $sort, $dir, $range, $search) }}" class="inline-flex items-center gap-1 hover:text-slate-900 justify-end w-full">
                                <span>Conversion Rate (%)</span>
                                @if($sort === 'conversion_rate')
                                    <span>{{ $dir === 'asc' ? '↑' : '↓' }}</span>
                                @endif
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($countries as $row)
                        <tr class="hover:bg-slate-50/80 transition-colors">
                            <td class="py-3 px-4 font-semibold text-slate-800">
                                <span class="inline-flex items-center gap-2">
                                    <img src="{{ $row['flag_url'] }}" alt="{{ $row['country_name'] }}" class="w-5 h-3.5 object-cover rounded-xs border border-slate-200">
                                    <span>{{ $row['country_name'] }}</span>
                                    <span class="text-slate-400 font-mono text-[11px]">({{ $row['country_code'] }})</span>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right font-medium text-slate-900">{{ number_format($row['unique_visitors']) }}</td>
                            <td class="py-3 px-4 text-right text-slate-700">{{ number_format($row['sessions']) }}</td>
                            <td class="py-3 px-4 text-right font-medium {{ $row['bounce_rate'] > 70 ? 'text-rose-600' : ($row['bounce_rate'] < 40 ? 'text-emerald-600' : 'text-amber-700') }}">
                                {{ $row['bounce_rate'] }}%
                            </td>
                            <td class="py-3 px-4 text-right text-slate-600">{{ number_format($row['booking_cta_clicks']) }}</td>
                            <td class="py-3 px-4 text-right font-bold text-emerald-700">{{ number_format($row['bookings_completed']) }}</td>
                            <td class="py-3 px-4 text-right font-bold {{ $row['conversion_rate'] > 0 ? 'text-emerald-700' : 'text-slate-400' }}">
                                {{ $row['conversion_rate'] }}%
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 text-center text-slate-400 italic">
                                No geographical metrics found matching your criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

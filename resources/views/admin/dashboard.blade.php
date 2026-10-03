@extends('layouts.admin')

@section('content')
<div class="space-y-8">
    
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Tutor Operations Dashboard</h1>
            <p class="text-sm text-slate-500 mt-1">Live schedule, booking operations, and student engagement overview.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.bookings.index', ['view' => 'calendar']) }}" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-300 rounded-xl text-sm font-semibold text-slate-700 hover:bg-slate-50 shadow-xs transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Calendar View</span>
            </a>
            <a href="{{ route('admin.availability.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-semibold shadow-sm transition-colors font-serif">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Manage Availability</span>
            </a>
        </div>
    </div>

    <!-- Needs Attention Queue (if any) -->
    @if(count($suspectedDuplicates) > 0 || $recentCancellations->isNotEmpty())
        <div class="space-y-3">
            @if(count($suspectedDuplicates) > 0)
                <div class="bg-amber-50 border border-amber-300 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-amber-100 text-amber-800 rounded-xl shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-amber-900">Needs Attention: {{ count($suspectedDuplicates) }} Potential Duplicate Contact(s) Detected</h2>
                            <p class="text-xs text-amber-800 mt-0.5">Students with matching phone numbers or identities exist across bookings and resource downloads.</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.contacts.duplicates') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg shrink-0 transition-colors">
                        <span>Review & Merge</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            @endif

            @if($recentCancellations->isNotEmpty())
                <div class="bg-slate-50 border border-slate-300 rounded-2xl p-4 sm:p-5 flex items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <div class="p-2 bg-slate-200 text-slate-700 rounded-xl shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Recent Lesson Cancellation(s)</h2>
                            <p class="text-xs text-slate-600 mt-0.5">{{ $recentCancellations->count() }} booking(s) were cancelled in the past 7 days.</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.bookings.index', ['status' => 'cancelled']) }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                        View Cancellations &rarr;
                    </a>
                </div>
            @endif
        </div>
    @endif

    <!-- Next Upcoming Lesson Card & Today's Schedule -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Next Upcoming Lesson Spotlight -->
        <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 text-white rounded-3xl p-6 shadow-xl border border-slate-800 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <span class="px-3 py-1 bg-amber-500/20 text-amber-300 border border-amber-500/30 rounded-full text-xs font-bold uppercase tracking-wider">
                        Next Lesson
                    </span>
                    <span class="text-xs text-slate-400">Business: {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($cairoNow) }}</span>
                </div>

                @if($nextBooking)
                    @php
                        $nextLocal = \Carbon\CarbonImmutable::instance($nextBooking->start_at_utc)->setTimezone($businessTz);
                        $studentLocal = \Carbon\CarbonImmutable::instance($nextBooking->start_at_utc)->setTimezone($nextBooking->customer_timezone);
                    @endphp

                    <h2 class="text-xl font-bold font-serif text-white">{{ $nextBooking->contact->name ?? 'Student' }}</h2>
                    <p class="text-xs text-amber-400 mt-0.5">{{ $nextBooking->sessionType->title ?? '1-on-1 Tutoring Session' }}</p>

                    <div class="mt-6 space-y-3 bg-slate-800/60 rounded-2xl p-4 border border-slate-700/60">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium">Your Time (Business Time):</span>
                            <span class="font-bold text-white">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($nextLocal) }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium">Student Time:</span>
                            <span class="font-bold text-amber-300">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($studentLocal) }} ({{ $nextBooking->customer_timezone }})</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium">Email:</span>
                            <span class="text-slate-200 truncate max-w-[160px]">{{ $nextBooking->contact->email }}</span>
                        </div>
                    </div>
                @else
                    <div class="py-8 text-center text-slate-400">
                        <svg class="w-10 h-10 mx-auto mb-2 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <p class="text-sm font-medium">No upcoming sessions scheduled.</p>
                        <p class="text-xs text-slate-500 mt-1">New bookings will appear here automatically.</p>
                    </div>
                @endif
            </div>

            @if($nextBooking)
                <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between">
                    <a href="{{ route('admin.bookings.show', $nextBooking->id) }}" class="text-xs font-bold text-amber-400 hover:text-amber-300 transition-colors">
                        View Lesson Details &rarr;
                    </a>
                    <form action="{{ route('admin.bookings.complete', $nextBooking->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-xs font-bold transition-colors">
                            Mark Completed
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <!-- Today's Schedule Table -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 shadow-xs border border-slate-200">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-bold font-serif text-slate-900">Today's Schedule</h2>
                    <p class="text-xs text-slate-500">{{ $cairoNow->format('l, F j, Y') }} (Business Time)</p>
                </div>
                <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">
                    {{ $todaysBookings->count() }} Session(s) Today
                </span>
            </div>

            @if($todaysBookings->isNotEmpty())
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Business Time</th>
                                <th class="px-4 py-3 font-semibold">Student</th>
                                <th class="px-4 py-3 font-semibold">Student Timezone</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($todaysBookings as $b)
                                @php
                                    $bStartLocal = \Carbon\CarbonImmutable::instance($b->start_at_utc)->setTimezone($businessTz);
                                    $bStudentLocal = \Carbon\CarbonImmutable::instance($b->start_at_utc)->setTimezone($b->customer_timezone);
                                @endphp
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-4 py-3.5 font-bold text-slate-900">
                                        {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStartLocal) }}
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <div class="font-medium text-slate-900">{{ $b->contact->name ?? 'Student' }}</div>
                                        <div class="text-xs text-slate-500">{{ $b->contact->email }}</div>
                                    </td>
                                    <td class="px-4 py-3.5 text-xs text-slate-600">
                                        <span class="font-semibold text-slate-900">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStudentLocal) }}</span>
                                        <span class="text-slate-400">({{ $b->customer_timezone }})</span>
                                    </td>
                                    <td class="px-4 py-3.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold capitalize
                                            {{ $b->status === 'confirmed' ? 'bg-amber-100 text-amber-800' : '' }}
                                            {{ $b->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                            {{ $b->status === 'no_show' ? 'bg-red-100 text-red-800' : '' }}
                                            {{ $b->status === 'cancelled' ? 'bg-slate-100 text-slate-600' : '' }}">
                                            {{ str_replace('_', ' ', $b->status) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3.5 text-right">
                                        <a href="{{ route('admin.bookings.show', $b->id) }}" class="text-xs font-bold text-amber-600 hover:text-amber-800">
                                            Manage &rarr;
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="py-12 text-center text-slate-400">
                    <p class="text-sm font-medium">No sessions scheduled for today.</p>
                    <p class="text-xs text-slate-500 mt-1">Enjoy your study, prep, or day off!</p>
                </div>
            @endif
        </div>
    </div>

    <!-- 30-Day KPI Metrics Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold font-serif text-slate-900">30-Day Business Performance</h2>
            <span class="text-xs text-slate-500">First-party metrics & conversion data</span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
            
            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Confirmed Bookings</div>
                <div class="text-2xl font-bold font-serif text-slate-900 mt-2">{{ $kpis['confirmed_bookings'] }}</div>
                <div class="text-xs text-emerald-600 mt-1 font-medium">Active sessions scheduled</div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Completed Sessions</div>
                <div class="text-2xl font-bold font-serif text-slate-900 mt-2">{{ $kpis['completed_bookings'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Taught & completed</div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Students</div>
                <div class="text-2xl font-bold font-serif text-slate-900 mt-2">{{ $kpis['total_students'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Tutoring roster</div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Resource Requests</div>
                <div class="text-2xl font-bold font-serif text-slate-900 mt-2">{{ $kpis['resource_requests'] }}</div>
                <div class="text-xs text-slate-500 mt-1">Gated workbook forms</div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200 shadow-xs col-span-2 lg:col-span-1">
                <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Resource Downloads</div>
                <div class="text-2xl font-bold font-serif text-slate-900 mt-2">{{ $kpis['resource_downloads'] }}</div>
                <div class="text-xs text-emerald-600 mt-1 font-medium">Materials served</div>
            </div>
        </div>
    </div>

    <!-- Recent Operations & Security Activity -->
    <div class="bg-white rounded-3xl p-6 shadow-xs border border-slate-200">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-lg font-bold font-serif text-slate-900">Recent Security & Operations Audit Log</h2>
                <p class="text-xs text-slate-500">Every sensitive tutor action is tracked in the immutable database audit trail</p>
            </div>
            <a href="{{ route('admin.audit-logs') }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                View Full Audit Log &rarr;
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold">Action</th>
                        <th class="px-4 py-2.5 font-semibold">Administrator</th>
                        <th class="px-4 py-2.5 font-semibold">IP Address</th>
                        <th class="px-4 py-2.5 font-semibold text-right">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($recentAuditLogs as $log)
                        <tr class="hover:bg-slate-50/50">
                            <td class="px-4 py-3 font-semibold text-slate-900">
                                <span class="px-2 py-0.5 bg-slate-100 text-slate-700 rounded-md font-mono text-[11px]">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-700">
                                {{ $log->administrator->name ?? 'System / Anonymous' }}
                            </td>
                            <td class="px-4 py-3 text-slate-500 font-mono text-[11px]">
                                {{ $log->ip_address ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right text-slate-500">
                                {{ $log->created_at ? $log->created_at->diffForHumans() : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                                No audit events recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

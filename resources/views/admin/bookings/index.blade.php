@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Bookings & Tutoring Calendar</h1>
            <p class="text-sm text-slate-500 mt-1">Manage private lessons, dual-timezone appointments, and scheduling operations.</p>
        </div>

        <!-- Actions & View Mode Switcher -->
        <div class="flex items-center gap-3 shrink-0 flex-wrap">
            <div class="flex items-center bg-slate-200/80 p-1 rounded-xl">
                <a href="{{ route('admin.bookings.index', array_merge(request()->query(), ['view' => 'list'])) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $viewMode === 'list' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    List
                </a>
                <a href="{{ route('admin.bookings.index', array_merge(request()->query(), ['view' => 'day', 'date' => $selectedDate])) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $viewMode === 'day' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Day
                </a>
                <a href="{{ route('admin.bookings.index', array_merge(request()->query(), ['view' => 'week', 'date' => $selectedDate])) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ $viewMode === 'week' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Week
                </a>
                <a href="{{ route('admin.bookings.index', array_merge(request()->query(), ['view' => 'calendar'])) }}" 
                   class="px-3 py-1.5 rounded-lg text-xs font-bold transition-colors {{ in_array($viewMode, ['calendar', 'month']) ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900' }}">
                    Month
                </a>
            </div>

            <a href="{{ route('admin.bookings.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>New Booking</span>
            </a>
        </div>
    </div>

    @if($viewMode === 'day')
        <!-- Day View -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-6">
            
            <!-- Day Navigation Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-5">
                <div>
                    <h2 class="text-xl font-bold font-serif text-slate-900">{{ $dayCarbon->format('l, F j, Y') }}</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Tutor Time ({{ $businessTz }}) &bull; {{ $dayBookings->count() }} {{ Str::plural('session', $dayBookings->count()) }} scheduled</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.bookings.index', ['view' => 'day', 'date' => $prevDay]) }}" 
                       class="p-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700" title="Previous Day">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <a href="{{ route('admin.bookings.index', ['view' => 'day', 'date' => now($businessTz)->toDateString()]) }}" 
                       class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-50 text-slate-700">
                        Today
                    </a>
                    <a href="{{ route('admin.bookings.index', ['view' => 'day', 'date' => $nextDay]) }}" 
                       class="p-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700" title="Next Day">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                    
                    <form action="{{ route('admin.bookings.index') }}" method="GET" class="ml-2 flex items-center gap-1">
                        <input type="hidden" name="view" value="day">
                        <input type="date" name="date" value="{{ $selectedDate }}" onchange="this.form.submit()"
                               class="text-xs px-2.5 py-1.5 border border-slate-300 rounded-lg bg-slate-50 focus:outline-none focus:ring-2 focus:ring-amber-500">
                    </form>
                </div>
            </div>

            <!-- Day Appointments List -->
            @if($dayBookings->isEmpty())
                <div class="p-12 text-center">
                    <div class="w-12 h-12 rounded-full bg-slate-100 text-slate-400 mx-auto flex items-center justify-center mb-3">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-sm font-bold text-slate-900">No appointments scheduled for this day</h3>
                    <p class="text-xs text-slate-500 mt-1">Check availability rules or create a manual booking above.</p>
                </div>
            @else
                <div class="space-y-4">
                    @foreach($dayBookings as $b)
                        @php
                            $bStartCairo = $b->business_start;
                            $bEndCairo = $b->end_at_utc->copy()->setTimezone($bStartCairo->timezoneName);
                            $bStartStudent = $b->customer_start;
                            $bEndStudent = $b->end_at_utc->copy()->setTimezone($bStartStudent->timezoneName);
                        @endphp
                        <div class="border border-slate-200 rounded-2xl p-4 sm:p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:border-amber-400 transition-colors">
                            <div class="space-y-2">
                                <div class="flex items-center gap-3">
                                    <span class="text-lg font-bold text-slate-900 font-mono">
                                        {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStartCairo) }} – {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bEndCairo) }}
                                    </span>
                                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold uppercase tracking-wider
                                        {{ $b->status === 'confirmed' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ $b->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        {{ $b->status === 'no_show' ? 'bg-red-100 text-red-800' : '' }}
                                        {{ $b->status === 'cancelled' ? 'bg-slate-100 text-slate-600' : '' }}">
                                        {{ str_replace('_', ' ', $b->status) }}
                                    </span>
                                </div>

                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-1 text-xs">
                                    <div>
                                        <span class="text-slate-400">Student:</span>
                                        <span class="font-bold text-slate-800">{{ $b->contact->name ?? 'Student' }}</span>
                                        <span class="text-slate-500">({{ $b->contact->email ?? 'No email' }})</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400">Student Local Time:</span>
                                        <span class="font-medium text-slate-700 font-mono">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStartStudent) }} ({{ $b->customer_timezone }})</span>
                                    </div>
                                    <div>
                                        <span class="text-slate-400">Session Type:</span>
                                        <span class="font-medium text-slate-700">{{ $b->sessionType->name ?? 'Lesson' }} ({{ $b->sessionType->duration_minutes ?? 60 }} min)</span>
                                    </div>
                                    @if($b->notes)
                                        <div class="sm:col-span-2 text-slate-600 italic">
                                            Notes: {{ $b->notes }}
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100">
                                <a href="{{ route('admin.bookings.show', $b->id) }}" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold rounded-lg transition-colors">
                                    View Details
                                </a>
                                @if($b->status === 'confirmed')
                                    <form action="{{ route('admin.bookings.complete', $b->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 text-xs font-semibold rounded-lg transition-colors">
                                            Complete
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

        </div>

    @elseif($viewMode === 'week')
        <!-- Week View -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-6">
            
            <!-- Week Navigation Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-5">
                <div>
                    <h2 class="text-xl font-bold font-serif text-slate-900">
                        {{ $weekStart->format('M j') }} &ndash; {{ $weekEnd->format('M j, Y') }}
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Tutor Time ({{ $businessTz }}) &bull; 7-day schedule view</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.bookings.index', ['view' => 'week', 'date' => $prevWeek]) }}" 
                       class="p-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700" title="Previous Week">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <a href="{{ route('admin.bookings.index', ['view' => 'week', 'date' => now($businessTz)->toDateString()]) }}" 
                       class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-50 text-slate-700">
                        This Week
                    </a>
                    <a href="{{ route('admin.bookings.index', ['view' => 'week', 'date' => $nextWeek]) }}" 
                       class="p-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700" title="Next Week">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- 7-Column Week Grid -->
            <div class="grid grid-cols-1 md:grid-cols-7 gap-3">
                @foreach($weekDays as $wDay)
                    <div class="border rounded-2xl p-3 flex flex-col justify-between min-h-[160px] {{ $wDay['is_today'] ? 'border-amber-500 bg-amber-50/20 ring-2 ring-amber-500/20' : 'border-slate-200 bg-slate-50/50' }}">
                        <div>
                            <!-- Column Header -->
                            <div class="flex items-center justify-between border-b border-slate-200/80 pb-2 mb-2">
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wider text-slate-600">{{ $wDay['day_name'] }}</span>
                                    <span class="text-xs font-medium text-slate-400 block">{{ $wDay['formatted'] }}</span>
                                </div>
                                <a href="{{ route('admin.bookings.index', ['view' => 'day', 'date' => $wDay['date']]) }}" class="text-[10px] text-amber-600 hover:underline font-bold">
                                    Day View &rarr;
                                </a>
                            </div>

                            <!-- Appointments in this Day -->
                            <div class="space-y-1.5">
                                @forelse($wDay['bookings'] as $b)
                                    @php
                                        $bStartCairo = $b->business_start;
                                    @endphp
                                    <a href="{{ route('admin.bookings.show', $b->id) }}" 
                                       class="block p-1.5 rounded-lg text-xs transition-colors border
                                           {{ $b->status === 'confirmed' ? 'bg-white border-amber-300 text-amber-950 hover:bg-amber-50' : '' }}
                                           {{ $b->status === 'completed' ? 'bg-emerald-50 border-emerald-300 text-emerald-950 hover:bg-emerald-100' : '' }}
                                           {{ $b->status === 'no_show' ? 'bg-red-50 border-red-300 text-red-950 hover:bg-red-100' : '' }}
                                           {{ $b->status === 'cancelled' ? 'bg-slate-100 border-slate-200 text-slate-500 line-through' : '' }}">
                                        <div class="font-bold font-mono text-[11px]">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStartCairo) }} (Tutor Time)</div>
                                        <div class="truncate text-[11px]">{{ $b->contact->name ?? 'Student' }}</div>
                                    </a>
                                @empty
                                    <div class="text-[11px] text-slate-400 py-3 text-center italic">
                                        No lessons
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        @if($wDay['bookings']->isNotEmpty())
                            <div class="pt-2 mt-2 border-t border-slate-200 text-[10px] text-slate-500 font-bold text-right">
                                {{ $wDay['bookings']->count() }} {{ Str::plural('session', $wDay['bookings']->count()) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

        </div>

    @elseif($viewMode === 'list')
        <!-- List Filters -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-xs space-y-4">
            <form action="{{ route('admin.bookings.index') }}" method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <input type="hidden" name="view" value="list">

                <!-- Search -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Search</label>
                    <input type="search" name="search" value="{{ $search }}" placeholder="Student name, email, token..." 
                           class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all">
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Status</label>
                    <select name="status" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all">
                        <option value="all" {{ $status === 'all' ? 'selected' : '' }}>All Statuses</option>
                        <option value="confirmed" {{ $status === 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="cancelled" {{ $status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="no_show" {{ $status === 'no_show' ? 'selected' : '' }}>Student No-Show</option>
                    </select>
                </div>

                <!-- Date Scope -->
                <div>
                    <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">Date Scope</label>
                    <select name="date" class="w-full px-3 py-2 text-sm bg-slate-50 border border-slate-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all">
                        <option value="all" {{ $dateScope === 'all' ? 'selected' : '' }}>All Dates</option>
                        <option value="today" {{ $dateScope === 'today' ? 'selected' : '' }}>Today Only</option>
                        <option value="upcoming" {{ $dateScope === 'upcoming' ? 'selected' : '' }}>Upcoming Sessions</option>
                        <option value="past" {{ $dateScope === 'past' ? 'selected' : '' }}>Past Sessions</option>
                    </select>
                </div>

                <!-- Submit & Clear Buttons -->
                <div class="flex items-end gap-2">
                    <button type="submit" class="flex-1 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-sm font-semibold transition-colors">
                        Filter
                    </button>
                    <a href="{{ route('admin.bookings.index') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-sm font-semibold transition-colors">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Bookings Table -->
        <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3.5 font-semibold">Tutor Time</th>
                            <th class="px-4 py-3.5 font-semibold">Student & Local Time</th>
                            <th class="px-4 py-3.5 font-semibold">Session Type</th>
                            <th class="px-4 py-3.5 font-semibold">Status</th>
                            <th class="px-4 py-3.5 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($bookings as $booking)
                            @php
                                $bStartCairo = $booking->business_start;
                                $bStartStudent = $booking->customer_start;
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="font-bold text-slate-900">{{ $bStartCairo->format('D, M j, Y') }}</div>
                                    <div class="text-xs text-amber-700 font-mono font-semibold">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStartCairo) }} Tutor Time</div>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-slate-900">{{ $booking->contact->name ?? 'Student' }}</div>
                                    <div class="text-xs text-slate-500 font-mono">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStartStudent) }} ({{ $booking->customer_timezone }})</div>
                                    <div class="text-[11px] text-slate-400">{{ $booking->contact->email ?? '' }}</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="font-medium text-slate-900">{{ $booking->sessionType->name ?? '1-on-1 Lesson' }}</div>
                                    <div class="text-xs text-slate-500">{{ $booking->sessionType->duration_minutes ?? 60 }} mins</div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-bold capitalize
                                        {{ $booking->status === 'confirmed' ? 'bg-amber-100 text-amber-800' : '' }}
                                        {{ $booking->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                        {{ $booking->status === 'cancelled' ? 'bg-slate-100 text-slate-600' : '' }}
                                        {{ $booking->status === 'no_show' ? 'bg-red-100 text-red-800' : '' }}">
                                        {{ str_replace('_', ' ', $booking->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-right">
                                    <a href="{{ route('admin.bookings.show', $booking->id) }}" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                                        Manage &rarr;
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-12 text-center text-slate-400">
                                    No bookings found matching your search or filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($bookings->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $bookings->links() }}
                </div>
            @endif
        </div>

    @else
        <!-- Month / Calendar Overview Mode -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            
            <!-- Calendar Navigation -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h2 class="text-xl font-bold font-serif text-slate-900">{{ $monthCarbon->format('F Y') }}</h2>
                    <p class="text-xs text-slate-500">All slots shown in Tutor Time ({{ $businessTz }})</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.bookings.index', ['view' => 'calendar', 'month' => $prevMonth]) }}" 
                       class="p-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </a>
                    <a href="{{ route('admin.bookings.index', ['view' => 'calendar', 'month' => now($businessTz)->format('Y-m')]) }}" 
                       class="px-3 py-1.5 border border-slate-300 rounded-lg text-xs font-semibold hover:bg-slate-50 text-slate-700">
                        Current Month
                    </a>
                    <a href="{{ route('admin.bookings.index', ['view' => 'calendar', 'month' => $nextMonth]) }}" 
                       class="p-2 border border-slate-300 rounded-lg hover:bg-slate-50 text-slate-700">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>

            <!-- Calendar Days Header -->
            <div class="grid grid-cols-7 gap-px bg-slate-200 border border-slate-200 rounded-2xl overflow-hidden text-center text-xs font-bold text-slate-700">
                <div class="bg-slate-100 py-2">Sun</div>
                <div class="bg-slate-100 py-2">Mon</div>
                <div class="bg-slate-100 py-2">Tue</div>
                <div class="bg-slate-100 py-2">Wed</div>
                <div class="bg-slate-100 py-2">Thu</div>
                <div class="bg-slate-100 py-2">Fri</div>
                <div class="bg-slate-100 py-2">Sat</div>

                <!-- Empty cells before start of month -->
                @for($i = 0; $i < $startWeekday; $i++)
                    <div class="bg-slate-50/50 min-h-[100px] p-2 text-slate-300 text-left"></div>
                @endfor

                <!-- Month Days -->
                @for($day = 1; $day <= $daysInMonth; $day++)
                    @php
                        $dayDate = $monthCarbon->setDay($day)->toDateString();
                        $dBookings = $monthBookings->get($dayDate, collect());
                        $isToday = $dayDate === now($businessTz)->toDateString();
                    @endphp
                    <div class="bg-white min-h-[100px] p-2 text-left flex flex-col justify-between hover:bg-amber-50/40 transition-colors {{ $isToday ? 'ring-2 ring-amber-500' : '' }}">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold {{ $isToday ? 'w-5 h-5 bg-amber-600 text-white rounded-full flex items-center justify-center' : 'text-slate-800' }}">
                                {{ $day }}
                            </span>
                            @if($dBookings->isNotEmpty())
                                <span class="text-[10px] font-bold px-1.5 py-0.5 bg-amber-100 text-amber-900 rounded-full">
                                    {{ $dBookings->count() }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-2 space-y-1 overflow-y-auto max-h-[70px]">
                            @foreach($dBookings as $b)
                                @php
                                    $bTime = $b->business_start;
                                @endphp
                                <a href="{{ route('admin.bookings.show', $b->id) }}" 
                                   class="block text-[11px] px-1.5 py-0.5 rounded-md truncate font-medium
                                       {{ $b->status === 'confirmed' ? 'bg-amber-50 text-amber-900 border border-amber-200' : '' }}
                                       {{ $b->status === 'completed' ? 'bg-emerald-50 text-emerald-900 border border-emerald-200' : '' }}
                                       {{ $b->status === 'no_show' ? 'bg-red-50 text-red-900 border border-red-200' : '' }}
                                       {{ $b->status === 'cancelled' ? 'bg-slate-100 text-slate-500 line-through' : '' }}">
                                    {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bTime) }} - {{ $b->contact->name ?? 'Student' }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    @endif

</div>
@endsection

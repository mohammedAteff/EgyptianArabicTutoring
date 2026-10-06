@extends('layouts.admin')

@section('content')
<div class="space-y-8" x-data="{ addRuleModalOpen: false, addExceptionModalOpen: false }">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Tutor Availability Management</h1>
            <p class="text-sm text-slate-500 mt-1">Configure weekly recurring lesson intervals and date-specific exceptions in Business Time ({{ $businessTz }}).</p>
        </div>
        <div class="flex items-center gap-3">
            <button @click="addExceptionModalOpen = true" class="px-4 py-2 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-bold transition-colors shadow-xs">
                + Add Date Exception / Holiday
            </button>
            <button @click="addRuleModalOpen = true" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                + Add Weekly Time Window
            </button>
        </div>
    </div>

    <!-- Weekly Recurring Rules by Day -->

    <p class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Date exceptions govern new bookings in business time. Saving or deleting an exception preserves existing appointments. Review the Bookings & Calendar page before changing any booked lesson.</p>
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div>
            <h2 class="text-lg font-bold font-serif text-slate-900">Weekly Recurring Schedule</h2>
            <p class="text-xs text-slate-500">Regular tutoring windows available for student booking each week.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            @foreach($weekdays as $dayIndex => $dayName)
                @php
                    $dayRules = $rules->where('weekday', $dayIndex);
                @endphp
                <div class="bg-slate-50 rounded-2xl p-4 border border-slate-200/80 flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                            <span class="font-bold text-slate-900 text-sm">{{ $dayName }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-semibold {{ $dayRules->isNotEmpty() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">
                                {{ $dayRules->count() }} Window(s)
                            </span>
                        </div>

                        <div class="mt-3 space-y-2">
                            @forelse($dayRules as $r)
                                <div class="bg-white p-2.5 rounded-xl border border-slate-200 text-xs flex items-center justify-between shadow-2xs">
                                    <div>
                                        <div class="font-bold text-slate-900">
                                            {{ substr($r->start_time, 0, 5) }} &ndash; {{ substr($r->end_time, 0, 5) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 mt-0.5">
                                            {{ $r->session_duration_minutes }}m lesson + {{ $r->buffer_minutes }}m buffer
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <form action="{{ route('admin.availability.toggle', $r->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" title="{{ $r->enabled ? 'Disable window' : 'Enable window' }}" 
                                                    class="p-1 rounded-md text-xs font-bold {{ $r->enabled ? 'text-emerald-600 hover:bg-emerald-50' : 'text-slate-400 hover:bg-slate-100' }}">
                                                {{ $r->enabled ? 'ON' : 'OFF' }}
                                            </button>
                                        </form>
                                        <form action="{{ route('admin.availability.destroy', $r->id) }}" method="POST" onsubmit="return confirm('Delete this recurring window?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="p-1 text-slate-400 hover:text-red-600 rounded-md">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @empty
                                <div class="py-4 text-center text-xs text-slate-400">
                                    Day off / No active windows
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Date Exceptions / Holidays -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-lg font-bold font-serif text-slate-900">Date-Specific Exceptions & Blocked Days</h2>
                <p class="text-xs text-slate-500">Overrides recurring rules for specific dates (e.g. holidays, vacations, or custom hours).</p>
            </div>
            <button @click="addExceptionModalOpen = true" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-bold transition-colors">
                + New Exception
            </button>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Date</th>
                        <th class="px-4 py-3 font-semibold">Type</th>
                        <th class="px-4 py-3 font-semibold">Custom Hours</th>
                        <th class="px-4 py-3 font-semibold">Reason</th>
                        <th class="px-4 py-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($exceptions as $exception)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-bold text-slate-900">
                                {{ \Carbon\Carbon::parse($exception->date)->format('D, M j, Y') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2.5 py-1 rounded-md text-xs font-bold {{ $exception->is_blocked ? 'bg-red-100 text-red-800' : 'bg-blue-100 text-blue-800' }}">
                                    {{ $exception->is_blocked ? 'Day Blocked (Off)' : 'Custom Hours' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                @if(! $exception->is_blocked && $exception->start_time && $exception->end_time)
                                    {{ substr($exception->start_time, 0, 5) }} &ndash; {{ substr($exception->end_time, 0, 5) }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-700">
                                {{ $exception->reason ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form action="{{ route('admin.availability.exception.destroy', $exception->id) }}" method="POST" onsubmit="return confirm('Remove this exception?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-red-600 hover:text-red-800">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400 text-xs">
                                No date exceptions configured. Weekly recurring rules will apply across all horizon dates.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4">{{ $exceptions->links() }}</div>
    </div>

    <!-- Modal: Add Weekly Rule -->
    <div x-show="addRuleModalOpen" 
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" 
         style="display: none;">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-slate-200" @click.away="addRuleModalOpen = false">
            <h3 class="text-lg font-bold font-serif text-slate-900 mb-1">Add Weekly Time Window</h3>
            <p class="text-xs text-slate-500 mb-6">Create a recurring availability interval in Business Time ({{ $businessTz }}).</p>

            <form action="{{ route('admin.availability.rules.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Weekday</label>
                    <select name="weekday" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        @foreach($weekdays as $i => $name)
                            <option value="{{ $i }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Start Time (HH:MM)</label>
                        <input type="time" name="start_time" required value="09:00" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">End Time (HH:MM)</label>
                        <input type="time" name="end_time" required value="13:00" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Lesson Duration (min)</label>
                        <input type="number" name="session_duration_minutes" value="60" min="15" max="240" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Buffer After (min)</label>
                        <input type="number" name="buffer_minutes" value="15" min="0" max="60" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Minimum Notice (hrs)</label>
                        <input type="number" name="min_notice_hours" value="12" min="0" max="72" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Max Horizon (days)</label>
                        <input type="number" name="max_horizon_days" value="60" min="1" max="180" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="addRuleModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs">
                        Add Window
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Add Date Exception -->
    <div x-show="addExceptionModalOpen" 
         x-data="{ isBlocked: 1 }"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs" 
         style="display: none;">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-200" @click.away="addExceptionModalOpen = false">
            <h3 class="text-lg font-bold font-serif text-slate-900 mb-1">Add Date Exception / Blocked Day</h3>
            <p class="text-xs text-slate-500 mb-6">Block a date completely (holiday/vacation) or specify custom hours for that specific date.</p>

            <form action="{{ route('admin.availability.exceptions.store') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Target Date</label>
                    <input type="date" name="date" required min="{{ date('Y-m-d') }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Exception Type</label>
                    <select name="is_blocked" x-model="isBlocked" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="1">Block Entire Day (Unavailable)</option>
                        <option value="0">Custom Availability Hours</option>
                    </select>
                </div>

                <div x-show="isBlocked == 0" class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Start Time (HH:MM)</label>
                        <input type="time" name="start_time" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">End Time (HH:MM)</label>
                        <input type="time" name="end_time" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Reason / Note</label>
                    <input type="text" name="reason" placeholder="e.g. Eid Holiday, Doctor appointment, Travel"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="addExceptionModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs">
                        Save Exception
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

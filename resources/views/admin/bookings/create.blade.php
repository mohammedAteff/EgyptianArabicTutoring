@extends('layouts.admin')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Create Manual Booking</h1>
            <p class="text-sm text-slate-500 mt-1">Directly reserve a session for a student contacted via phone, WhatsApp, or outside the web form.</p>
        </div>
        <a href="{{ route('admin.bookings.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
            &larr; Back to Bookings
        </a>
    </div>

    @if(session('error'))
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm font-medium">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.bookings.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Session Type Selection -->
            <div>
                <label for="session_type_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Lesson Type</label>
                <select id="session_type_id" name="session_type_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @foreach($sessionTypes as $st)
                        <option value="{{ $st->id }}" {{ old('session_type_id') == $st->id ? 'selected' : '' }}>
                            {{ $st->title }} ({{ $st->duration_minutes }} min) — ${{ number_format($st->price, 2) }} {{ $st->currency }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Student Identity -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="student_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Student Full Name</label>
                    <input type="text" id="student_name" name="student_name" value="{{ old('student_name') }}" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                           placeholder="e.g. David Miller">
                </div>

                <div>
                    <label for="student_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Student Email</label>
                    <input type="email" id="student_email" name="student_email" value="{{ old('student_email') }}" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono"
                           placeholder="david@example.com">
                </div>

                <div>
                    <label for="student_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Phone / WhatsApp</label>
                    <input type="text" id="student_phone" name="student_phone" value="{{ old('student_phone') }}"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono"
                           placeholder="+1 555 123 4567">
                </div>
            </div>

            <!-- Date & Time (Cairo Timezone) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Session Date (Cairo)</label>
                    <input type="date" id="date" name="date" value="{{ old('date', now($businessTz)->addDay()->format('Y-m-d')) }}" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                </div>

                <div>
                    <label for="time" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Start Time (Cairo 24h)</label>
                    <input type="text" id="time" name="time" value="{{ old('time', '10:00') }}" required
                           placeholder="10:00"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                </div>

                <div>
                    <label for="customer_timezone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Student Timezone</label>
                    <input type="text" id="customer_timezone" name="customer_timezone" value="{{ old('customer_timezone', $businessTz) }}"
                           placeholder="America/New_York or Africa/Cairo"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label for="notes" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Internal Lesson Notes / Student Goal</label>
                <textarea id="notes" name="notes" rows="3"
                          placeholder="e.g. Student wants Egyptian dialect for upcoming travel to Cairo next month..."
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.bookings.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors font-serif shadow-xs">
                    Create and Authorize Booking
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

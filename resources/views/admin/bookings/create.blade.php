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

    <form method="GET" action="{{ route('admin.bookings.create') }}" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5">
        <label for="booking-student" class="block text-sm font-semibold">Book for an existing student</label>
        <div class="flex flex-wrap gap-3"><select id="booking-student" name="student_id" class="min-h-11 min-w-0 flex-1 rounded-xl border border-slate-300 p-3"><option value="">New student / direct payment</option>@foreach($students as $candidate)<option value="{{ $candidate->id }}" @selected($selectedStudent?->id === $candidate->id)>{{ $candidate->name }} · {{ $candidate->email }}</option>@endforeach</select><button class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white">Choose student</button></div>
    </form>
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.bookings.store') }}" method="POST" class="space-y-6" x-data="{ funding: @js(old('funding_mode', $selectedStudent && array_sum(array_column($entitlementBalances, 'available')) > 0 ? 'package' : 'direct')) }">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
            @if($selectedStudent)<input type="hidden" name="student_id" value="{{ $selectedStudent->id }}">@endif
            <label class="block text-sm font-semibold">Funding<select name="funding_mode" x-model="funding" @change="$nextTick(() => { const picker = $el.form.querySelector('[name=session_type_id]'); if (picker.selectedOptions[0]?.disabled) picker.value = Array.from(picker.options).find(option => !option.disabled)?.value || ''; })" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 p-3"><option value="direct">Direct payment / standalone booking</option><option value="package" @disabled(!$selectedStudent)>Use existing package credits{{ !$selectedStudent ? ' — choose a student above first' : '' }}</option></select></label>
            @if($selectedStudent)
            <section x-show="funding === 'package'" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm">
                <h2 class="font-semibold">{{ $selectedStudent->name }} · Available package credits</h2>
                @forelse($entitlementBalances as $balance)<p class="mt-2">{{ $balance['label'] }}: <strong>{{ $balance['available'] }}</strong> available</p>@empty<p class="mt-2">No eligible package credits.</p>@endforelse
                <p class="mt-3">The matching credit is taken from the earliest-expiring eligible package when you confirm. No new payment is recorded. One-hour and two-hour credits remain separate.</p>
            </section>
            @endif

            <!-- Session Type Selection -->
            <div>
                <label for="session_type_id" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Lesson Type</label>
                <select id="session_type_id" name="session_type_id" required class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @foreach($sessionTypes as $st)
                        <option value="{{ $st->id }}" :hidden="funding === 'package' && @js($st->funding_mode !== 'package')" :disabled="funding === 'package' && @js(!in_array($st->id, $fundableTypeIds, true))" x-text="funding === 'package' ? @js($st->title.' ('.$st->duration_minutes.' min) · Uses '.$st->required_entitlement_units.' matching package credit'.($st->required_entitlement_units > 1 ? 's' : '').(in_array($st->id, $fundableTypeIds, true) ? '' : ' · No eligible credits')) : @js($st->title.' ('.$st->duration_minutes.' min) — $'.number_format($st->price, 2).' '.$st->currency)" {{ old('session_type_id', $defaultSessionTypeId) == $st->id ? 'selected' : '' }}>
                            {{ $st->title }} ({{ $st->duration_minutes }} min) — ${{ number_format($st->price, 2) }} {{ $st->currency }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Student Identity -->
            <div x-show="funding !== 'package'" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label for="student_name" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Student Full Name</label>
                    <input type="text" id="student_name" name="student_name" value="{{ old('student_name', $selectedStudent?->name) }}" :required="funding !== 'package'"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                           placeholder="e.g. David Miller">
                </div>

                <div>
                    <label for="student_email" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Student Email</label>
                    <input type="email" id="student_email" name="student_email" value="{{ old('student_email', $selectedStudent?->email) }}" :required="funding !== 'package'"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono"
                           placeholder="david@example.com">
                </div>

                <div>
                    <label for="student_phone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Phone / WhatsApp</label>
                    <input type="text" id="student_phone" name="student_phone" value="{{ old('student_phone', $selectedStudent?->phone) }}"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono"
                           placeholder="+1 555 123 4567">
                </div>
            </div>

            <!-- Date & Time (Business Timezone) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Session Date ({{ $businessTz }})</label>
                    <input type="date" id="date" name="date" value="{{ old('date', now($businessTz)->addDay()->format('Y-m-d')) }}" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                </div>

                <div>
                    <label for="time" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Start Time ({{ $businessTz }} 24h)</label>
                    <input type="text" id="time" name="time" value="{{ old('time', '10:00') }}" required
                           placeholder="10:00"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                </div>

                <div>
                    <label for="customer_timezone" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Student Timezone</label>
                    <input type="text" id="customer_timezone" name="customer_timezone" value="{{ old('customer_timezone', $selectedStudent?->preferred_timezone ?? $businessTz) }}"
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

@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-8">

    <!-- Top Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                <a href="{{ route('admin.contacts.index') }}" class="hover:text-amber-600 transition-colors">&larr; Back to Contacts</a>
                <span>&bull;</span>
                <span>Student #{{ $contact->id }}</span>
            </div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight flex items-center gap-3">
                <span>{{ $contact->name ?? 'Student' }}</span>
                @if($contact->merged_into_contact_id)
                    <span class="px-2.5 py-0.5 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">
                        Merged into #{{ $contact->merged_into_contact_id }}
                    </span>
                @endif
            </h1>
        </div>
    </div>

    <!-- Student Profile & Notes Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Contact Identity Card -->
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
            <h2 class="text-base font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">Student Identity</h2>

            <div class="space-y-3 text-sm">
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Canonical Email</span>
                    <span class="font-mono text-xs font-bold text-slate-900">{{ $contact->email }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Display Email</span>
                    <span class="font-mono text-xs text-slate-700">{{ $contact->display_email }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Phone / WhatsApp</span>
                    <span class="font-mono text-xs text-slate-900 font-semibold">{{ $contact->phone ?? 'Not provided' }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">First Seen</span>
                    <span class="text-xs text-slate-700">{{ $contact->first_seen_at ? app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($contact->first_seen_at) : '—' }}</span>
                </div>
                <div>
                    <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Last Active</span>
                    <span class="text-xs text-slate-700">{{ $contact->last_seen_at ? $contact->last_seen_at->diffForHumans() : '—' }}</span>
                </div>
                @if($contact->utm_source)
                    <div class="pt-3 border-t border-slate-100">
                        <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">Attribution</span>
                        <div class="text-xs text-slate-700 mt-1">
                            <span class="font-bold capitalize">{{ $contact->utm_source }}</span>
                            @if($contact->utm_medium) / {{ $contact->utm_medium }} @endif
                            @if($contact->utm_campaign) ({{ $contact->utm_campaign }}) @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Tutor Private Student Notes -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
            <div>
                <h2 class="text-base font-bold font-serif text-slate-900 mb-1">Tutor Pedagogical Assessment & Notes</h2>
                <p class="text-xs text-slate-500 mb-4">Student Arabic level (e.g. A1, Intermediate), dialect learning goals, personal interests, homework assignments.</p>

                <form action="{{ route('admin.contacts.notes', $contact->id) }}" method="POST">
                    @csrf
                    @method('PATCH')
                    <textarea name="notes" rows="6" 
                              class="w-full p-4 text-sm bg-slate-50 border border-slate-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all placeholder-slate-400"
                              placeholder="Add pedagogical notes for this student...">{{ old('notes', $contact->notes) }}</textarea>

                    <div class="mt-3 flex justify-end">
                        <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-colors font-serif">
                            Save Student Assessment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Booking History Table -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold font-serif text-slate-900">Tutoring Booking History</h2>
                <p class="text-xs text-slate-500">{{ $contact->bookings->count() }} total session(s) on record</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Business Time</th>
                        <th class="px-4 py-3 font-semibold">Student Time</th>
                        <th class="px-4 py-3 font-semibold">Session Type</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($contact->bookings as $b)
                        @php
                            $bStartCairo = \Carbon\CarbonImmutable::instance($b->start_at_utc)->setTimezone(app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone());
                            $bStartStudent = \Carbon\CarbonImmutable::instance($b->start_at_utc)->setTimezone($b->customer_timezone);
                        @endphp
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3.5 font-bold text-slate-900">
                                {{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($bStartCairo) }}
                            </td>
                            <td class="px-4 py-3.5 text-xs text-slate-600">
                                <span class="font-semibold text-slate-900">{{ app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorTime($bStartStudent) }}</span>
                                <span class="text-slate-400">({{ $b->customer_timezone }})</span>
                            </td>
                            <td class="px-4 py-3.5 text-xs font-semibold text-slate-800">
                                {{ $b->sessionType->title ?? 'Tutoring Session' }}
                            </td>
                            <td class="px-4 py-3.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-bold capitalize
                                    {{ $b->status === 'confirmed' ? 'bg-amber-100 text-amber-800' : '' }}
                                    {{ $b->status === 'completed' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                    {{ $b->status === 'no_show' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $b->status === 'cancelled' ? 'bg-slate-100 text-slate-600' : '' }}">
                                    {{ str_replace('_', ' ', $b->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <a href="{{ route('admin.bookings.show', $b->id) }}" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                                    Manage &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400 text-xs">
                                No bookings recorded yet for this student.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Resource Requests & Downloads History -->
    <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
        <div>
            <h2 class="text-base font-bold font-serif text-slate-900">Resource Inquiries & Downloads</h2>
            <p class="text-xs text-slate-500">Learning workbooks and materials requested or downloaded by this student.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3 font-semibold">Resource Title</th>
                        <th class="px-4 py-3 font-semibold">Requested At</th>
                        <th class="px-4 py-3 font-semibold">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($contact->resourceRequests as $req)
                        <tr class="hover:bg-slate-50/70">
                            <td class="px-4 py-3 font-medium text-slate-900">
                                {{ $req->resource->title ?? 'Learning Resource' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $req->created_at ? app(\App\Domains\Timezone\Services\TimezoneDisplayService::class)->administratorDateTime($req->created_at) : '—' }}
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $req->source ? ucfirst($req->source) : 'Direct / Organic' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-6 text-center text-slate-400 text-xs">
                                No resource inquiries recorded.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

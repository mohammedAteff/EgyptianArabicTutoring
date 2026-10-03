@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Students & Contacts Directory</h1>
            <p class="text-sm text-slate-500 mt-1">Single source of truth for all student identities, booking histories, and lead inquiries.</p>
        </div>
        @if($duplicateCount > 0)
            <a href="{{ route('admin.contacts.duplicates') }}" class="inline-flex items-center gap-2 px-3.5 py-2 bg-amber-50 text-amber-900 border border-amber-300 hover:bg-amber-100 rounded-xl text-xs font-bold transition-colors">
                <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                <span>{{ $duplicateCount }} Suspected Duplicate(s) &rarr;</span>
            </a>
        @endif
    </div>

    <x-report-filters :filters="$filters" :action="route('admin.contacts.index')" export-route="admin.contacts.export" :fields="[
        'search'=>['Name, email or phone','search'], 'population'=>['Population',['students'=>'Students only','resources'=>'Resource-only contacts','leads'=>'Other leads / contacts','students_resources'=>'Students + resource contacts','all'=>'All people']], 'resource_id'=>['Resource',$resourceOptions], 'category_id'=>['Category',$categoryOptions], 'booking_status'=>['Booking status',['confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled','no_show'=>'No show']], 'activity_from'=>['Activity from','date'], 'activity_to'=>['Activity through','date']
    ]" />

    <!-- Contacts Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3.5 font-semibold">Student Name</th>
                        <th class="px-4 py-3.5 font-semibold">Email & Phone</th>
                        <th class="px-4 py-3.5 font-semibold">Bookings</th>
                        <th class="px-4 py-3.5 font-semibold">Resources</th>
                        <th class="px-4 py-3.5 font-semibold">Last Active</th>
                        <th class="px-4 py-3.5 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($contacts as $contact)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-4">
                                <div class="font-bold text-slate-900">{{ $contact->name ?? 'Student' }}</div>
                                <div class="text-[11px] text-slate-400 font-mono">{{ $contact->person_type }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-slate-900 font-mono text-xs">{{ $contact->email }}</div>
                                <div class="text-xs text-slate-500">{{ $contact->phone ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold {{ $contact->bookings_count > 0 ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500' }}">
                                    {{ $contact->bookings_count }} lesson(s)
                                </span>
                            </td>
                            <td class="px-4 py-4">
                                <span class="text-xs text-slate-600 font-medium">
                                    {{ $contact->resource_requests_count }} request(s) / {{ $contact->resource_downloads_count }} download(s)
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-500">
                                {{ $contact->last_seen_at ? \Carbon\CarbonImmutable::parse($contact->last_seen_at, 'UTC')->diffForHumans() : '—' }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ ($contact->student_id ? route('admin.students.show', $contact->student_id) : route('admin.contacts.show', $contact->id)) }}" class="inline-flex items-center gap-1 text-xs font-bold text-amber-700 hover:text-amber-800">
                                    <span>Profile</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                <p class="text-sm font-semibold text-slate-600">No student contacts found.</p>
                                <p class="text-xs text-slate-400 mt-1">Student contacts are automatically created upon booking or resource requests.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($contacts->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                {{ $contacts->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

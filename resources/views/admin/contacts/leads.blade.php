@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Student Leads (Content Inquiries)</h1>
            <p class="text-sm text-slate-500 mt-1">Visitors who requested learning workbooks or worksheets but have not yet booked a private session.</p>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.leads') }}" method="GET" class="flex items-center gap-3">
            <div class="flex-1 relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input type="search" name="search" value="{{ $search }}" placeholder="Search lead name, email, or phone..." 
                       class="w-full pl-9 pr-4 py-2 text-sm bg-slate-50 border border-slate-300 rounded-xl focus:outline-none focus:ring-2 focus:ring-amber-500 focus:bg-white transition-all">
            </div>
            <button type="submit" class="px-4 py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-sm font-semibold transition-colors">
                Search
            </button>
            @if($search)
                <a href="{{ route('admin.leads') }}" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-sm font-semibold transition-colors">
                    Clear
                </a>
            @endif
        </form>
    </div>

    <!-- Leads Table -->
    <div class="bg-white rounded-2xl shadow-xs border border-slate-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-slate-500 text-xs uppercase tracking-wider border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-3.5 font-semibold">Lead Name</th>
                        <th class="px-4 py-3.5 font-semibold">Email & Phone</th>
                        <th class="px-4 py-3.5 font-semibold">Resource Downloads</th>
                        <th class="px-4 py-3.5 font-semibold">Acquisition Source</th>
                        <th class="px-4 py-3.5 font-semibold">First Seen</th>
                        <th class="px-4 py-3.5 font-semibold text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($leads as $lead)
                        <tr class="hover:bg-slate-50/70 transition-colors">
                            <td class="px-4 py-4 font-bold text-slate-900">
                                {{ $lead->name ?? 'Prospective Student' }}
                            </td>
                            <td class="px-4 py-4">
                                <div class="font-medium text-slate-900 font-mono text-xs">{{ $lead->email }}</div>
                                <div class="text-xs text-slate-500">{{ $lead->phone ?? '—' }}</div>
                            </td>
                            <td class="px-4 py-4">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800">
                                    {{ $lead->resource_requests_count }} request(s) / {{ $lead->resource_downloads_count }} download(s)
                                </span>
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-600">
                                @if($lead->utm_source)
                                    <span class="font-medium text-slate-900 capitalize">{{ $lead->utm_source }}</span>
                                    @if($lead->utm_campaign)
                                        <span class="text-slate-400">({{ $lead->utm_campaign }})</span>
                                    @endif
                                @else
                                    <span class="text-slate-400">Direct / Organic</span>
                                @endif
                            </td>
                            <td class="px-4 py-4 text-xs text-slate-500">
                                {{ $lead->first_seen_at ? $lead->first_seen_at->format('M j, Y') : '—' }}
                            </td>
                            <td class="px-4 py-4 text-right">
                                <a href="{{ route('admin.contacts.show', $lead->id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-amber-700 hover:text-amber-800">
                                    <span>Profile</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center text-slate-400">
                                <p class="text-sm font-semibold text-slate-600">No unbooked leads at this time.</p>
                                <p class="text-xs text-slate-400 mt-1">Visitors who request resources will appear here automatically.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($leads->hasPages())
            <div class="p-4 border-t border-slate-200 bg-slate-50/50">
                {{ $leads->links() }}
            </div>
        @endif
    </div>

</div>
@endsection

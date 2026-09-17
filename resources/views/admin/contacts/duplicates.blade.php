@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
                <a href="{{ route('admin.contacts.index') }}" class="hover:text-amber-600 transition-colors">&larr; Back to Directory</a>
            </div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Suspected Duplicate Contacts Review</h1>
            <p class="text-sm text-slate-500 mt-1">Safely review and merge student identity records that share identical phone numbers or details.</p>
        </div>
    </div>

    @forelse($duplicateGroups as $group)
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-amber-200 shadow-xs space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                    <h2 class="text-base font-bold font-serif text-slate-900">
                        {{ $group['reason'] }}: <span class="font-mono text-amber-900">{{ $group['value'] }}</span>
                    </h2>
                </div>
                <span class="text-xs font-semibold px-2.5 py-1 bg-amber-50 text-amber-900 rounded-full border border-amber-200">
                    {{ $group['contacts']->count() }} colliding records
                </span>
            </div>

            <!-- Comparison Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach($group['contacts'] as $contact)
                    <div class="bg-slate-50 rounded-2xl p-5 border border-slate-200 flex flex-col justify-between">
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 text-base">{{ $contact->name ?? 'Student' }}</span>
                                <span class="text-xs font-mono text-slate-400">ID #{{ $contact->id }}</span>
                            </div>
                            
                            <div class="text-xs space-y-1.5 text-slate-600">
                                <div><span class="font-semibold text-slate-400 uppercase tracking-wider">Email:</span> <span class="font-mono text-slate-900">{{ $contact->email }}</span></div>
                                <div><span class="font-semibold text-slate-400 uppercase tracking-wider">Phone:</span> <span class="font-mono text-slate-900">{{ $contact->phone ?? '—' }}</span></div>
                                <div><span class="font-semibold text-slate-400 uppercase tracking-wider">First Seen:</span> {{ $contact->first_seen_at ? $contact->first_seen_at->format('M j, Y') : '—' }}</div>
                                <div><span class="font-semibold text-slate-400 uppercase tracking-wider">Bookings:</span> <span class="font-bold text-slate-900">{{ $contact->bookings_count }}</span></div>
                                <div><span class="font-semibold text-slate-400 uppercase tracking-wider">Resource Requests:</span> <span class="font-bold text-slate-900">{{ $contact->resource_requests_count }}</span></div>
                            </div>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-200 flex items-center justify-between">
                            <a href="{{ route('admin.contacts.show', $contact->id) }}" target="_blank" class="text-xs font-semibold text-amber-700 hover:text-amber-800">
                                View Profile &rarr;
                            </a>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Merge Form -->
            @if($group['contacts']->count() === 2)
                @php
                    $contactA = $group['contacts'][0];
                    $contactB = $group['contacts'][1];
                @endphp
                <div class="bg-amber-50/50 rounded-2xl p-4 border border-amber-200/80 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="text-xs text-amber-900">
                        <span class="font-bold">Merge Action:</span> Select which record will remain as the permanent canonical student identity. The other record will have all bookings and resource requests reassigned, and will be safely archived.
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        <!-- Merge B into A -->
                        <form action="{{ route('admin.contacts.merge') }}" method="POST" onsubmit="return confirm('Merge #{{ $contactB->id }} into #{{ $contactA->id }} (Keep #{{ $contactA->id }})?');">
                            @csrf
                            <input type="hidden" name="canonical_id" value="{{ $contactA->id }}">
                            <input type="hidden" name="duplicate_id" value="{{ $contactB->id }}">
                            <button type="submit" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold transition-colors shadow-xs">
                                Keep #{{ $contactA->id }}
                            </button>
                        </form>

                        <!-- Merge A into B -->
                        <form action="{{ route('admin.contacts.merge') }}" method="POST" onsubmit="return confirm('Merge #{{ $contactA->id }} into #{{ $contactB->id }} (Keep #{{ $contactB->id }})?');">
                            @csrf
                            <input type="hidden" name="canonical_id" value="{{ $contactB->id }}">
                            <input type="hidden" name="duplicate_id" value="{{ $contactA->id }}">
                            <button type="submit" class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors shadow-xs">
                                Keep #{{ $contactB->id }}
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    @empty
        <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-xs">
            <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-600 mx-auto flex items-center justify-center mb-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <h2 class="text-base font-bold font-serif text-slate-900">No Suspected Duplicate Contacts</h2>
            <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">All contact identities and phone numbers appear distinct. The system continually verifies student identities.</p>
        </div>
    @endforelse

</div>
@endsection

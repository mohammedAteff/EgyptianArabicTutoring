@extends('layouts.admin')

@section('content')
<div class="space-y-6">

    <!-- Header -->
    <div>
        <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Global Search Results</h1>
        <p class="text-sm text-slate-500 mt-1">Showing matches for query: <span class="font-bold text-slate-900">&ldquo;{{ $query }}&rdquo;</span></p>
    </div>

    @if($contacts->isEmpty() && $bookings->isEmpty() && $resources->isEmpty())
        <div class="bg-white rounded-3xl p-12 text-center border border-slate-200 shadow-xs">
            <svg class="w-12 h-12 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <h2 class="text-base font-bold font-serif text-slate-900">No records found</h2>
            <p class="text-xs text-slate-500 mt-1">Try searching by partial student name, email, phone, or token.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Matching Contacts -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-bold font-serif text-slate-900">Students & Contacts</h2>
                    <span class="text-xs font-bold px-2 py-0.5 bg-amber-100 text-amber-900 rounded-full">{{ $contacts->count() }}</span>
                </div>

                <div class="space-y-3">
                    @forelse($contacts as $c)
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-slate-900 text-xs">{{ $c->name ?? 'Student' }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">{{ $c->email }}</div>
                            </div>
                            <a href="{{ route('admin.contacts.show', $c->id) }}" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                                View &rarr;
                            </a>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-4 text-center">No matching students</div>
                    @endforelse
                </div>
            </div>

            <!-- Matching Bookings -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-bold font-serif text-slate-900">Bookings</h2>
                    <span class="text-xs font-bold px-2 py-0.5 bg-amber-100 text-amber-900 rounded-full">{{ $bookings->count() }}</span>
                </div>

                <div class="space-y-3">
                    @forelse($bookings as $b)
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-slate-900 text-xs">{{ $b->contact->name ?? 'Student' }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">Token: {{ substr($b->confirmation_token, 0, 8) }}...</div>
                                <div class="text-[11px] text-slate-600 mt-0.5">{{ $b->start_at_utc ? $b->start_at_utc->format('M j, Y') : '' }} ({{ $b->status }})</div>
                            </div>
                            <a href="{{ route('admin.bookings.show', $b->id) }}" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                                View &rarr;
                            </a>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-4 text-center">No matching bookings</div>
                    @endforelse
                </div>
            </div>

            <!-- Matching Resources -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <h2 class="text-base font-bold font-serif text-slate-900">Resources</h2>
                    <span class="text-xs font-bold px-2 py-0.5 bg-amber-100 text-amber-900 rounded-full">{{ $resources->count() }}</span>
                </div>

                <div class="space-y-3">
                    @forelse($resources as $r)
                        <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/80 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-slate-900 text-xs">{{ $r->title }}</div>
                                <div class="text-[11px] text-slate-500 font-mono">/resources/{{ $r->slug }}</div>
                            </div>
                            <a href="{{ route('admin.resources.edit', $r->id) }}" class="text-xs font-bold text-amber-700 hover:text-amber-800">
                                Edit &rarr;
                            </a>
                        </div>
                    @empty
                        <div class="text-xs text-slate-400 py-4 text-center">No matching resources</div>
                    @endforelse
                </div>
            </div>

        </div>
    @endif

</div>
@endsection

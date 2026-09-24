@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-[0.18em] text-amber-700">Student records</p>
            <h1 class="mt-2 font-serif text-3xl font-bold tracking-tight text-slate-950">Students</h1>
            <p class="mt-2 max-w-2xl text-sm text-slate-600">Review student contact details, upcoming sessions, and submitted questionnaires.</p>
        </div>
        <form method="GET" action="{{ route('admin.students.index') }}" class="flex w-full gap-2 sm:max-w-md">
            <label class="sr-only" for="student-search">Search students</label>
            <input id="student-search" name="q" value="{{ $search }}" type="search" placeholder="Name, email, or phone"
                   class="min-w-0 flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900 focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-200">
            <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-400">Search</button>
        </form>
    </div>

    @if(session('success'))
        <div role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">{{ session('success') }}</div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="hidden grid-cols-[minmax(12rem,1.3fr)_minmax(12rem,1fr)_minmax(9rem,.8fr)_auto] gap-4 border-b border-slate-200 bg-slate-50 px-5 py-3 text-xs font-bold uppercase tracking-wider text-slate-500 md:grid">
            <span>Student</span><span>Contact</span><span>Timezone</span><span>Record</span>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($students as $student)
                <a href="{{ route('admin.students.show', $student->id) }}" class="grid gap-3 px-5 py-4 transition hover:bg-amber-50/50 focus:bg-amber-50 focus:outline-none md:grid-cols-[minmax(12rem,1.3fr)_minmax(12rem,1fr)_minmax(9rem,.8fr)_auto] md:items-center md:gap-4">
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-slate-900">{{ $student->first_name }} {{ $student->last_name }}</span>
                        <span class="mt-1 block text-xs text-slate-500">Student #{{ $student->id }}</span>
                    </span>
                    <span class="min-w-0 text-sm text-slate-700">
                        <span class="block truncate">{{ $student->email ?: 'No email on file' }}</span>
                        <span class="mt-1 block text-xs text-slate-500">{{ $student->phone ?: 'No phone on file' }}</span>
                    </span>
                    <span class="text-sm text-slate-600">{{ $student->preferred_timezone ?: 'Business timezone' }}</span>
                    <span class="text-sm font-semibold text-amber-800">Open record <span aria-hidden="true">→</span></span>
                </a>
            @empty
                <div class="px-5 py-12 text-center">
                    <p class="font-semibold text-slate-800">No student records found</p>
                    <p class="mt-1 text-sm text-slate-500">Try a different name, email, or phone number.</p>
                </div>
            @endforelse
        </div>
        @if($students->hasPages())
            <div class="border-t border-slate-200 px-5 py-4">{{ $students->links() }}</div>
        @endif
    </div>
</div>
@endsection

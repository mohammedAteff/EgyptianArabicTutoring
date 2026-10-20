@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-6xl space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div><h1 class="font-serif text-3xl font-bold">All student birthdays</h1><p class="mt-2 text-sm text-slate-600">The whole year in one place · {{ $total }} {{ $total === 1 ? 'birthday' : 'birthdays' }}. Active students with a recorded birthday appear here. Visible to staff only; students see only their own birthday.</p></div>
        <a href="{{ route('admin.birthdays.index') }}" class="inline-flex min-h-11 items-center rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold">← Birthday calendar</a>
    </div>
    @forelse($birthdayGroups as $birthdays)
    <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6">
        <h2 class="text-xl font-bold">{{ $birthdays->first()['date']->format('F') }}</h2>
        <ul class="mt-4 divide-y divide-slate-100">
            @foreach($birthdays as $birthday)
            <li class="flex flex-wrap items-center justify-between gap-3 py-4">
                <div><p class="font-semibold">{{ $birthday['student']->name }}</p><p class="mt-1 text-sm text-slate-500">{{ $birthday['student']->date_of_birth->format('j F') }}</p></div>
                <a class="text-sm font-semibold text-amber-800" href="{{ route('admin.students.show', $birthday['student']) }}">Open student profile →</a>
            </li>
            @endforeach
        </ul>
    </section>
    @empty
    <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-sm text-slate-500">No recorded birthdays for active students yet.</p>
    @endforelse
</div>
@endsection

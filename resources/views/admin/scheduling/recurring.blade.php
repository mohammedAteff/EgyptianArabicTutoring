@extends('layouts.admin')
@section('content')
<div class="mx-auto max-w-6xl space-y-6"><div><h1 class="font-serif text-3xl font-bold">Recurring lessons</h1><p class="mt-2 text-sm text-slate-600">Generate up to 12 occurrences at a time. Each lesson needs an available time and its own valid funding. Repeating a batch is safe.</p><a class="mt-3 inline-block text-sm font-semibold text-amber-800" href="{{ route('admin.students.index') }}">Open a student to create a plan →</a></div><x-recurring-plans :plans="$plans" /></div>
@endsection

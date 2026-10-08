@props(['student'])
<nav aria-label="Student quick actions" class="mb-5 flex flex-wrap gap-2 rounded-xl border border-slate-200 bg-white p-4 text-sm font-semibold text-amber-800">
    <a class="rounded-lg bg-amber-50 px-3 py-3" href="{{ route('admin.tasks.index', ['student_id' => $student->id]) }}">Create staff task</a>
    <a class="rounded-lg bg-slate-50 px-3 py-3" href="{{ route('admin.student-bins.index', $student->id) }}">Educational notes</a>
    @if(auth('web')->user()?->isAdmin())
        @can('manageTeaching', $student)<a class="rounded-lg bg-amber-700 px-4 py-3 text-white" href="{{ route('admin.students.learning.create', $student->id) }}">Assign Learning</a>@endcan
        <a class="rounded-lg bg-slate-50 px-3 py-3" href="{{ route('admin.students.scheduling', $student->id) }}">Scheduling & holidays</a>
        <a class="rounded-lg bg-slate-50 px-3 py-3" href="{{ route('admin.receivables.index', ['student_id' => $student->id, 'state' => 'all']) }}">Statements & renewal</a>
        <a class="rounded-lg bg-slate-50 px-3 py-3" href="{{ route('admin.billing.cashier', ['student_id' => $student->id]) }}">Open Cashier</a>
        <a class="rounded-lg bg-slate-50 px-3 py-3" href="{{ route('admin.bookings.create', ['student_id' => $student->id]) }}">Book session</a>
        <a class="rounded-lg bg-slate-50 px-3 py-3" href="{{ route('admin.students.teaching', $student->id) }}#teaching-resource">Assign resource</a>
    @endif
    @if($student->email)<a class="rounded-lg bg-slate-50 px-3 py-3" href="mailto:{{ $student->email }}">Email student</a>@endif
    @if($student->phone)<a class="rounded-lg bg-slate-50 px-3 py-3" href="tel:{{ $student->phone }}">Call student</a>@endif
</nav>

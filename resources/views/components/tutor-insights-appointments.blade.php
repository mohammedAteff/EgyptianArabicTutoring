@props(['records', 'label', 'upcoming' => false])
<div class="insights-table-scroll" role="region" aria-label="{{ $label }}" tabindex="0">
<table class="insights-table min-w-[42rem]">
    <caption class="sr-only">{{ $label }} · 20 records per page; exports include all matching rows</caption>
    <thead><tr><th scope="col">Appointment</th><th scope="col">Student</th><th scope="col">Recorded type</th><th scope="col">Current outcome</th><th scope="col">Schedule (UTC)</th><th scope="col">Local date</th><th scope="col">Stored interval</th></tr></thead>
    <tbody>@forelse($records as $record)<tr>
        <th scope="row">#{{ $record->record_id }}</th><td>{{ $record->student_id === null ? 'Unlinked' : '#'.$record->student_id }}</td>
        <td>{{ $record->session_type }}@if($record->session_type_id)<small class="insights-muted block">Type #{{ $record->session_type_id }}</small>@endif</td>
        <td>{{ App\Domains\Reporting\Services\TutorInsightsContext::OUTCOMES[$record->status] ?? $record->status }}</td>
        <td>{{ $record->interval_start }}<br>to {{ $record->interval_end ?? 'unavailable' }}</td><td>{{ $record->local_date }}</td>
        <td>{{ $record->duration_seconds === null ? 'Unavailable' : sprintf('%d:%02d:%02d', intdiv((int) $record->duration_seconds, 3600), intdiv((int) $record->duration_seconds % 3600, 60), (int) $record->duration_seconds % 60) }}@if($upcoming)<small class="insights-muted block">Scheduled interval; not delivered teaching</small>@endif</td>
    </tr>@empty<tr><td colspan="7">No qualifying appointments in this scope.</td></tr>@endforelse</tbody>
</table></div>
{{ $records->links() }}

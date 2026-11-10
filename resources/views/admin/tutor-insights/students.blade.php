@extends('layouts.admin', ['title' => 'Find a student · Tutor Insights'])
@section('content')
<div class="learning-insights min-w-0 space-y-6">
    <h1 class="text-2xl font-semibold">Find a student</h1>
    <p class="insights-muted">Search retained student records by first name, last name, or student ID. At most 20 results appear per page.</p>
    <form method="GET" class="insights-panel space-y-3">
        @foreach(Illuminate\Support\Arr::except(request()->query(), ['q', 'page', 'student_id']) as $key => $value)@if(is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
        <label class="insights-label">Find a student<input type="search" name="q" maxlength="100" value="{{ $search }}" class="insights-control"></label>
        <button class="insights-button insights-button-primary">Search</button>
    </form>
    @if($errors->any())<div role="alert" class="insights-panel">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <div class="insights-panel">@forelse($students as $student)<p><a class="insights-link" href="{{ route('admin.tutor-insights.index', array_replace(Illuminate\Support\Arr::except(request()->query(), ['q', 'page']), ['student_id' => $student->id])) }}">{{ $student->first_name }} {{ $student->last_name }} · #{{ $student->id }}</a></p>@empty<p>{{ $search === '' ? 'Enter a name or student ID to search.' : 'No matching retained students.' }}</p>@endforelse</div>
    {{ $students->links() }}
    <a class="insights-link" href="{{ route('admin.tutor-insights.index', Illuminate\Support\Arr::except(request()->query(), ['q', 'page'])) }}">Back to Tutor Insights</a>
</div>
@endsection

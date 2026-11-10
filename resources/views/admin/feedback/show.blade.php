@extends('layouts.admin', ['title' => 'Feedback'])
@section('content')
<x-estimated-activity-bootstrap :contexts="$activityContexts ?? []" />
<div id="activity-feedback">@include('components.feedback-thread')</div>
@endsection

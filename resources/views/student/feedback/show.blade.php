@extends('layouts.student', ['title' => __('feedback.nav')])
@section('content')
<div class="student-feedback">
    @include('components.feedback-thread')
</div>
@endsection

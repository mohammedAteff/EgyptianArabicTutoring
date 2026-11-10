@extends('layouts.admin', ['title' => 'Feedback'])
@section('content')
<div class="min-w-0 space-y-6">
    <h1 class="text-3xl font-semibold">{{ __('feedback.inbox') }}</h1>
    @include('components.feedback-list', ['admin' => true, 'baseRoute' => 'admin.feedback'])
</div>
@endsection

@extends('layouts.admin', ['title' => 'Shared Tutor Work · Activation'])
@section('content')
<div class="learning-insights mx-auto max-w-4xl space-y-6">
    <header class="space-y-2"><p class="insights-eyebrow">Tutor Insights · Shared workspace</p><h1 class="text-3xl font-semibold">Shared Tutor Work awaits activation</h1></header>
    <section class="insights-panel space-y-3"><h2 class="text-xl font-semibold">{{ $migrationPending ? 'Database migration pending' : 'Legacy transition decision pending' }}</h2><p>{{ $migrationPending ? 'The shared workspace migration must be applied to this explicitly approved environment before work tracking can open.' : 'Existing account-owned records are preserved outside the shared ledger. An explicit adoption or preservation decision and cutover are required before new shared work can be saved.' }}</p><p>No existing history has been automatically combined or removed.</p></section>
    <a class="insights-button" href="{{ route('admin.tutor-insights.index') }}">Teaching &amp; appointments</a><a class="insights-button" href="{{ route('admin.tutor-insights.index', ['view' => 'operations']) }}">Operational activity</a>
</div>
@endsection

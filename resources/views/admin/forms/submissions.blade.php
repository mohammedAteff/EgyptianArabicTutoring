@extends('layouts.admin')

@section('content')
<div class="mb-4"><a href="{{ route('admin.forms.index') }}" class="text-sm font-medium text-nile-800 underline">← Forms</a><h1 class="mt-3 text-2xl font-semibold">{{ $form->title }} responses</h1><p class="mt-1 text-sm text-stone-600">Drafts and submitted responses remain available to authorized staff. Assistant accounts see only questions marked assistant-visible. Date filters use creation time in the business timezone.</p></div>
<x-report-filters :filters="$filters" :fields="$filterFields" :action="route('admin.forms.submissions', $form)" export-route="admin.forms.export" :fixed="['form' => $form->id]" :allow-export="true" />
<div class="mt-6 space-y-4">
@forelse($submissions as $submission)
    <article class="rounded-xl border border-stone-200 bg-white p-5">
        <div class="flex flex-wrap justify-between gap-2"><h2 class="font-semibold">Submission #{{ $submission->id }} · {{ $submission->student?->first_name }} {{ $submission->student?->last_name }}</h2><span class="text-sm text-stone-500">{{ ucfirst($submission->status) }} · v{{ $submission->version->version_number }} · {{ $submission->submitted_at?->timezone($businessTimezone)->format('Y-m-d H:i') ?? 'Not submitted' }}</span></div>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2">@foreach($submission->answers as $answer)<div class="rounded-lg bg-stone-50 p-3"><dt class="text-xs font-semibold text-stone-600">{{ $answer->question?->label }}</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-stone-900">{{ $answer->value_text }}</dd></div>@endforeach</dl>
    </article>
@empty
    <p class="rounded-xl border border-stone-200 bg-white p-6 text-stone-600">No responses yet.</p>
@endforelse
</div>
<div class="mt-4">{{ $submissions->links() }}</div>
@endsection

@extends('layouts.admin')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4"><div><a href="{{ route('admin.forms.index') }}" class="text-sm font-medium text-nile-800 underline">← Forms</a><h1 class="mt-3 text-2xl font-semibold">{{ $form->title }} responses</h1><p class="mt-1 text-sm text-stone-600">Only configured assistant-visible questions are loaded for assistant accounts.</p></div><a href="{{ route('admin.forms.export', $form) }}" class="rounded-lg border border-stone-300 px-4 py-2 text-sm font-semibold hover:bg-white">Export CSV</a></div>
<form method="GET" class="mt-4 flex flex-wrap items-end gap-3"><label class="text-sm font-medium">Version<select name="version_id" class="mt-1 block rounded-lg border-stone-300">@foreach($versions as $version)<option value="{{ $version->id }}" @selected($versionId === $version->id)>Version {{ $version->version_number }}{{ $version->id === $form->active_version_id ? ' (current)' : '' }}</option>@endforeach</select></label><button class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold">Show version</button><a href="{{ route('admin.forms.export', [$form, 'version_id' => $versionId]) }}" class="rounded-lg border border-stone-300 px-3 py-2 text-sm font-semibold">Export this version</a></form>
<div class="mt-6 space-y-4">
@forelse($submissions as $submission)
    <article class="rounded-xl border border-stone-200 bg-white p-5">
        <div class="flex flex-wrap justify-between gap-2"><h2 class="font-semibold">Submission #{{ $submission->id }} · {{ $submission->student?->first_name }} {{ $submission->student?->last_name }}</h2><span class="text-sm text-stone-500">{{ ucfirst($submission->status) }} · v{{ $submission->version->version_number }} · {{ $submission->submitted_at?->timezone(config('business.timezone'))->format('Y-m-d H:i') ?? 'Not submitted' }}</span></div>
        <dl class="mt-4 grid gap-3 sm:grid-cols-2">@foreach($submission->answers as $answer)<div class="rounded-lg bg-stone-50 p-3"><dt class="text-xs font-semibold text-stone-600">{{ $answer->question?->label }}</dt><dd class="mt-1 whitespace-pre-wrap text-sm text-stone-900">{{ $answer->value_text }}</dd></div>@endforeach</dl>
    </article>
@empty
    <p class="rounded-xl border border-stone-200 bg-white p-6 text-stone-600">No responses yet.</p>
@endforelse
</div>
<div class="mt-4">{{ $submissions->links() }}</div>
@endsection

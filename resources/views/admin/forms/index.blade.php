@extends('layouts.admin')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-4">
    <div><h1 class="text-2xl font-semibold text-nile-900">Student forms</h1><p class="mt-1 text-sm text-stone-600">Versioned questionnaires and their student responses.</p></div>
    @if($canManageForms)<a href="{{ route('admin.forms.create') }}" class="rounded-lg bg-nile-800 px-4 py-2 text-sm font-semibold text-white hover:bg-nile-900">Create form</a>@endif
</div>
@if(session('success'))<p role="status" class="mt-5 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</p>@endif
<div class="mt-6 overflow-x-auto rounded-xl border border-stone-200 bg-white">
    <table class="min-w-full divide-y divide-stone-200 text-sm">
        <thead class="bg-stone-50 text-left text-stone-600"><tr><th class="px-4 py-3">Form</th><th class="px-4 py-3">Trigger</th><th class="px-4 py-3">Version</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Actions</th></tr></thead>
        <tbody class="divide-y divide-stone-100">
        @forelse($forms as $form)
            <tr><td class="px-4 py-3"><p class="font-semibold text-stone-900">{{ $form->title }}</p><p class="text-xs text-stone-500">/{{ $form->slug }}</p></td><td class="px-4 py-3">{{ str($form->prompt_trigger)->replace('_', ' ')->title() }}</td><td class="px-4 py-3">{{ $form->activeVersion?->version_number ?? '—' }}@if($form->published_version_id && (int) $form->published_version_id !== (int) $form->active_version_id)<span class="block text-xs text-amber-800">Draft · live {{ $form->publishedVersion?->version_number }}</span>@endif</td><td class="px-4 py-3">{{ str($form->status)->title() }}</td><td class="space-x-3 px-4 py-3">@if($canManageForms)<a class="font-medium text-nile-800 underline" href="{{ route('admin.forms.edit', $form) }}">Edit</a>@endif<a class="font-medium text-nile-800 underline" href="{{ route('admin.forms.submissions', $form) }}">Responses</a><a class="font-medium text-nile-800 underline" href="{{ route('admin.forms.export', $form) }}">CSV</a></td></tr>
        @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-stone-500">No forms have been created.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">{{ $forms->links() }}</div>
@endsection

@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-slate-900">Reconcile {{ strtoupper($locale) }} Translation</h1>
            <p class="text-xs text-slate-500 mt-1">
                The English source content has evolved since this draft was created. Compare the revisions below and reconcile your translation.
            </p>
        </div>
        <a href="javascript:history.back()" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
            &larr; Back to Editor
        </a>
    </div>

    <!-- Snapshots Comparison Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Outdated Source Snapshot -->
        <div class="bg-amber-50/60 rounded-xl border border-amber-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase text-amber-800 tracking-wider">
                    Source Revision #{{ $draft->source_revision_id ?? '1' }} (Snapshot when Draft was Started)
                </span>
            </div>
            <div class="space-y-3 text-xs text-slate-700">
                <div>
                    <span class="font-bold text-slate-900">Title / Name:</span>
                    <p class="mt-0.5 p-2 bg-white rounded border border-amber-100 font-mono">{{ $sourceRevision?->title ?? '—' }}</p>
                </div>
                <div>
                    <span class="font-bold text-slate-900">Content / Description:</span>
                    <pre class="mt-0.5 p-2 bg-white rounded border border-amber-100 font-mono whitespace-pre-wrap">{{ is_array($sourceRevision?->content) ? json_encode($sourceRevision->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($sourceRevision?->description ?? '—') }}</pre>
                </div>
            </div>
        </div>

        <!-- Current English Source Snapshot -->
        <div class="bg-emerald-50/60 rounded-xl border border-emerald-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold uppercase text-emerald-800 tracking-wider">
                    Current English Revision #{{ $currentRevision?->revision_number ?? '1' }} (Latest Authoritative Source)
                </span>
            </div>
            <div class="space-y-3 text-xs text-slate-700">
                <div>
                    <span class="font-bold text-slate-900">Title / Name:</span>
                    <p class="mt-0.5 p-2 bg-white rounded border border-emerald-100 font-mono">{{ $currentRevision?->title ?? '—' }}</p>
                </div>
                <div>
                    <span class="font-bold text-slate-900">Content / Description:</span>
                    <pre class="mt-0.5 p-2 bg-white rounded border border-emerald-100 font-mono whitespace-pre-wrap">{{ is_array($currentRevision?->content) ? json_encode($currentRevision->content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : ($currentRevision?->description ?? '—') }}</pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Reconciliation Form -->
    <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-xs">
        <h2 class="text-sm font-bold text-slate-900 mb-4">Reconciled {{ strtoupper($locale) }} Draft</h2>

        <form method="POST" action="{{ route('admin.translations.save-draft', ['entityType' => $entityType, 'id' => $entity->getKey(), 'locale' => $locale]) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="reconcile_to_revision" value="{{ $currentRevision?->revision_number ?? 1 }}">

            @if($entity->getEntityType() === 'resource')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Title ({{ strtoupper($locale) }})</label>
                    <input type="text" name="title" value="{{ old('title', $draft?->title) }}" required class="w-full rounded-lg border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Short Description ({{ strtoupper($locale) }})</label>
                    <textarea name="short_description" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('short_description', $draft?->short_description) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Full Description ({{ strtoupper($locale) }})</label>
                    <textarea name="full_description" rows="5" class="w-full rounded-lg border-slate-200 text-sm">{{ old('full_description', $draft?->full_description) }}</textarea>
                </div>
            @elseif($entity->getEntityType() === 'game')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Title ({{ strtoupper($locale) }})</label>
                    <input type="text" name="title" value="{{ old('title', $draft?->title) }}" required class="w-full rounded-lg border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description ({{ strtoupper($locale) }})</label>
                    <textarea name="description" rows="4" class="w-full rounded-lg border-slate-200 text-sm">{{ old('description', $draft?->description) }}</textarea>
                </div>
            @elseif($entity->getEntityType() === 'page')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Title ({{ strtoupper($locale) }})</label>
                    <input type="text" name="title" value="{{ old('title', $draft?->title) }}" required class="w-full rounded-lg border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Content ({{ strtoupper($locale) }})</label>
                    <textarea name="content" rows="6" class="w-full rounded-lg border-slate-200 text-sm">{{ old('content', $draft?->content) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Excerpt ({{ strtoupper($locale) }})</label>
                    <textarea name="excerpt" rows="2" class="w-full rounded-lg border-slate-200 text-sm">{{ old('excerpt', $draft?->excerpt) }}</textarea>
                </div>
            @elseif($entity->getEntityType() === 'faq')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Question ({{ strtoupper($locale) }})</label>
                    <input type="text" name="question" value="{{ old('question', $draft?->question) }}" required class="w-full rounded-lg border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Answer ({{ strtoupper($locale) }})</label>
                    <textarea name="answer" rows="4" class="w-full rounded-lg border-slate-200 text-sm">{{ old('answer', $draft?->answer) }}</textarea>
                </div>
            @elseif($entity->getEntityType() === 'category')
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Name ({{ strtoupper($locale) }})</label>
                    <input type="text" name="name" value="{{ old('name', $draft?->name) }}" required class="w-full rounded-lg border-slate-200 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase mb-1">Description ({{ strtoupper($locale) }})</label>
                    <textarea name="description" rows="3" class="w-full rounded-lg border-slate-200 text-sm">{{ old('description', $draft?->description) }}</textarea>
                </div>
            @endif

            <div class="flex items-center gap-3 pt-4 border-t border-slate-100">
                <button type="submit" class="px-4 py-2 bg-slate-800 hover:bg-slate-900 text-white font-semibold text-xs rounded-lg transition-colors">
                    Save Reconciled Draft
                </button>
                <button type="submit" formaction="{{ route('admin.translations.publish', ['entityType' => $entityType, 'id' => $entity->getKey(), 'locale' => $locale]) }}" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs rounded-lg transition-colors">
                    Save & Publish Live Translation
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

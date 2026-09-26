@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
            <a href="{{ route('admin.resources.index') }}" class="hover:text-amber-600 transition-colors">&larr; Back to Resources</a>
        </div>
        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('resources.preview', now()->addHours(24), ['slug' => $resource->slug]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 rounded-xl text-xs font-semibold transition-colors">
            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span>Preview Resource</span>
        </a>
    </div>
    <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Edit Resource — {{ $resource->title }}</h1>

@php
    $draftContent = $draftRevision?->content ?? [];
    $hasDraft = (bool) $draftRevision;
    $valTitle = old('title', $draftRevision?->title ?? $resource->title);
    $valSlug = old('slug', $draftContent['slug'] ?? $resource->slug);
    $valCategory = old('category_id', $draftContent['category_id'] ?? $resource->category_id);
    $valFileType = old('file_type', $draftContent['file_type'] ?? $resource->file_type);
    $valSortOrder = old('sort_order', $draftContent['sort_order'] ?? $resource->sort_order);
    $valDescription = old('description', $draftContent['short_description'] ?? $resource->short_description ?? $resource->description);
    $valIsGated = old('is_gated', isset($draftContent['is_gated']) ? ($draftContent['is_gated'] ? '1' : '0') : ($resource->is_gated ? '1' : '0'));
    $valStatus = old('status', $resource->status);
    $valCover = old('cover_image_path', $draftContent['cover_image_path'] ?? $resource->cover_image_path);
    $valExternalUrl = old('external_url', $draftContent['external_url'] ?? $resource->external_url);
    $activeFile = $draftContent['file_path'] ?? $resource->file_path;
@endphp

    <!-- Form Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        @if($hasDraft)
            <div class="p-4 rounded-2xl bg-amber-50 border border-amber-200 text-amber-900 flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-amber-200 text-amber-900 shrink-0">Draft Revision #{{ $draftRevision->revision_number }}</span>
                    <span class="text-xs font-medium"><strong class="font-bold">Unpublished Draft Revision Pending:</strong> Form inputs are loaded from your unpublished draft saved {{ $draftRevision->created_at->diffForHumans() }}. Live site displays the published version until you click "Publish Resource".</span>
                </div>
                <button type="submit" form="discard-draft-form" class="text-xs font-bold text-red-600 hover:text-red-800 underline shrink-0">
                    Discard Draft
                </button>
            </div>
        @endif

        <form id="resource-edit-form" action="{{ route('admin.resources.update', $resource->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Title</label>
                    <input type="text" name="title" value="{{ $valTitle }}" required 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Slug (URL)</label>
                    <input type="text" name="slug" value="{{ $valSlug }}" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    @error('slug') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Category -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Category</label>
                    <select name="category_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ $valCategory == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- File Type -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">File Type</label>
                    <select name="file_type" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="pdf" {{ $valFileType == 'pdf' ? 'selected' : '' }}>PDF Document</option>
                        <option value="audio" {{ $valFileType == 'audio' ? 'selected' : '' }}>Audio Track (MP3)</option>
                        <option value="zip" {{ $valFileType == 'zip' ? 'selected' : '' }}>ZIP Archive</option>
                        <option value="doc" {{ $valFileType == 'doc' ? 'selected' : '' }}>Word Document</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ $valSortOrder }}" min="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Description & Overview</label>
                <textarea name="description" rows="4" 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ $valDescription }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Gate Toggle -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Access Gate</label>
                    <select name="is_gated" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="1" {{ $valIsGated == '1' ? 'selected' : '' }}>Email Gated (Captures Student Lead)</option>
                        <option value="0" {{ $valIsGated == '0' ? 'selected' : '' }}>Direct Public Download (Ungated)</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Publication Status</label>
                    <select name="status" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="published" {{ $valStatus == 'published' ? 'selected' : '' }}>Published (Live on Website)</option>
                        <option value="draft" {{ $valStatus == 'draft' ? 'selected' : '' }}>Draft (Hidden)</option>
                        <option value="archived" {{ $valStatus == 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="pt-4 border-t border-slate-100">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Cover Image / Thumbnail</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <div>
                        <div class="flex gap-2">
                            <input type="text" id="cover_image_path" name="cover_image_path" value="{{ $valCover }}"
                                   placeholder="e.g. media/verbs-guide-cover.webp"
                                   class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <button type="button" onclick="openMediaPicker('cover_image_path', 'cover_image_preview')" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 shrink-0 transition-colors">
                                Choose from Library
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Select from library, enter relative path, or upload a new file.</p>
                    </div>
                    <div>
                        <input type="file" name="cover_file" accept="image/*" class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-3 {{ $valCover ? '' : 'hidden' }}">
                    <img id="cover_image_preview" src="{{ $valCover ? (str_starts_with($valCover, 'http') ? $valCover : \Illuminate\Support\Facades\Storage::disk('public')->url($valCover)) : '' }}" alt="Current Cover" class="h-14 w-14 rounded-xl object-cover border border-slate-200">
                    <span class="text-xs text-slate-500 font-mono">{{ $valCover }}</span>
                </div>
            </div>

            <!-- External URL -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">External Resource URL (Optional)</label>
                <input type="url" name="external_url" value="{{ $valExternalUrl }}"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                       placeholder="https://example.com/guide.pdf">
                <p class="text-xs text-slate-400 mt-1">If provided, visitor downloads will redirect directly to this verified external URL (must use https:// or http://).</p>
                @error('external_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- File Upload -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Replace Resource File (Optional)</label>
                <input type="file" name="file" class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                @if($activeFile)
                    <p class="text-xs text-slate-500 mt-1">Current file: <span class="font-mono text-slate-700">{{ $activeFile }}</span></p>
                @endif
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="{{ route('admin.resources.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" name="action" value="draft" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold transition-colors shadow-xs">
                    Save as Draft
                </button>
                <button type="submit" name="action" value="publish" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                    Publish Resource
                </button>
            </div>
        </form>

        @if($hasDraft)
            <form id="discard-draft-form" action="{{ route('admin.resources.draft.destroy', $resource->id) }}" method="POST" class="hidden" onsubmit="return confirm('Discard this draft revision and revert to live values?');">
                @csrf
                @method('DELETE')
            </form>
        @endif
    </div>

    <!-- Translations & Localization Panel -->
    <x-admin.translations-panel :entity="$resource" entity-type="resource" />

</div>
@endsection

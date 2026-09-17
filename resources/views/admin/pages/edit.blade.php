@extends('layouts.admin')

@section('content')
<div class="space-y-8">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Edit Page — {{ $page->title }}</h1>
            <p class="text-sm text-slate-500 mt-1">Update content, publication status, or roll back to an earlier revision.</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('pages.preview', now()->addHours(24), ['slug' => $page->slug]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 rounded-xl text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Preview Draft</span>
            </a>
            @if($page->isPublished())
                <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-semibold transition-colors">
                    <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    <span>View Public Page</span>
                </a>
            @endif
            <a href="{{ route('admin.pages.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
                &larr; Back to Pages
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <svg class="w-5 h-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Main Form (2 cols) -->
        <div class="lg:col-span-2 bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
            <form action="{{ route('admin.pages.update', $page->id) }}" method="POST" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Page Title</label>
                        <input type="text" id="title" name="title" value="{{ old('title', $page->title) }}" required
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label for="slug" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">URL Slug</label>
                        <input type="text" id="slug" name="slug" value="{{ old('slug', $page->slug) }}" required
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    </div>
                </div>

                <div>
                    <label for="excerpt" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Short Excerpt / Summary</label>
                    <textarea id="excerpt" name="excerpt" rows="2"
                              class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('excerpt', $page->excerpt) }}</textarea>
                </div>

                <div>
                    <label for="content" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Page Body Content</label>
                    <textarea id="content" name="content" rows="12"
                              class="w-full p-4 bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none leading-relaxed">{{ old('content', $page->content) }}</textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                    <div>
                        <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Publication Status</label>
                        <select id="status" name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <option value="draft" {{ old('status', $page->status) === 'draft' ? 'selected' : '' }}>Draft (Private)</option>
                            <option value="published" {{ old('status', $page->status) === 'published' ? 'selected' : '' }}>Published</option>
                            <option value="archived" {{ old('status', $page->status) === 'archived' ? 'selected' : '' }}>Archived</option>
                        </select>
                    </div>

                    <div>
                        <label for="seo_title" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">SEO Meta Title</label>
                        <input type="text" id="seo_title" name="seo_title" value="{{ old('seo_title', $page->seo_title) }}"
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>

                    <div>
                        <label for="seo_description" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">SEO Meta Description</label>
                        <input type="text" id="seo_description" name="seo_description" value="{{ old('seo_description', $page->seo_description) }}"
                               class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div class="pt-4 border-t border-slate-100">
                    <label for="og_image_path" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Featured / Social Share Image Path</label>
                    <div class="flex gap-3 items-center">
                        <input type="text" id="og_image_path" name="og_image_path" value="{{ old('og_image_path', $page->og_image_path) }}"
                               class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                               placeholder="media/about-tutor.webp">
                        <button type="button" onclick="openMediaPicker('og_image_path', 'og_image_preview')" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 shrink-0 transition-colors">
                            Choose from Media Library
                        </button>
                        <div class="h-10 w-10 rounded-lg overflow-hidden border border-slate-200 shrink-0 {{ $page->og_image_path ? '' : 'hidden' }}">
                            <img id="og_image_preview" src="{{ $page->og_image_path ? (str_starts_with($page->og_image_path, 'http') ? $page->og_image_path : \Illuminate\Support\Facades\Storage::disk('public')->url($page->og_image_path)) : '' }}" alt="Preview" class="h-full w-full object-cover">
                        </div>
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Select an asset from the <a href="{{ route('admin.media.index') }}" target="_blank" class="text-amber-600 underline">Media Library</a> or enter its relative storage path.</p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('admin.pages.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Cancel
                    </a>
                    <button type="submit" name="action" value="draft" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold transition-colors shadow-xs">
                        Save as Draft
                    </button>
                    <button type="submit" name="action" value="publish" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors font-serif shadow-xs">
                        Publish Page
                    </button>
                </div>
            </form>
        </div>

        <!-- Revisions Sidebar (1 col) -->
        <div class="space-y-6">
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900">Revision History</h2>
                    <span class="text-xs text-slate-400 font-mono">{{ count($revisions) }} saved</span>
                </div>

                <div class="space-y-3 max-h-[500px] overflow-y-auto">
                    @forelse($revisions as $rev)
                        <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-xs text-slate-900">Revision #{{ $rev->revision_number }}</span>
                                <span class="text-[10px] text-slate-500">{{ $rev->created_at->format('M j, Y H:i') }}</span>
                            </div>
                            <p class="text-xs text-slate-600 truncate font-mono">{{ Str::limit($rev->content['body'] ?? '', 60) }}</p>

                            <form action="{{ route('admin.pages.revisions.restore', ['page' => $page->id, 'revision' => $rev->id]) }}" method="POST" onsubmit="return confirm('Restore content from Revision #{{ $rev->revision_number }}?');">
                                @csrf
                                <button type="submit" class="w-full mt-2 py-1.5 px-3 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-lg text-xs font-semibold transition-colors text-center">
                                    Restore this version
                                </button>
                            </form>
                        </div>
                    @empty
                        <div class="py-6 text-center text-slate-400 text-xs">
                            No earlier revisions recorded yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

</div>
@endsection

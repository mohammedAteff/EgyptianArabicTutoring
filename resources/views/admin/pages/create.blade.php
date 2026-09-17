@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Create Page</h1>
            <p class="text-sm text-slate-500 mt-1">Add a new standalone page to the Egyptian Arabic tutoring website.</p>
        </div>
        <a href="{{ route('admin.pages.index') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900">
            &larr; Back to Pages
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-800 text-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.pages.store') }}" method="POST" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="title" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Page Title</label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                           placeholder="e.g. Student FAQ or Curriculum Overview">
                </div>

                <div>
                    <label for="slug" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">URL Slug</label>
                    <input type="text" id="slug" name="slug" value="{{ old('slug') }}" required
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono"
                           placeholder="curriculum-overview">
                </div>
            </div>

            <div>
                <label for="excerpt" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Short Excerpt / Summary</label>
                <textarea id="excerpt" name="excerpt" rows="2"
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                          placeholder="Brief description for SEO and previews...">{{ old('excerpt') }}</textarea>
            </div>

            <div>
                <label for="content" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Page Body Content (Markdown / HTML supported)</label>
                <textarea id="content" name="content" rows="12"
                          class="w-full p-4 bg-slate-50 border border-slate-300 rounded-xl text-sm font-mono focus:ring-2 focus:ring-amber-500 focus:outline-none leading-relaxed"
                          placeholder="# Page Heading&#10;&#10;Write page body text here...">{{ old('content') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t border-slate-100">
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Publication Status</label>
                    <select id="status" name="status" class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft (Private)</option>
                        <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>Published (Live immediately)</option>
                        <option value="archived" {{ old('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>

                <div>
                    <label for="seo_title" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">SEO Meta Title (Optional)</label>
                    <input type="text" id="seo_title" name="seo_title" value="{{ old('seo_title') }}"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label for="seo_description" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">SEO Meta Description</label>
                    <input type="text" id="seo_description" name="seo_description" value="{{ old('seo_description') }}"
                           class="w-full px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100">
                <label for="og_image_path" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 mb-1.5">Featured / Social Share Image Path</label>
                <div class="flex gap-3 items-center">
                    <input type="text" id="og_image_path" name="og_image_path" value="{{ old('og_image_path') }}"
                           class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                           placeholder="media/about-tutor.webp">
                    <button type="button" onclick="openMediaPicker('og_image_path', 'og_image_preview')" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 shrink-0 transition-colors">
                        Choose from Media Library
                    </button>
                    <div class="h-10 w-10 rounded-lg overflow-hidden border border-slate-200 shrink-0 hidden">
                        <img id="og_image_preview" src="" alt="Preview" class="h-full w-full object-cover">
                    </div>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Select an asset from the <a href="{{ route('admin.media.index') }}" target="_blank" class="text-amber-600 underline">Media Library</a> or enter its relative storage path.</p>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                <a href="{{ route('admin.pages.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" name="status" value="draft" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold transition-colors shadow-xs">
                    Save as Draft
                </button>
                <button type="submit" name="status" value="published" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors font-serif shadow-xs">
                    Save and Publish Page
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

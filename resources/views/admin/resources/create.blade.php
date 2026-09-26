@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
        <a href="{{ route('admin.resources.index') }}" class="hover:text-amber-600 transition-colors">&larr; Back to Resources</a>
    </div>
    <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Add Learning Resource</h1>

    <!-- Form Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.resources.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Title -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Title</label>
                    <input type="text" name="title" value="{{ old('title') }}" required 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                           placeholder="e.g. 100 Common Egyptian Arabic Verbs Guide">
                    @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Slug -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Slug (URL)</label>
                    <input type="text" name="slug" value="{{ old('slug') }}" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                           placeholder="Leave empty to auto-generate">
                    @error('slug') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Category -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Category</label>
                    <select name="category_id" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- File Type -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">File Type</label>
                    <select name="file_type" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="pdf" {{ old('file_type') == 'pdf' ? 'selected' : '' }}>PDF Document</option>
                        <option value="audio" {{ old('file_type') == 'audio' ? 'selected' : '' }}>Audio Track (MP3)</option>
                        <option value="zip" {{ old('file_type') == 'zip' ? 'selected' : '' }}>ZIP Archive</option>
                        <option value="doc" {{ old('file_type') == 'doc' ? 'selected' : '' }}>Word Document</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Description & Overview</label>
                <textarea name="description" rows="4" 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                          placeholder="Explain what vocabulary, grammar, or dialogue exercises this workbook provides...">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Gate Toggle -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Access Gate</label>
                    <select name="is_gated" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="1" {{ old('is_gated', '1') == '1' ? 'selected' : '' }}>Email Gated (Captures Student Lead)</option>
                        <option value="0" {{ old('is_gated') == '0' ? 'selected' : '' }}>Direct Public Download (Ungated)</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Publication Status</label>
                    <select name="status" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="published" {{ old('status', 'published') == 'published' ? 'selected' : '' }}>Published (Live on Website)</option>
                        <option value="draft" {{ old('status') == 'draft' ? 'selected' : '' }}>Draft (Hidden)</option>
                        <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>
            </div>

            <!-- Cover Image -->
            <div class="pt-4 border-t border-slate-100">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Cover Image / Thumbnail (Optional)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-center">
                    <div>
                        <div class="flex gap-2">
                            <input type="text" id="cover_image_path" name="cover_image_path" value="{{ old('cover_image_path') }}"
                                   placeholder="e.g. media/verbs-guide-cover.webp"
                                   class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <button type="button" onclick="openMediaPicker('cover_image_path', 'cover_image_preview')" class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 shrink-0 transition-colors">
                                Choose from Library
                            </button>
                        </div>
                        <p class="text-[11px] text-slate-400 mt-1">Select from library, enter relative path, or upload a file directly.</p>
                    </div>
                    <div>
                        <input type="file" name="cover_file" accept="image/*" class="w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-3 hidden">
                    <img id="cover_image_preview" src="" alt="Preview" class="h-14 w-14 rounded-xl object-cover border border-slate-200">
                </div>
            </div>

            <!-- External URL -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">External Resource URL (Optional)</label>
                <input type="url" name="external_url" value="{{ old('external_url') }}"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none"
                       placeholder="https://example.com/guide.pdf">
                <p class="text-xs text-slate-400 mt-1">If provided, visitor downloads will redirect directly to this verified external URL (must use https:// or http://).</p>
                @error('external_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- File Upload -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Resource File (PDF / ZIP / MP3)</label>
                <input type="file" name="file" class="w-full text-sm text-slate-600 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100">
                <p class="text-xs text-slate-400 mt-1">Upload the real protected document before publishing. Missing files are rejected rather than replaced with synthetic content.</p>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="{{ route('admin.resources.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" name="status" value="draft" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-xs font-bold transition-colors shadow-xs">
                    Save as Draft
                </button>
                <button type="submit" name="status" value="published" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                    Save & Publish Resource
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

@extends('layouts.admin')

@section('content')
<div class="space-y-8" x-data="{ uploadModalOpen: false }">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Media & Asset Library</h1>
            <p class="text-sm text-slate-500 mt-1">Upload and manage visual assets, infographics, illustrations, and lesson artwork.</p>
        </div>
        <button @click="uploadModalOpen = true" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
            <span>Upload New Asset</span>
        </button>
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

    <!-- Metrics Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Total Assets</div>
            <div class="text-lg font-bold text-slate-900">{{ $media->total() }} Files</div>
            <div class="text-xs text-slate-500 mt-1">Uploaded in library</div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Storage Consumed</div>
            <div class="text-lg font-bold text-slate-900">{{ $totalBytesFormatted }}</div>
            <div class="text-xs text-slate-500 mt-1">Public disk partition</div>
        </div>

        <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-xs">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-1">Supported Formats</div>
            <div class="text-lg font-bold text-slate-900">WebP, PNG, JPG, SVG</div>
            <div class="text-xs text-slate-500 mt-1">Web optimized assets</div>
        </div>
    </div>

    <!-- Media Grid -->
    <div class="bg-white rounded-3xl border border-slate-200 shadow-xs p-6 sm:p-8">
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 gap-6">
            @forelse($media as $item)
                <div class="group relative rounded-2xl border border-slate-200 overflow-hidden bg-slate-50 flex flex-col justify-between hover:shadow-md transition-all">
                    <!-- Thumbnail or Icon -->
                    <div class="aspect-square w-full bg-slate-100 flex items-center justify-center overflow-hidden relative">
                        @if(str_starts_with($item->mime_type, 'image/'))
                            <img src="{{ Storage::disk($item->disk)->url($item->path) }}" alt="{{ $item->alt_text ?? $item->filename }}" class="object-cover w-full h-full group-hover:scale-105 transition-transform duration-300">
                        @else
                            <div class="w-12 h-12 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center font-bold text-xs uppercase">
                                {{ pathinfo($item->filename, PATHINFO_EXTENSION) }}
                            </div>
                        @endif
                    </div>

                    <!-- File Details & Actions -->
                    <div class="p-3 bg-white space-y-1">
                        <div class="font-bold text-xs text-slate-900 truncate" title="{{ $item->filename }}">{{ $item->filename }}</div>
                        <div class="text-[10px] text-slate-400 flex items-center justify-between font-mono">
                            <span>{{ number_format($item->file_size / 1024, 1) }} KB</span>
                            @if(!empty($item->dimensions))
                                <span>{{ $item->dimensions['width'] }}x{{ $item->dimensions['height'] }}</span>
                            @endif
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                            <button type="button"
                                    onclick="navigator.clipboard.writeText('{{ Storage::disk($item->disk)->url($item->path) }}'); alert('Asset URL copied to clipboard!');"
                                    class="text-[10px] font-semibold text-amber-600 hover:text-amber-700 transition-colors">
                                Copy URL
                            </button>

                            <form action="{{ route('admin.media.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Permanently delete asset {{ $item->filename }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[10px] font-semibold text-red-500 hover:text-red-700 transition-colors">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-12 text-center text-slate-400 text-xs">
                    No media assets uploaded yet. Click "Upload New Asset" to add images or documents.
                </div>
            @endforelse
        </div>

        @if($media->hasPages())
            <div class="pt-6 border-t border-slate-100 mt-6">
                {{ $media->links() }}
            </div>
        @endif
    </div>

    <!-- Modal: Upload New Asset -->
    <div x-show="uploadModalOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
         style="display: none;">
        
        <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-lg w-full shadow-2xl border border-slate-200" @click.away="uploadModalOpen = false">
            <h3 class="text-lg font-bold font-serif text-slate-900 mb-1">Upload Media Asset</h3>
            <p class="text-xs text-slate-500 mb-6">Select an image, illustration, or document to upload to the public storage library.</p>

            <form action="{{ route('admin.media.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">File Asset</label>
                    <input type="file" name="file" required accept="image/*,.pdf"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 cursor-pointer">
                </div>

                <div>
                    <label for="alt_text" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1.5">Alt Text / Description</label>
                    <input type="text" id="alt_text" name="alt_text" placeholder="e.g. Egyptian street map illustration"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="uploadModalOpen = false" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold font-serif shadow-xs">
                        Upload Asset
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

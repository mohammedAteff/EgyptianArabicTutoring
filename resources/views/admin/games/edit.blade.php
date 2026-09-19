@extends('layouts.admin')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
            <a href="{{ route('admin.games.index') }}" class="hover:text-amber-600 transition-colors">&larr; Back to Games</a>
        </div>
        <a href="{{ \Illuminate\Support\Facades\URL::temporarySignedRoute('games.preview', now()->addHours(24), ['slug' => $game->slug]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 rounded-xl text-xs font-semibold transition-colors">
            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            <span>Preview Game</span>
        </a>
    </div>
    <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Edit Game — {{ $game->title }}</h1>

@php
    $valTitle = old('title', $draftRevision?->title ?? $game->title);
    $valDescription = old('description', $draftRevision?->content['description'] ?? $game->description);
    $valBadge = old('badge', $draftRevision?->content['badge'] ?? $game->badge);
    $valTargetUrl = old('target_url', $draftRevision?->content['target_url'] ?? $game->target_url);
    $valThumbnailPath = old('thumbnail_path', $draftRevision?->content['thumbnail_path'] ?? $game->thumbnail_path);
    $valStatus = old('status', $game->status);
    $valSortOrder = old('sort_order', $draftRevision?->content['sort_order'] ?? $game->sort_order);
@endphp

    @if ($draftRevision)
        <div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                <div>
                    <p class="text-sm font-semibold text-amber-900">Unpublished Draft Revision Pending</p>
                    <p class="text-xs text-amber-700">This form is showing pending draft changes (Revision #{{ $draftRevision->revision_number }}). The live site still displays published content.</p>
                </div>
            </div>
            <form action="{{ route('admin.games.draft.destroy', $game->id) }}" method="POST" onsubmit="return confirm('Discard this draft and revert to live published values?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="px-3 py-1.5 bg-white border border-amber-300 hover:bg-amber-100 text-amber-900 text-xs font-semibold rounded-lg shadow-xs transition-colors">
                    Discard Draft
                </button>
            </form>
        </div>
    @endif

    <!-- Form Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.games.update', $game->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Title -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Title</label>
                <input type="text" name="title" value="{{ $valTitle }}" required 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Description</label>
                <textarea name="description" rows="3" 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ $valDescription }}</textarea>
                @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Badge Label -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Badge / Category Label</label>
                <input type="text" name="badge" value="{{ $valBadge }}" placeholder="e.g. Speaking Practice"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('badge') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Target External URL -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">External Target URL (Optional)</label>
                <input type="url" name="target_url" value="{{ $valTargetUrl }}" placeholder="https://mohamedateff.com/6word"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <p class="text-xs text-slate-400 mt-1">If provided, clicking the game card launches this external destination in a new tab.</p>
                @error('target_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Thumbnail Image Path -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Thumbnail / Preview Image Path</label>
                <input type="text" name="thumbnail_path" value="{{ $valThumbnailPath }}" placeholder="images/games/6-word-story.webp"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('thumbnail_path') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Status -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Availability Status</label>
                    <select name="status" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="available" {{ $valStatus === 'available' ? 'selected' : '' }}>Available to Play</option>
                        <option value="coming_soon" {{ $valStatus === 'coming_soon' ? 'selected' : '' }}>Coming Soon</option>
                        <option value="disabled" {{ $valStatus === 'disabled' ? 'selected' : '' }}>Disabled (Hidden)</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ $valSortOrder }}" min="0" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="{{ route('admin.games.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" name="action" value="draft" class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-bold transition-colors shadow-xs">
                    Save as Draft
                </button>
                <button type="submit" name="action" value="publish" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                    Publish Game
                </button>
            </div>
        </form>
    </div>

    <!-- Translations & Localization Panel -->
    <x-admin.translations-panel :entity="$game" entity-type="game" />

</div>
@endsection

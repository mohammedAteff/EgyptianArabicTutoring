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

    <!-- Form Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.games.update', $game->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Title -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Title</label>
                <input type="text" name="title" value="{{ old('title', $game->title) }}" required 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Description</label>
                <textarea name="description" rows="3" 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('description', $game->description) }}</textarea>
                @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Badge Label -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Badge / Category Label</label>
                <input type="text" name="badge" value="{{ old('badge', $game->badge) }}" placeholder="e.g. Speaking Practice"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('badge') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Target External URL -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">External Target URL (Optional)</label>
                <input type="url" name="target_url" value="{{ old('target_url', $game->target_url) }}" placeholder="https://mohamedateff.com/6word"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <p class="text-xs text-slate-400 mt-1">If provided, clicking the game card launches this external destination in a new tab.</p>
                @error('target_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Thumbnail Image Path -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Thumbnail / Preview Image Path</label>
                <input type="text" name="thumbnail_path" value="{{ old('thumbnail_path', $game->thumbnail_path) }}" placeholder="images/games/6-word-story.webp"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('thumbnail_path') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Status -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Availability Status</label>
                    <select name="status" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="available" {{ old('status', $game->status) === 'available' ? 'selected' : '' }}>Available to Play</option>
                        <option value="coming_soon" {{ old('status', $game->status) === 'coming_soon' ? 'selected' : '' }}>Coming Soon</option>
                        <option value="disabled" {{ old('status', $game->status) === 'disabled' ? 'selected' : '' }}>Disabled (Hidden)</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $game->sort_order) }}" min="0" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="{{ route('admin.games.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                    Save Changes
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

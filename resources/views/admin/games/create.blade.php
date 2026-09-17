@extends('layouts.admin')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">

    <!-- Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">
            <a href="{{ route('admin.games.index') }}" class="hover:text-amber-600 transition-colors">&larr; Back to Games</a>
        </div>
    </div>
    <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Create Educational Game</h1>

    <!-- Form Card -->
    <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs">
        <form action="{{ route('admin.games.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Title -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Title *</label>
                <input type="text" name="title" value="{{ old('title') }}" required placeholder="e.g. Cairo Street Numbers"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Slug -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Slug (optional, auto-generated if empty)</label>
                <input type="text" name="slug" value="{{ old('slug') }}" placeholder="cairo-street-numbers"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                @error('slug') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Description</label>
                <textarea name="description" rows="3" placeholder="Brief summary of what this game teaches..."
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('description') }}</textarea>
                @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Badge Label -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Badge / Category Label</label>
                <input type="text" name="badge" value="{{ old('badge') }}" placeholder="e.g. Vocabulary Sprint"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('badge') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Target External URL -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">External Target URL (Optional)</label>
                <input type="url" name="target_url" value="{{ old('target_url') }}" placeholder="https://external-game-host.com/game-app"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <p class="text-xs text-slate-400 mt-1">If provided, clicking the game launches this external destination in a new tab.</p>
                @error('target_url') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Thumbnail Image Path -->
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Thumbnail / Preview Image Path</label>
                <input type="text" name="thumbnail_path" value="{{ old('thumbnail_path') }}" placeholder="media/game-thumb.webp"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                @error('thumbnail_path') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Status -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Availability Status</label>
                    <select name="status" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="available" {{ old('status') === 'available' ? 'selected' : '' }}>Available to Play</option>
                        <option value="coming_soon" {{ old('status', 'coming_soon') === 'coming_soon' ? 'selected' : '' }}>Coming Soon</option>
                        <option value="disabled" {{ old('status') === 'disabled' ? 'selected' : '' }}>Disabled (Hidden)</option>
                        <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                    </select>
                </div>

                <!-- Sort Order -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0" required
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Featured Checkbox -->
            <div class="pt-2">
                <label class="relative flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" name="featured" value="1" {{ old('featured') ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                    <span class="text-sm font-semibold text-slate-700">Feature on Homepage</span>
                </label>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-6 border-t border-slate-100">
                <a href="{{ route('admin.games.index') }}" class="px-4 py-2 text-xs font-semibold text-slate-600 hover:text-slate-900">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition-colors shadow-xs font-serif">
                    Create Game
                </button>
            </div>
        </form>
    </div>

</div>
@endsection

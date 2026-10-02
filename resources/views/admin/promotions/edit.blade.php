@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                <a href="{{ route('admin.promotions.index') }}" class="hover:underline">&larr; Back to Promotions</a>
            </div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Edit Promotion: {{ $promotion->title }}</h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.promotions.preview', $promotion) }}" target="_blank"
               class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold border border-slate-300 transition-colors">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Live Preview</span>
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="p-4 bg-red-50 text-red-900 border border-red-200 rounded-2xl text-xs space-y-1">
            <div class="font-bold">Please correct the following errors:</div>
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('admin.promotions.update', $promotion) }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Campaign Internal Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title', $promotion->title) }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Public Headline <span class="text-red-500">*</span></label>
                <input type="text" name="headline" value="{{ old('headline', $promotion->headline) }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Public Subheadline / Description (Optional)</label>
                <textarea name="subheadline" rows="2"
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('subheadline', $promotion->subheadline) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Call to Action (CTA) Text <span class="text-red-500">*</span></label>
                <input type="text" name="cta_text" value="{{ old('cta_text', $promotion->cta_text) }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Call to Action (CTA) URL <span class="text-red-500">*</span></label>
                <input type="text" name="cta_url" value="{{ old('cta_url', $promotion->cta_url) }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Display Format <span class="text-red-500">*</span></label>
                <select name="display_type" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <option value="top_bar" {{ old('display_type', $promotion->display_type) === 'top_bar' ? 'selected' : '' }}>Top Notification Bar (Sticky Header)</option>
                    <option value="floating_modal" {{ old('display_type', $promotion->display_type) === 'floating_modal' ? 'selected' : '' }}>Floating Modal Dialog (Attention Grabber)</option>
                    <option value="inline_card" {{ old('display_type', $promotion->display_type) === 'inline_card' ? 'selected' : '' }}>Inline Announcement Card</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Replace Banner Image (Optional)</label>
                @if($promotion->banner_image_path)
                    <div class="mb-2 flex items-center gap-3 p-2 bg-slate-50 border border-slate-200 rounded-xl">
                        <img src="{{ asset('storage/' . $promotion->banner_image_path) }}" alt="" class="w-10 h-10 object-cover rounded-lg border">
                        <span class="text-xs text-slate-500 truncate font-mono">{{ basename($promotion->banner_image_path) }}</span>
                    </div>
                @endif
                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp"
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none">
                <input type="hidden" name="banner_image_path" value="{{ $promotion->banner_image_path }}">
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100">
            <h3 class="text-sm font-bold text-slate-800 mb-3">Scheduling & Timers (Business Time: {{ app(\App\Domains\Timezone\Services\TimezoneService::class)->getBusinessTimezone() }})</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Start Time (Business Time)</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $startsAtCairo) }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">End Time (Business Time)</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $endsAtCairo) }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center gap-8">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', $promotion->is_active) ? 'checked' : '' }}
                       class="rounded text-amber-600 focus:ring-amber-500 h-4 w-4">
                <span class="text-xs font-bold text-slate-800">Publish / Active</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="has_countdown" value="1" {{ old('has_countdown', $promotion->has_countdown) ? 'checked' : '' }}
                       class="rounded text-amber-600 focus:ring-amber-500 h-4 w-4">
                <span class="text-xs font-bold text-slate-800">Show Real-Time Countdown Timer (Requires End Time)</span>
            </label>
        </div>

        <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="{{ route('admin.promotions.index') }}" class="px-5 py-2.5 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                Save Changes
            </button>
        </div>
    </form>
</div>
@endsection

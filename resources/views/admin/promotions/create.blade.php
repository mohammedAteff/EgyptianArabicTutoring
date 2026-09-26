@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">
                <a href="{{ route('admin.promotions.index') }}" class="hover:underline">&larr; Back to Promotions</a>
            </div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">Create Promotion</h1>
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

    <form action="{{ route('admin.promotions.store') }}" method="POST" enctype="multipart/form-data" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
        @csrf

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Campaign Internal Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" placeholder="e.g. Ramadan Fluency Flash Sale" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Public Headline <span class="text-red-500">*</span></label>
                <input type="text" name="headline" value="{{ old('headline') }}" placeholder="e.g. Limited Time Offer: Save $25 on All 8-Session Packages" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div class="sm:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Public Subheadline / Description (Optional)</label>
                <textarea name="subheadline" rows="2" placeholder="e.g. Accelerate your Egyptian Arabic fluency with direct 1-on-1 coaching with Abdallah."
                          class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('subheadline') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Call to Action (CTA) Text <span class="text-red-500">*</span></label>
                <input type="text" name="cta_text" value="{{ old('cta_text', 'Claim Offer') }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Call to Action (CTA) URL <span class="text-red-500">*</span></label>
                <input type="text" name="cta_url" value="{{ old('cta_url', '/#pricing') }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Display Format <span class="text-red-500">*</span></label>
                <select name="display_type" required
                        class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <option value="top_bar" {{ old('display_type') === 'top_bar' ? 'selected' : '' }}>Top Notification Bar (Sticky Header)</option>
                    <option value="floating_modal" {{ old('display_type') === 'floating_modal' ? 'selected' : '' }}>Floating Modal Dialog (Attention Grabber)</option>
                    <option value="inline_card" {{ old('display_type') === 'inline_card' ? 'selected' : '' }}>Inline Announcement Card</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Banner Image (Optional)</label>
                <input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp"
                       class="w-full px-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs focus:outline-none">
                <p class="text-[11px] text-slate-400 mt-1">JPEG, PNG, or WebP up to 10MB.</p>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100">
            <h3 class="text-sm font-bold text-slate-800 mb-3">Scheduling & Timers (Cairo Local Time: Africa/Cairo)</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Start Time (Cairo Time)</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Leave empty to activate immediately when toggled on.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">End Time (Cairo Time)</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1">Leave empty to run indefinitely until manually deactivated.</p>
                </div>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 flex flex-wrap items-center gap-8">
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}
                       class="rounded text-amber-600 focus:ring-amber-500 h-4 w-4">
                <span class="text-xs font-bold text-slate-800">Publish / Active</span>
            </label>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="has_countdown" value="1" {{ old('has_countdown') ? 'checked' : '' }}
                       class="rounded text-amber-600 focus:ring-amber-500 h-4 w-4">
                <span class="text-xs font-bold text-slate-800">Show Real-Time Countdown Timer (Requires End Time)</span>
            </label>
        </div>

        <div class="pt-6 border-t border-slate-100 flex items-center justify-end gap-3">
            <a href="{{ route('admin.promotions.index') }}" class="px-5 py-2.5 bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 rounded-xl text-xs font-semibold transition-colors">
                Cancel
            </a>
            <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-xs transition-colors">
                Create & Save Promotion
            </button>
        </div>
    </form>
</div>
@endsection

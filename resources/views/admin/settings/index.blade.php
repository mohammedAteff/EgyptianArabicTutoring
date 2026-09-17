@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">System & Business Settings</h1>
            <p class="text-sm text-slate-500 mt-1">Configure business timezone, site policies, booking rules, and homepage/about copy.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('home.preview') }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 rounded-xl text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Preview Homepage</span>
            </a>
            <a href="{{ route('about.preview') }}" target="_blank" class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-amber-50 hover:bg-amber-100 text-amber-900 border border-amber-200 rounded-xl text-xs font-semibold transition-colors">
                <svg class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span>Preview About</span>
            </a>
        </div>
    </div>

    <!-- Form -->
    <form action="{{ route('admin.settings.update') }}" method="POST" class="space-y-8">
        @csrf

        <!-- 1. General & Business Timezone -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">1. Business Timezone & General</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Site Name -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Website Name</label>
                    <input type="text" name="site_name" value="{{ old('site_name', $settings['site_name']->value ?? 'Egyptian Arabic Tutoring') }}" required 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <!-- Business Timezone -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Business Timezone (Tutor Local)</label>
                    <select name="business_timezone" required class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono text-xs">
                        @php $currentTz = old('business_timezone', $settings['business_timezone']->value ?? 'Africa/Cairo'); @endphp
                        @foreach($timezones as $tz)
                            <option value="{{ $tz['id'] }}" {{ $currentTz === $tz['id'] ? 'selected' : '' }}>
                                {{ $tz['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-400 mt-1">Canonical UTC instants are computed against this timezone.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Default Language -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Default Platform Language</label>
                    <select name="default_language" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="en" {{ old('default_language', $settings['default_language']->value ?? 'en') === 'en' ? 'selected' : '' }}>English (Default)</option>
                        <option value="ar" {{ old('default_language', $settings['default_language']->value ?? 'en') === 'ar' ? 'selected' : '' }}>Arabic (العربية)</option>
                    </select>
                </div>

                <!-- Maintenance Mode -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Maintenance Mode</label>
                    <select name="maintenance_mode" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                        <option value="0" {{ old('maintenance_mode', $settings['maintenance_mode']->value ?? '0') == '0' ? 'selected' : '' }}>Live (Normal Public Operations)</option>
                        <option value="1" {{ old('maintenance_mode', $settings['maintenance_mode']->value ?? '0') == '1' ? 'selected' : '' }}>Maintenance Mode (Public Offline)</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Site Footer Notice</label>
                <input type="text" name="site_footer_text" value="{{ old('site_footer_text', $settings['site_footer_text']->value ?? 'Authentic 1-on-1 Egyptian Arabic Tutoring.') }}" 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
            </div>
        </div>

        <!-- 2. Homepage Content & Copy -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">2. Homepage Copy & Sections</h2>

            <!-- Hero -->
            <div class="space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Hero Section</h3>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Hero Title</label>
                    <input type="text" name="hero_title" value="{{ old('hero_title', $settings['hero_title']->value ?? 'Speak Egyptian Arabic with Confidence') }}" required 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-serif">
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Hero Subtitle</label>
                    <textarea name="hero_subtitle" rows="2" required 
                              class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('hero_subtitle', $settings['hero_subtitle']->value ?? 'Master authentic Egyptian street and conversational Arabic through structured, 1-on-1 private lessons with an experienced native speaker.') }}</textarea>
                </div>
            </div>

            <!-- Approach -->
            <div class="pt-4 border-t border-slate-100 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">The Practical Method (Approach Section)</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Approach Badge</label>
                        <input type="text" name="home_approach_badge" value="{{ old('home_approach_badge', $settings['home_approach_badge']->value ?? 'The Practical Method') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Approach Title</label>
                        <input type="text" name="home_approach_title" value="{{ old('home_approach_title', $settings['home_approach_title']->value ?? 'Why Learn Egyptian Arabic 1-on-1?') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Approach Intro</label>
                    <textarea name="home_approach_intro" rows="2" 
                              class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('home_approach_intro', $settings['home_approach_intro']->value ?? "Most Arabic courses teach Modern Standard Arabic (MSA), which native speakers don't speak at home or on the streets. We teach you authentic spoken Egyptian Arabic as it is used today.") }}</textarea>
                </div>
            </div>

            <!-- Resources & Games Teasers -->
            <div class="pt-4 border-t border-slate-100 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Resources & Games Teasers</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Resources Badge</label>
                        <input type="text" name="home_resources_badge" value="{{ old('home_resources_badge', $settings['home_resources_badge']->value ?? 'Free Workbooks & Cheatsheets') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Resources Title</label>
                        <input type="text" name="home_resources_title" value="{{ old('home_resources_title', $settings['home_resources_title']->value ?? 'Grow Your Vocabulary') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Resources Subtitle</label>
                    <input type="text" name="home_resources_subtitle" value="{{ old('home_resources_subtitle', $settings['home_resources_subtitle']->value ?? 'Download free structured guides prepared specifically for Egyptian Arabic learners.') }}" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Games Badge</label>
                        <input type="text" name="home_games_badge" value="{{ old('home_games_badge', $settings['home_games_badge']->value ?? 'Interactive Practice') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Games Title</label>
                        <input type="text" name="home_games_title" value="{{ old('home_games_title', $settings['home_games_title']->value ?? 'Play Egyptian Arabic Games') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Games Subtitle</label>
                    <input type="text" name="home_games_subtitle" value="{{ old('home_games_subtitle', $settings['home_games_subtitle']->value ?? 'Reinforce your memory with engaging numbers, food, and street phrase exercises.') }}" 
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- CTA -->
            <div class="pt-4 border-t border-slate-100 space-y-4">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500">Call to Action (CTA) Section</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">CTA Title</label>
                        <input type="text" name="home_cta_title" value="{{ old('home_cta_title', $settings['home_cta_title']->value ?? 'Ready to Speak Authentic Egyptian Arabic?') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">CTA Button Text</label>
                        <input type="text" name="home_cta_button" value="{{ old('home_cta_button', $settings['home_cta_button']->value ?? 'Book Your Session Now') }}" 
                               class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">CTA Subtitle</label>
                    <textarea name="home_cta_subtitle" rows="2" 
                              class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('home_cta_subtitle', $settings['home_cta_subtitle']->value ?? 'Reserve your first private lesson in minutes. Choose your local timezone and get instant confirmation with your calendar invitation.') }}</textarea>
                </div>
            </div>
        </div>

        <!-- 3. About Page Content -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">3. About Ahmad Page Content</h2>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Tutor Biography</label>
                <textarea name="about_biography" rows="4" 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none leading-relaxed">{{ old('about_biography', $settings['about_biography']->value ?? 'Native Egyptian Arabic tutor with extensive experience teaching international students conversational fluency, grammar, and cultural nuances.') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Teaching Philosophy</label>
                <textarea name="about_philosophy" rows="3" 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none leading-relaxed">{{ old('about_philosophy', $settings['about_philosophy']->value ?? 'Language is lived, not memorized. We focus on natural speech patterns and practical everyday scenarios.') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Tutor Portrait Image Path</label>
                <div class="flex gap-3 items-center">
                    <input type="text" id="about_image_path" name="about_image_path" value="{{ old('about_image_path', $settings['about_image_path']->value ?? '') }}" 
                           placeholder="media/ahmad-portrait.webp"
                           class="flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    <button type="button" onclick="openMediaPicker('about_image_path', 'about_image_preview')" class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl border border-slate-300 shrink-0 transition-colors">
                        Choose from Media Library
                    </button>
                    <div class="h-10 w-10 rounded-lg overflow-hidden border border-slate-200 shrink-0 {{ !empty($settings['about_image_path']->value) ? '' : 'hidden' }}">
                        <img id="about_image_preview" src="{{ !empty($settings['about_image_path']->value) ? (str_starts_with($settings['about_image_path']->value, 'http') ? $settings['about_image_path']->value : \Illuminate\Support\Facades\Storage::disk('public')->url($settings['about_image_path']->value)) : '' }}" alt="Preview" class="h-full w-full object-cover">
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. Booking Policies -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">4. Booking Policies & Instructions</h2>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Booking Instructions</label>
                <textarea name="booking_instructions" rows="3" required 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('booking_instructions', $settings['booking_instructions']->value ?? 'Choose your timezone and select a convenient date and time. An instant confirmation and calendar file will be generated for you.') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Cancellation Policy</label>
                <textarea name="cancellation_policy" rows="3" required 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('cancellation_policy', $settings['cancellation_policy']->value ?? 'Cancellations and rescheduling are accepted up to 24 hours in advance.') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Cancellation & Rescheduling Cutoff (Hours)</label>
                <input type="number" name="booking_cancellation_cutoff_hours" min="0" max="168" value="{{ old('booking_cancellation_cutoff_hours', $settings['booking_cancellation_cutoff_hours']->value ?? '24') }}" required 
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                <p class="text-[11px] text-slate-400 mt-1">Minimum hours before appointment start required for student self-service cancellation or rescheduling (e.g. 24).</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Rescheduling Policy</label>
                <textarea name="rescheduling_policy" rows="3" required 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('rescheduling_policy', $settings['rescheduling_policy']->value ?? 'Rescheduling is free and subject to available tutor calendar slots.') }}</textarea>
            </div>
        </div>

        <!-- Save Buttons -->
        <div class="flex items-center justify-end gap-3">
            <button type="submit" name="action" value="draft" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 text-slate-800 rounded-xl text-sm font-bold transition-colors shadow-xs">
                Save as Draft
            </button>
            <button type="submit" name="action" value="publish" class="px-6 py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-sm font-bold transition-colors shadow-sm font-serif">
                Publish All Settings
            </button>
        </div>
    </form>

</div>
@endsection

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
                    <input type="text" name="site_name" value="{{ old('site_name', $settings['site_name']->value ?? config('business.site_name')) }}" required
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
                        <option value="fr" {{ old('default_language', $settings['default_language']->value ?? 'en') === 'fr' ? 'selected' : '' }}>French (Français)</option>
                        <option value="de" {{ old('default_language', $settings['default_language']->value ?? 'en') === 'de' ? 'selected' : '' }}>German (Deutsch)</option>
                    </select>
                </div>

                @if($canManageMaintenance)
                    <!-- Maintenance Mode -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Maintenance Mode</label>
                        <select name="maintenance_mode" class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <option value="0" {{ old('maintenance_mode', $settings['maintenance_mode']->value ?? '0') == '0' ? 'selected' : '' }}>Live (Normal Public Operations)</option>
                            <option value="1" {{ old('maintenance_mode', $settings['maintenance_mode']->value ?? '0') == '1' ? 'selected' : '' }}>Maintenance Mode (Public Offline)</option>
                        </select>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:col-span-2">
                        Public maintenance mode is managed by a super administrator. You can still update the business and site settings on this page.
                    </div>
                @endif
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
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">3. About Abdallah Page Content</h2>

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
                           placeholder="media/abdallah-portrait.webp"
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
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Lesson Video Meeting Link</label>
                <input type="url" name="video_meeting_url" value="{{ old('video_meeting_url', $settings['video_meeting_url']->value ?? '') }}" maxlength="2048" placeholder="https://…" autocomplete="url"
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                <p class="text-[11px] text-slate-400 mt-1">Use the private lesson-room URL. It appears in confirmed student sessions, booking confirmations, and calendar files. Leave blank until a real join link is available.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Cancellation Policy</label>
                <textarea name="cancellation_policy" rows="3" required 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('cancellation_policy', $settings['cancellation_policy']->value ?? 'Cancellations with at least 4 hours notice do not forfeit the session credit. Rescheduling requests must be made directly to Abdallah at least 24 hours before class.') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Cancellation Cutoff (Hours)</label>
                <input type="number" name="booking_cancellation_cutoff_hours" min="0" max="168" value="{{ old('booking_cancellation_cutoff_hours', $settings['booking_cancellation_cutoff_hours']->value ?? '4') }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                <p class="text-[11px] text-slate-400 mt-1">Minimum hours before appointment start required for customer cancellation (the canonical policy is 4 hours).</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Rescheduling Request Notice (Hours)</label>
                <input type="number" name="booking_reschedule_cutoff_hours" min="0" max="168" value="{{ old('booking_reschedule_cutoff_hours', $settings['booking_reschedule_cutoff_hours']->value ?? '24') }}" required
                       class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                <p class="text-[11px] text-slate-400 mt-1">Minimum notice for direct-contact rescheduling requests; administrators can execute approved changes privately.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Rescheduling Policy</label>
                <textarea name="rescheduling_policy" rows="3" required 
                          class="w-full p-3.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">{{ old('rescheduling_policy', $settings['rescheduling_policy']->value ?? 'Rescheduling is free and subject to available tutor calendar slots.') }}</textarea>
            </div>
        </div>

        <!-- 5. Social Proof Counters -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">5. Social Proof Counters</h2>
            <p class="text-xs text-slate-500">Configure public engagement counters displayed on the website banner. All calculations use Cairo calendar boundaries and flat caching.</p>

            <!-- Counter 1: Collective Learning Hours -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Counter 1: Collective Learning Activity Hours</h3>
                        <p class="text-xs text-slate-500">Aggregates completed lesson hours plus student study dwell time over completed Cairo days.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="counters_learning_hours_public_enabled" value="1" class="sr-only peer"
                               {{ old('counters_learning_hours_public_enabled', \App\Domains\CMS\Models\Setting::get('counters.learning_hours.public_enabled', false)) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-2 text-xs font-semibold text-slate-700">Public Display</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Window Days (Complete)</label>
                        <input type="number" name="counters_learning_hours_window_days" min="1" max="90"
                               value="{{ old('counters_learning_hours_window_days', \App\Domains\CMS\Models\Setting::get('counters.learning_hours.window_days', 7)) }}"
                               class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Headline Text</label>
                        <input type="text" name="counters_learning_hours_headline"
                               value="{{ old('counters_learning_hours_headline', \App\Domains\CMS\Models\Setting::get('counters.learning_hours.headline', 'Globally, Line of Action students have put in...')) }}"
                               class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Subtitle Text</label>
                    <input type="text" name="counters_learning_hours_subtitle"
                           value="{{ old('counters_learning_hours_subtitle', \App\Domains\CMS\Models\Setting::get('counters.learning_hours.subtitle', 'of practice time in the last 7 days')) }}"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>

            <!-- Counter 2: Monthly Traffic -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Counter 2: Previous Month Traffic</h3>
                        <p class="text-xs text-slate-500">Displays total visitors or sessions from the previous calendar month.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="counters_monthly_traffic_public_enabled" value="1" class="sr-only peer"
                               {{ old('counters_monthly_traffic_public_enabled', \App\Domains\CMS\Models\Setting::get('counters.monthly_traffic.public_enabled', false)) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-2 text-xs font-semibold text-slate-700">Public Display</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Traffic Source Metric</label>
                        @php $trafficSrc = old('counters_monthly_traffic_source', \App\Domains\CMS\Models\Setting::get('counters.monthly_traffic.source', 'unique_visitors')); @endphp
                        <select name="counters_monthly_traffic_source" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <option value="unique_visitors" {{ $trafficSrc === 'unique_visitors' ? 'selected' : '' }}>Unique Visitors</option>
                            <option value="sessions" {{ $trafficSrc === 'sessions' ? 'selected' : '' }}>Total Sessions</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Visitors Template ({count})</label>
                        <input type="text" name="counters_monthly_traffic_template_visitors"
                               value="{{ old('counters_monthly_traffic_template_visitors', \App\Domains\CMS\Models\Setting::get('counters.monthly_traffic.template_visitors', 'We welcomed {count} visitors last month')) }}"
                               class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Sessions Template ({count})</label>
                        <input type="text" name="counters_monthly_traffic_template_sessions"
                               value="{{ old('counters_monthly_traffic_template_sessions', \App\Domains\CMS\Models\Setting::get('counters.monthly_traffic.template_sessions', 'We had {count} website sessions last month')) }}"
                               class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>
            </div>

            <!-- Counter 3: Live Users -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Counter 3: Live Users Online</h3>
                        <p class="text-xs text-slate-500">Live count of distinct visitors active within the last 60 seconds.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="counters_live_users_public_enabled" value="1" class="sr-only peer"
                               {{ old('counters_live_users_public_enabled', \App\Domains\CMS\Models\Setting::get('counters.live_users.public_enabled', false)) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-2 text-xs font-semibold text-slate-700">Public Display</span>
                    </label>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Live Users Template ({count})</label>
                    <input type="text" name="counters_live_users_template"
                           value="{{ old('counters_live_users_template', \App\Domains\CMS\Models\Setting::get('counters.live_users.template', '{count} active visitors online right now')) }}"
                           class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                </div>
            </div>
        </div>

        <!-- 6. Telegram Reminders & Multi-Recipient Notifications -->
        @php
            $savedReminderWindows = \App\Domains\CMS\Models\Setting::get('telegram.reminder_windows', [1440, 60]);
            $reminderWindows = old('telegram_reminder_windows', is_array($savedReminderWindows) ? $savedReminderWindows : []);
            $savedChatIds = \App\Domains\CMS\Models\Setting::get('telegram.notification_chat_ids', []);
            $notificationChatIds = old('telegram_notification_chat_ids', is_array($savedChatIds) ? $savedChatIds : []);
            $reminderWindowRows = array_map(fn ($minutes) => ['id' => (string) \Illuminate\Support\Str::uuid(), 'value' => (int) $minutes % 60 === 0 ? (int) $minutes / 60 : (int) $minutes, 'unit' => (int) $minutes % 60 === 0 ? 'hours' : 'minutes'], $reminderWindows);
            $notificationChatRows = array_map(fn ($chatId) => ['id' => (string) \Illuminate\Support\Str::uuid(), 'value' => (string) $chatId], $notificationChatIds);
        @endphp
        <div x-data="{
                windows: @js($reminderWindowRows),
                chatIds: @js($notificationChatRows),
                minutes(row) { return row.value === '' || !Number.isInteger(Number(row.value)) ? '' : Number(row.value) * (row.unit === 'hours' ? 60 : 1) },
                addWindow() { this.windows.push({ id: Math.random().toString(36).slice(2), value: '', unit: 'minutes' }) },
                addChat() { this.chatIds.push({ id: Math.random().toString(36).slice(2), value: '' }) }
            }" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <input type="hidden" name="telegram_settings_present" value="1">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100">
                <div>
                    <h2 class="text-lg font-bold font-serif text-slate-900">6. Telegram Bot & Reminder Windows</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Automated lesson alerts to Abdallah and assistants with configurable dynamic milestone lead times.</p>
                </div>
                <div class="flex items-center gap-4">
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="telegram_reminders_enabled" value="1" class="sr-only peer"
                               {{ old('telegram_reminders_enabled', \App\Domains\CMS\Models\Setting::get('telegram.reminders_enabled', false)) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-2 text-xs font-bold text-slate-800">Reminders Active</span>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Telegram Bot Token (Encrypted at Rest)</label>
                    <input type="password" name="telegram_bot_token"
                           placeholder="••••••••••••••••••••••••••••••••••••••••"
                           class="w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    <p class="text-[11px] text-slate-400 mt-1">Leave empty to keep existing token. Value is encrypted with AES-256-GCM.</p>
                </div>

                <div class="space-y-3">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Reminder Milestones</label>
                    <template x-for="(window, index) in windows" :key="window.id">
                        <div class="flex flex-wrap sm:flex-nowrap items-center gap-2">
                            <span class="text-xs text-slate-500" x-text="'#' + (index + 1)"></span>
                            <input type="number" min="1" step="1" x-model.number="window.value" :aria-label="'Reminder milestone ' + (index + 1)" class="min-w-0 flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <select x-model="window.unit" :aria-label="'Reminder milestone unit ' + (index + 1)" class="px-3 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                                <option value="minutes">Minutes</option>
                                <option value="hours">Hours</option>
                            </select>
                            <input type="hidden" name="telegram_reminder_windows[]" :value="minutes(window)">
                            <button type="button" @click="windows.splice(index, 1)" :aria-label="'Remove reminder milestone ' + (index + 1)" class="px-3 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50 rounded-xl">Remove</button>
                        </div>
                    </template>
                    <button type="button" @click="addWindow()" class="px-3.5 py-2.5 text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 rounded-xl">+ Add reminder milestone</button>
                    @error('telegram_reminder_windows') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                    @error('telegram_reminder_windows.*') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="space-y-3">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Notification Chat IDs</label>
                    <template x-for="(chat, index) in chatIds" :key="chat.id">
                        <div class="flex items-center gap-2">
                            <input type="text" name="telegram_notification_chat_ids[]" x-model="chat.value" :aria-label="'Notification chat ID ' + (index + 1)" placeholder="-100123456789" class="min-w-0 flex-1 px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                            <button type="button" @click="chatIds.splice(index, 1)" :aria-label="'Remove notification chat ID ' + (index + 1)" class="px-3 py-2.5 text-xs font-semibold text-red-700 hover:bg-red-50 rounded-xl">Remove</button>
                        </div>
                    </template>
                    <button type="button" @click="addChat()" class="px-3.5 py-2.5 text-xs font-semibold text-amber-800 bg-amber-50 hover:bg-amber-100 rounded-xl">+ Add recipient chat ID</button>
                    @error('telegram_notification_chat_ids') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                    @error('telegram_notification_chat_ids.*') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100 flex items-center justify-between">
                <span class="text-xs text-slate-400">Verify connectivity and credentials before publishing live reminders.</span>
                <button type="submit" formaction="{{ route('admin.settings.telegram.test') }}" formmethod="POST"
                        class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold border border-slate-200 transition-colors">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Send Test Reminder</span>
                </button>
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
<form method="POST" action="{{ route('admin.settings.operations') }}" class="mt-8 space-y-5 rounded-2xl border border-slate-200 bg-white p-6">
@csrf
<h2 class="text-xl font-bold">Contact, conversions and maintenance message</h2>
<label class="block text-sm font-medium">Maintenance message<textarea name="maintenance_message" maxlength="2000" class="mt-2 w-full rounded-lg border border-slate-300 p-3">{{ old('maintenance_message', \App\Domains\CMS\Models\Setting::get('maintenance_message', 'We will be back shortly.')) }}</textarea></label>
<label class="block text-sm font-medium">WhatsApp destination URL<input type="url" name="whatsapp_url" value="{{ old('whatsapp_url', \App\Domains\CMS\Models\Setting::get('whatsapp_url')) }}" placeholder="https://wa.me/201012345678" class="mt-2 w-full rounded-lg border border-slate-300 p-3"></label>
<div class="flex flex-wrap gap-5">@foreach(['whatsapp_public'=>'Show on public website','whatsapp_portal'=>'Show in student portal'] as $key=>$label)<label><input type="hidden" name="{{ $key }}" value="0"><input type="checkbox" name="{{ $key }}" value="1" @checked(\App\Domains\CMS\Models\Setting::get($key, false))> {{ $label }}</label>@endforeach</div>
<div class="grid gap-4 md:grid-cols-3">@foreach(['en'=>'English','fr'=>'French','de'=>'German'] as $locale=>$language)<fieldset class="space-y-2 rounded-lg border p-3"><legend>{{ $language }}</legend><label class="block text-sm">Button label<input name="whatsapp_label[{{ $locale }}]" maxlength="100" value="{{ \App\Domains\CMS\Models\Setting::get('whatsapp_label', [])[$locale] ?? '' }}" class="w-full rounded border border-slate-300 p-2"></label><label class="block text-sm">Prefilled message<textarea name="whatsapp_message[{{ $locale }}]" maxlength="1000" class="w-full rounded border border-slate-300 p-2">{{ \App\Domains\CMS\Models\Setting::get('whatsapp_message', [])[$locale] ?? '' }}</textarea></label></fieldset>@endforeach</div>
<fieldset><legend class="font-semibold">Conversion goals</legend><div class="mt-2 grid gap-2 sm:grid-cols-2">@foreach(\App\Domains\Analytics\Services\AnalyticsService::CONVERSION_EVENTS as $event)<label class="text-sm"><input type="checkbox" name="goals[]" value="{{ $event }}" @checked(in_array($event, (array) \App\Domains\CMS\Models\Setting::get('analytics.goals', []), true))> {{ ucfirst(str_replace('_', ' ', $event)) }}</label>@endforeach</div></fieldset>
<fieldset class="space-y-2"><legend class="font-semibold">Internal traffic</legend><p class="text-sm text-slate-600">Administrators, previews, bots and synthetic checks are excluded automatically. Connection exclusions store a keyed hash.</p><label class="block"><input type="checkbox" name="exclude_connection" value="1"> Exclude my current connection</label><label class="block"><input type="checkbox" name="clear_exclusions" value="1"> Clear saved connection exclusions</label></fieldset>
<button class="rounded-lg bg-slate-900 px-5 py-3 font-semibold text-white">Save operational settings</button>
</form>
@endsection

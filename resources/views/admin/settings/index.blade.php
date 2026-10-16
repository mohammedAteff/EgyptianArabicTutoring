@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto space-y-8">

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold font-serif text-slate-900 tracking-tight">System & Business Settings</h1>
            <p class="text-sm text-slate-500 mt-1">Manage business rules, operational controls and site announcements. Website wording is managed in Website Copy & Pages.</p>
        </div>
    </div>

    @if($hasSettingsDraft)<p role="status" class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">Showing the saved settings draft. Live settings change only when you publish.</p>@endif
    <!-- Form -->
    <form id="business-settings" action="{{ route('admin.settings.update') }}" method="POST" class="space-y-8">
        <input type="hidden" name="operations_present" value="1">
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
                        <p class="mt-2 text-xs text-slate-500">Publish to apply this setting. Your administrator session keeps access; check the public result in a private browser. Students and anonymous visitors receive the maintenance page.</p>
                    </div>
                @else
                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 sm:col-span-2">
                        Public maintenance mode is managed by a super administrator. You can still update the business and site settings on this page.
                    </div>
                @endif
            </div>


        </div>

        <!-- 4. Booking Policies -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">2. Booking Rules</h2>






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


        </div>

        <!-- 3. Social Proof Counters -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6">
            <h2 class="text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100">3. Social Proof Counters</h2>
            <p class="text-xs text-slate-500">Configure public engagement counters displayed on the website banner. All calculations use the configured business calendar boundaries and flat caching.</p>

            <!-- Counter 1: Collective Learning Hours -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Counter 1: Collective Learning Activity Hours</h3>
                        <p class="text-xs text-slate-500">Aggregates completed lesson hours plus student study dwell time over the business reporting window.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="counters_learning_hours_public_enabled" value="0"><input type="checkbox" name="counters_learning_hours_public_enabled" value="1" class="sr-only peer"
                               {{ old('counters_learning_hours_public_enabled', $getSetting('counters.learning_hours.public_enabled', false)) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-2 text-xs font-semibold text-slate-700">Public Display</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Window days (including today)</label>
                        <input type="number" name="counters_learning_hours_window_days" min="1" max="90"
                               value="{{ old('counters_learning_hours_window_days', $getSetting('counters.learning_hours.window_days', 7)) }}"
                               class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none font-mono">
                    </div>

                </div>

            </div>

            <!-- Counter 2: Monthly Traffic -->
            <div class="p-4 bg-slate-50 rounded-2xl border border-slate-200 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Counter 2: Previous Month Traffic</h3>
                        <p class="text-xs text-slate-500">Displays visitors, sessions or page views from the previous calendar month.</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="counters_monthly_traffic_public_enabled" value="0"><input type="checkbox" name="counters_monthly_traffic_public_enabled" value="1" class="sr-only peer"
                               {{ old('counters_monthly_traffic_public_enabled', $getSetting('counters.monthly_traffic.public_enabled', false)) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-2 text-xs font-semibold text-slate-700">Public Display</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">Traffic Source Metric</label>
                        @php $trafficSrc = old('counters_monthly_traffic_source', $getSetting('counters.monthly_traffic.source', 'unique_visitors')); @endphp
                        <select name="counters_monthly_traffic_source" class="w-full px-3.5 py-2.5 bg-white border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none">
                            <option value="unique_visitors" {{ $trafficSrc === 'unique_visitors' ? 'selected' : '' }}>Unique Visitors</option>
                            <option value="page_views" {{ $trafficSrc === 'page_views' ? 'selected' : '' }}>Page Views</option>
                            <option value="sessions" {{ $trafficSrc === 'sessions' ? 'selected' : '' }}>Total Sessions</option>
                        </select>
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
                        <input type="hidden" name="counters_live_users_public_enabled" value="0"><input type="checkbox" name="counters_live_users_public_enabled" value="1" class="sr-only peer"
                               {{ old('counters_live_users_public_enabled', $getSetting('counters.live_users.public_enabled', false)) ? 'checked' : '' }}>
                        <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-amber-600"></div>
                        <span class="ml-2 text-xs font-semibold text-slate-700">Public Display</span>
                    </label>
                </div>


            </div>
        </div>


        @php
            $inputClass = 'mt-1 w-full px-3.5 py-2.5 bg-slate-50 border border-slate-300 rounded-xl text-sm focus:ring-2 focus:ring-amber-500 focus:outline-none';
            $cardClass = 'bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-xs space-y-6';
            $headingClass = 'text-lg font-bold font-serif text-slate-900 pb-3 border-b border-slate-100';
            $floatingLabels = old('whatsapp_label', (array) $getSetting('whatsapp_label', []));
            $floatingMessages = old('whatsapp_message', (array) $getSetting('whatsapp_message', []));
        @endphp

        <section class="{{ $cardClass }}" aria-labelledby="maintenance-message-heading">
            <h2 id="maintenance-message-heading" class="{{ $headingClass }}">4. Maintenance Message</h2>
            <div>
                <label for="maintenance-message" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Public maintenance message</label>
                <textarea id="maintenance-message" name="maintenance_message" maxlength="2000" rows="3" class="{{ $inputClass }}">{{ old('maintenance_message', $getSetting('maintenance_message', 'We will be back shortly.')) }}</textarea>
                <p class="mt-2 text-xs text-slate-500">Shown while maintenance mode is active. Change the mode in Business Timezone &amp; General above.</p>
            </div>
        </section>

        <section class="{{ $cardClass }}" aria-labelledby="floating-whatsapp-heading">
            <h2 id="floating-whatsapp-heading" class="{{ $headingClass }}">5. Floating WhatsApp</h2>
            <p class="text-sm text-slate-500">Configure the circular contact button. Footer social links keep their own configuration in Social Links.</p>
            <div>
                <label for="floating-whatsapp-url" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">Floating WhatsApp destination URL</label>
                <input id="floating-whatsapp-url" type="url" name="whatsapp_url" value="{{ old('whatsapp_url', $getSetting('whatsapp_url')) }}" placeholder="https://wa.me/201012345678" class="{{ $inputClass }}">
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach(['whatsapp_public' => 'Show on public website', 'whatsapp_portal' => 'Show in student portal'] as $key => $label)
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="{{ $key }}" value="0">
                        <input type="checkbox" name="{{ $key }}" value="1" @checked(old($key, $getSetting($key, false))) class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach(['en' => 'English', 'fr' => 'French', 'de' => 'German'] as $locale => $language)
                    <fieldset class="space-y-4 rounded-xl border border-slate-200 p-4 min-w-0">
                        <legend class="px-1 text-sm font-semibold text-slate-700">{{ $language }}</legend>
                        <label class="block text-xs font-semibold text-slate-600">Accessible label / tooltip
                            <input name="whatsapp_label[{{ $locale }}]" maxlength="100" value="{{ $floatingLabels[$locale] ?? '' }}" class="{{ $inputClass }}">
                        </label>
                        <label class="block text-xs font-semibold text-slate-600">Prefilled message
                            <textarea name="whatsapp_message[{{ $locale }}]" maxlength="1000" rows="3" class="{{ $inputClass }}">{{ $floatingMessages[$locale] ?? '' }}</textarea>
                        </label>
                    </fieldset>
                @endforeach
            </div>
        </section>

        <section class="{{ $cardClass }}" aria-labelledby="conversion-goals-heading">
            <h2 id="conversion-goals-heading" class="{{ $headingClass }}">6. Analytics &amp; Conversion Goals</h2>
            <fieldset>
                <legend class="text-sm text-slate-500">Choose which actions count toward conversion reports.</legend>
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    @foreach(\App\Domains\Analytics\Services\AnalyticsService::CONVERSION_EVENTS as $event)
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="goals[]" value="{{ $event }}" @checked(in_array($event, (array) old('goals', $getSetting('analytics.goals', [])), true)) class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"> {{ ucfirst(str_replace('_', ' ', $event)) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
        </section>

        <section class="{{ $cardClass }}" aria-labelledby="internal-traffic-heading">
            <h2 id="internal-traffic-heading" class="{{ $headingClass }}">7. Analytics Exclusions &amp; Internal Traffic</h2>
            <p class="text-sm text-slate-500">Administrators, previews, bots and synthetic checks are excluded automatically. Saved connection exclusions apply to public analytics reports.</p>
            <div class="space-y-3">
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="exclude_connection" value="1" @checked(old('exclude_connection', $getSetting('exclude_connection', false))) class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"> Exclude my current connection</label>
                <label class="flex items-center gap-2 text-sm text-slate-700"><input type="checkbox" name="clear_exclusions" value="1" @checked(old('clear_exclusions', $getSetting('clear_exclusions', false))) class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"> Clear saved connection exclusions</label>
            </div>
        </section>

        @include('admin.settings.announcement')

        <div class="sticky bottom-0 z-30 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm">
            <p class="mr-auto text-xs text-slate-500">All settings and the site announcement save together. Drafts do not change the live website. Publish to apply all sections.</p>
            <button type="submit" name="action" value="draft" class="rounded-xl bg-slate-100 px-5 py-3 text-sm font-bold text-slate-800">Save as Draft</button>
            <button type="submit" name="action" value="publish" class="rounded-xl bg-amber-600 px-6 py-3 text-sm font-bold text-white hover:bg-amber-700">Publish All Settings</button>
        </div>
    </form>

</div>
@endsection

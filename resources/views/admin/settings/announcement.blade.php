<section class="space-y-5 rounded-3xl border border-slate-200 bg-white p-6 shadow-xs sm:p-8" aria-labelledby="announcement-heading">
    <h2 id="announcement-heading" class="text-lg font-bold text-slate-900">8. Site Announcement</h2>
    <p class="text-sm text-slate-500">Configure the announcement here. Save as Draft and Publish All Settings apply to this section too.</p>
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach(['enabled' => ['Status', ['0' => 'Disabled', '1' => 'Enabled']], 'audience' => ['Audience', ['public' => 'Public only', 'public_student' => 'Public + Student Portal']], 'severity' => ['Presentation', ['information' => 'Information', 'warning' => 'Warning', 'urgent' => 'Urgent']], 'dismissible' => ['Dismissal', ['0' => 'Fixed', '1' => 'Dismissible']]] as $field => [$label, $options])
            <label class="block text-sm font-semibold text-slate-700">{{ $label }}<select name="announcement[{{ $field }}]" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 px-3 py-2">@foreach($options as $value => $option)<option value="{{ $value }}" @selected((string) old('announcement.'.$field, $announcement[$field] ?? array_key_first($options)) === (string) $value)>{{ $option }}</option>@endforeach</select></label>
        @endforeach
    </div>
    @foreach(['en' => 'English', 'fr' => 'French', 'de' => 'German'] as $locale => $language)
        <fieldset class="grid gap-4 rounded-xl border border-slate-200 p-4 sm:grid-cols-2"><legend class="px-2 text-sm font-semibold">{{ $language }}{{ $locale !== 'en' ? ' (optional; falls back to English)' : '' }}</legend>
            <label class="block text-sm text-slate-700">Message ({{ $language }})<textarea name="announcement[message_{{ $locale }}]" maxlength="2000" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 p-3">{{ old('announcement.message_'.$locale, $announcement['message_'.$locale] ?? '') }}</textarea></label>
            <label class="block text-sm text-slate-700">CTA label ({{ $language }})<input name="announcement[cta_label_{{ $locale }}]" maxlength="100" value="{{ old('announcement.cta_label_'.$locale, $announcement['cta_label_'.$locale] ?? '') }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 p-3"></label>
        </fieldset>
    @endforeach
    <label class="block text-sm font-semibold text-slate-700">Optional CTA URL (HTTPS)<input name="announcement[cta_url]" type="url" maxlength="2048" value="{{ old('announcement.cta_url', $announcement['cta_url'] ?? '') }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 p-3"></label>
    <p class="text-sm text-slate-500">Schedule in Business Timezone: {{ $businessTimezone }}. Blank dates mean no start or expiry limit.</p>
    <div class="grid gap-4 sm:grid-cols-2">@foreach(['start_at' => 'Optional start', 'end_at' => 'Optional expiry'] as $field => $label)
        <label class="block text-sm font-semibold text-slate-700">{{ $label }}<input name="announcement[{{ $field }}]" type="datetime-local" value="{{ old('announcement.'.$field, empty($announcement[$field]) ? '' : ($announcementIsDraft ? $announcement[$field] : \Carbon\CarbonImmutable::parse($announcement[$field])->setTimezone($businessTimezone)->format('Y-m-d\TH:i'))) }}" class="mt-2 block min-h-11 w-full rounded-xl border border-slate-300 p-3"></label>
    @endforeach</div>
</section>

<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Analytics\Services\EngagementCounterService;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Setting;
use App\Domains\Notifications\Services\TelegramNotificationService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function timePreference(Request $request): RedirectResponse
    {
        $validated = $request->validate(['time_format' => ['required', Rule::in(['12', '24'])]]);
        $request->user('web')->update($validated);

        return back()->with('success', 'Your time display preference was saved.');
    }

    public function operational(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->operationalRules($request));
        $this->saveOperationalSettings($request, $validated);

        return back()->with('success', 'Operational settings saved.');
    }

    /** @return array<string, array<mixed>> */
    private function operationalRules(Request $request): array
    {
        return [
            'maintenance_message' => ['nullable', 'string', 'max:2000'],
            'whatsapp_public' => ['required', 'boolean'],
            'whatsapp_portal' => ['required', 'boolean'],
            'whatsapp_url' => [Rule::requiredIf($request->boolean('whatsapp_public') || $request->boolean('whatsapp_portal')), 'nullable', 'url:https', 'max:1000', 'regex:~^https://(?:wa\.me/[1-9][0-9]{6,14}|api\.whatsapp\.com/send)(?:\?.*)?$~'],
            'whatsapp_label' => ['required', 'array:en,fr,de'],
            'whatsapp_label.*' => ['nullable', 'string', 'max:100'],
            'whatsapp_message' => ['required', 'array:en,fr,de'],
            'whatsapp_message.*' => ['nullable', 'string', 'max:1000'],
            'goals' => ['nullable', 'array', 'max:30'],
            'goals.*' => ['string', Rule::in(AnalyticsService::CONVERSION_EVENTS), 'distinct'],
            'exclude_connection' => ['nullable', 'boolean'],
            'clear_exclusions' => ['nullable', 'boolean'],
        ];
    }

    /** @param array<string, mixed> $validated */
    private function saveOperationalSettings(Request $request, array $validated): void
    {
        foreach ($validated as $key => $value) {
            if (! in_array($key, ['goals', 'exclude_connection', 'clear_exclusions'], true)) {
                Setting::set($key, $value ?? '', 'operations');
            }
        }
        Setting::set('analytics.goals', $validated['goals'] ?? [], 'analytics');
        $hashes = $request->boolean('clear_exclusions') ? [] : (array) Setting::get('analytics.internal_hashes', []);
        if ($request->boolean('exclude_connection')) {
            $hashes[] = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));
        }
        Setting::set('analytics.internal_hashes', array_values(array_unique($hashes)), 'analytics');

    }

    public function __construct(
        protected TimezoneService $timezoneService
    ) {}

    public function index(Request $request): View
    {
        $settings = Setting::all()->keyBy('key');
        $timezones = $this->timezoneService->getAvailableTimezones();

        return view('admin.settings.index', [
            'title' => 'System & Business Settings',
            'settings' => $settings,
            'timezones' => $timezones,
            'canManageMaintenance' => $request->user('web')?->role === 'super_admin',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_if(
            $request->user('web')?->role !== 'super_admin' && $request->has('maintenance_mode'),
            403,
            'Only a super administrator can change maintenance mode.'
        );

        $rules = [
            'site_name' => ['required', 'string', 'max:100'],
            'business_timezone' => ['required', 'timezone'],
            'default_language' => ['required', 'string', Rule::in(['en', 'fr', 'de'])],
            'hero_title' => ['required', 'string', 'max:255'],
            'hero_subtitle' => ['required', 'string', 'max:1000'],
            'home_approach_badge' => ['nullable', 'string', 'max:100'],
            'home_approach_title' => ['nullable', 'string', 'max:255'],
            'home_approach_intro' => ['nullable', 'string', 'max:1000'],
            'home_resources_badge' => ['nullable', 'string', 'max:100'],
            'home_resources_title' => ['nullable', 'string', 'max:255'],
            'home_resources_subtitle' => ['nullable', 'string', 'max:1000'],
            'home_games_badge' => ['nullable', 'string', 'max:100'],
            'home_games_title' => ['nullable', 'string', 'max:255'],
            'home_games_subtitle' => ['nullable', 'string', 'max:1000'],
            'home_cta_title' => ['nullable', 'string', 'max:255'],
            'home_cta_subtitle' => ['nullable', 'string', 'max:1000'],
            'home_cta_button' => ['nullable', 'string', 'max:100'],
            'site_footer_text' => ['nullable', 'string', 'max:500'],
            'about_biography' => ['nullable', 'string'],
            'about_philosophy' => ['nullable', 'string'],
            'about_image_path' => ['nullable', 'string', 'max:255'],
            'cancellation_policy' => ['required', 'string', 'max:2000'],
            'booking_cancellation_cutoff_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'booking_reschedule_cutoff_hours' => ['nullable', 'integer', 'min:0', 'max:168'],
            'rescheduling_policy' => ['required', 'string', 'max:2000'],
            'booking_instructions' => ['required', 'string', 'max:2000'],
            'video_meeting_url' => ['nullable', 'string', 'max:2048', 'url:https'],
            'maintenance_mode' => ['nullable', 'boolean'],

            // Social Proof Counters (Phase 2)
            'counters_learning_hours_public_enabled' => ['nullable', 'boolean'],
            'counters_learning_hours_window_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'counters_learning_hours_headline' => ['nullable', 'string', 'max:255'],
            'counters_learning_hours_subtitle' => ['nullable', 'string', 'max:255'],
            'counters_monthly_traffic_public_enabled' => ['nullable', 'boolean'],
            'counters_monthly_traffic_source' => ['nullable', 'string', 'in:unique_visitors,sessions'],
            'counters_monthly_traffic_template_visitors' => ['nullable', 'string', 'max:255'],
            'counters_monthly_traffic_template_sessions' => ['nullable', 'string', 'max:255'],
            'counters_live_users_public_enabled' => ['nullable', 'boolean'],
            'counters_live_users_template' => ['nullable', 'string', 'max:255'],

            // Telegram Reminders (Phase 6)
            'telegram_reminders_enabled' => ['nullable', 'boolean'],
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_reminder_windows' => ['nullable', 'array', 'max:30'],
            'telegram_reminder_windows.*' => ['required', 'integer', 'min:1', 'max:525600', 'distinct'],
            'telegram_notification_chat_ids' => ['nullable', 'array', 'max:30'],
            'telegram_notification_chat_ids.*' => ['required', 'string', 'regex:/^-?[0-9]{1,20}$/', 'distinct'],
        ];

        if ($request->boolean('operations_present')) {
            $rules = array_merge($rules, $this->operationalRules($request));
        }
        $validated = $request->validate($rules);
        if ($request->boolean('telegram_settings_present') && Setting::get('telegram.center_migrated', false)) {
            abort(409, 'Manage Telegram automation in Telegram Bots.');
        }

        $action = $request->input('action', 'publish');
        $isDraft = $action === 'draft';

        DB::transaction(function () use ($request, $validated, $isDraft): void {
            $prev = Setting::all()->pluck('value', 'key')->toArray();

            // Process standard settings
            $counterMap = [
                'counters_learning_hours_public_enabled' => ['key' => 'counters.learning_hours.public_enabled', 'type' => 'bool'],
                'counters_learning_hours_window_days' => ['key' => 'counters.learning_hours.window_days', 'type' => 'int'],
                'counters_learning_hours_headline' => ['key' => 'counters.learning_hours.headline', 'type' => 'string'],
                'counters_learning_hours_subtitle' => ['key' => 'counters.learning_hours.subtitle', 'type' => 'string'],
                'counters_monthly_traffic_public_enabled' => ['key' => 'counters.monthly_traffic.public_enabled', 'type' => 'bool'],
                'counters_monthly_traffic_source' => ['key' => 'counters.monthly_traffic.source', 'type' => 'string'],
                'counters_monthly_traffic_template_visitors' => ['key' => 'counters.monthly_traffic.template_visitors', 'type' => 'string'],
                'counters_monthly_traffic_template_sessions' => ['key' => 'counters.monthly_traffic.template_sessions', 'type' => 'string'],
                'counters_live_users_public_enabled' => ['key' => 'counters.live_users.public_enabled', 'type' => 'bool'],
                'counters_live_users_template' => ['key' => 'counters.live_users.template', 'type' => 'string'],
            ];

            foreach ($counterMap as $inputKey => $meta) {
                if ($meta['type'] === 'bool') {
                    $val = $request->boolean($inputKey);
                    Setting::set($meta['key'], $val, 'counters', true);
                } elseif (array_key_exists($inputKey, $validated) && $validated[$inputKey] !== null) {
                    $val = $meta['type'] === 'int' ? (int) $validated[$inputKey] : (string) $validated[$inputKey];
                    Setting::set($meta['key'], $val, 'counters', true);
                }
            }

            if ($request->boolean('telegram_settings_present')) {
                Setting::set('telegram.reminders_enabled', $request->boolean('telegram_reminders_enabled'), 'telegram', false);

                if ($request->filled('telegram_bot_token')) {
                    $token = trim((string) $request->input('telegram_bot_token'));
                    Setting::set('telegram.bot_token', Crypt::encryptString($token), 'telegram', false);
                }

                Setting::set('telegram.reminder_windows', array_map('intval', $validated['telegram_reminder_windows'] ?? []), 'telegram', false);
                Setting::set('telegram.notification_chat_ids', array_values($validated['telegram_notification_chat_ids'] ?? []), 'telegram', false);
            }

            foreach ($validated as $key => $value) {
                if ($request->boolean('operations_present') && array_key_exists($key, $this->operationalRules($request))) {
                    continue;
                }
                if (isset($counterMap[$key]) || in_array($key, ['telegram_reminders_enabled', 'telegram_bot_token', 'telegram_reminder_windows', 'telegram_notification_chat_ids'])) {
                    continue;
                }

                $group = match ($key) {
                    'site_name', 'default_language', 'maintenance_mode', 'site_footer_text' => 'general',
                    'business_timezone', 'cancellation_policy', 'booking_cancellation_cutoff_hours', 'booking_reschedule_cutoff_hours', 'rescheduling_policy', 'booking_instructions', 'video_meeting_url' => 'booking',
                    'hero_title', 'hero_subtitle', 'home_approach_badge', 'home_approach_title', 'home_approach_intro', 'home_resources_badge', 'home_resources_title', 'home_resources_subtitle', 'home_games_badge', 'home_games_title', 'home_games_subtitle', 'home_cta_title', 'home_cta_subtitle', 'home_cta_button' => 'homepage',
                    'about_biography', 'about_philosophy', 'about_image_path' => 'about',
                    default => 'general',
                };

                if ($isDraft && in_array($group, ['homepage', 'about'])) {
                    Setting::set('draft:'.$key, (string) ($value ?? ''), $group, false);
                } else {
                    Setting::set($key, (string) ($value ?? ''), $group, true);
                    if (in_array($group, ['homepage', 'about'])) {
                        Setting::set('draft:'.$key, (string) ($value ?? ''), $group, false);
                    }
                }
            }

            if ($request->boolean('operations_present')) {
                $this->saveOperationalSettings($request, array_intersect_key($validated, $this->operationalRules($request)));
            }

            if (array_key_exists('business_timezone', $validated)) {
                Cache::forget('active_business_tz');
            }

            if ($request->has('maintenance_mode')) {
                Cache::forget('maintenance_mode_active');
                Cache::forget('system.maintenance_mode');
            }

            Cache::forget('active_business_tz');
            Cache::forget('maintenance_mode_active');
            Cache::forget(EngagementCounterService::cacheKey());

            AuditLog::create([
                'administrator_id' => Auth::id(),
                'action' => $isDraft ? 'settings_draft_saved' : 'settings_published',
                'entity_type' => Setting::class,
                'entity_id' => 0,
                'previous_data' => $prev,
                'new_data' => $validated,
                'created_at' => now(),
            ]);

        });

        $message = $isDraft
            ? 'Settings draft saved successfully. Preview changes at /preview/home and /about/preview.'
            : 'Settings published successfully.';

        return back()->with('success', $message);
    }

    public function testTelegram(Request $request): RedirectResponse
    {
        if (Setting::get('telegram.center_migrated', false)) {
            return redirect()->route('admin.telegram.index', ['tab' => 'destinations']);
        }
        $chatIds = Setting::get('telegram.notification_chat_ids', []);
        if (is_string($chatIds)) {
            $chatIds = json_decode($chatIds, true) ?: array_filter(array_map('trim', explode(',', $chatIds)));
        }

        if (empty($chatIds)) {
            return back()->with('error', 'No notification chat IDs configured in Telegram settings.');
        }

        $service = app(TelegramNotificationService::class);
        $success = 0;
        foreach ($chatIds as $cid) {
            if ($service->sendTestNotification((string) $cid)) {
                $success++;
            }
        }

        if ($success > 0) {
            return back()->with('success', "Test notification dispatched successfully to {$success} chat(s).");
        }

        return back()->with('error', 'Telegram test notification failed. Please verify Bot Token and Chat IDs.');
    }
}

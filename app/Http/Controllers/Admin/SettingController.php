<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        protected TimezoneService $timezoneService
    ) {}

    public function index(): View
    {
        $settings = Setting::all()->keyBy('key');
        $timezones = $this->timezoneService->getAvailableTimezones();

        return view('admin.settings.index', [
            'title' => 'System & Business Settings',
            'settings' => $settings,
            'timezones' => $timezones,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'business_timezone' => ['required', 'string'],
            'default_language' => ['required', 'string', 'in:en,ar'],
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
        ]);

        $action = $request->input('action', 'publish');
        $isDraft = $action === 'draft';

        $prev = Setting::all()->pluck('value', 'key')->toArray();

        foreach ($validated as $key => $value) {
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

        Cache::flush();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => $isDraft ? 'settings_draft_saved' : 'settings_published',
            'entity_type' => Setting::class,
            'entity_id' => 0,
            'previous_data' => $prev,
            'new_data' => $validated,
            'created_at' => now(),
        ]);

        $message = $isDraft
            ? 'Settings draft saved successfully. Preview changes at /preview/home and /about/preview.'
            : 'Settings published successfully.';

        return back()->with('success', $message);
    }
}

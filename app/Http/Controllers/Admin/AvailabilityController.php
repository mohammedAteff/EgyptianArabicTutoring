<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function __construct(
        protected TimezoneService $timezoneService
    ) {}

    public function index(): View
    {
        $businessTz = $this->timezoneService->getBusinessTimezone();
        $rules = AvailabilityRule::query()
            ->orderBy('weekday')
            ->orderBy('start_time')
            ->get();

        $exceptions = AvailabilityException::query()
            ->orderBy('date')
            ->paginate(25);

        $weekdays = [
            0 => 'Sunday',
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
        ];

        return view('admin.availability.index', [
            'title' => 'Tutor Availability & Schedule Windows',
            'rules' => $rules,
            'exceptions' => $exceptions,
            'weekdays' => $weekdays,
            'businessTz' => $businessTz,
            'defaultBufferMinutes' => (int) Setting::get('booking_buffer_minutes', 15),
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $validated = $this->ruleValues($request);

        $rule = AvailabilityRule::create([...$validated, 'enabled' => true]);

        AuditLog::create([
            'administrator_id' => Auth::id(), 'action' => 'availability_rule_created',
            'entity_type' => AvailabilityRule::class, 'entity_id' => $rule->id,
            'new_data' => $rule->toArray(), 'created_at' => now(),
        ]);

        return back()->with('success', 'Weekly availability window added.');
    }

    public function updateRule(Request $request, AvailabilityRule $rule): RedirectResponse
    {
        $validated = $this->ruleValues($request);
        $before = $rule->toArray();
        $rule->update($validated);
        AuditLog::create([
            'administrator_id' => Auth::id(), 'action' => 'availability_rule_updated',
            'entity_type' => AvailabilityRule::class, 'entity_id' => $rule->id,
            'previous_data' => $before, 'new_data' => $rule->toArray(), 'created_at' => now(),
        ]);

        return back()->with('success', 'Availability window and buffer saved. Existing appointments are unchanged.');
    }

    /** @return array<string, mixed> */
    private function ruleValues(Request $request): array
    {
        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'session_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'min_notice_hours' => ['nullable', 'integer', 'min:0', 'max:72'],
            'max_horizon_days' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        $startMinutes = (int) substr($validated['start_time'], 0, 2) * 60 + (int) substr($validated['start_time'], 3, 2);
        $endMinutes = (int) substr($validated['end_time'], 0, 2) * 60 + (int) substr($validated['end_time'], 3, 2);
        if ($endMinutes === $startMinutes) {
            throw ValidationException::withMessages(['end_time' => 'Choose different start and end times. An earlier end time finishes on the following day.']);
        }
        if ($endMinutes < $startMinutes) {
            $endMinutes += 1440;
        }
        if (! empty($validated['session_duration_minutes']) && $endMinutes - $startMinutes < (int) $validated['session_duration_minutes']) {
            throw ValidationException::withMessages(['end_time' => 'This window is shorter than one '.$validated['session_duration_minutes'].'-minute lesson. Extend the window or shorten the lesson. The buffer is the gap between lessons; the last lesson only needs to finish by the window end.']);
        }

        return [
            ...$validated,
            'start_time' => $validated['start_time'].':00',
            'end_time' => sprintf('%02d:%02d:00', intdiv($endMinutes, 60), $endMinutes % 60),
        ];
    }

    public function toggleRule(AvailabilityRule $rule): RedirectResponse
    {
        $rule->update(['enabled' => ! $rule->enabled]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'availability_rule_toggled',
            'entity_type' => AvailabilityRule::class,
            'entity_id' => $rule->id,
            'new_data' => ['enabled' => $rule->enabled],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Availability rule status updated.');
    }

    public function destroyRule(AvailabilityRule $rule): RedirectResponse
    {
        $ruleData = $rule->toArray();
        $rule->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'availability_rule_deleted',
            'entity_type' => AvailabilityRule::class,
            'entity_id' => $rule->id,
            'previous_data' => $ruleData,
            'created_at' => now(),
        ]);

        return back()->with('success', 'Availability window removed.');
    }

    public function storeException(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'is_blocked' => ['required', 'boolean'],
            'start_time' => ['nullable', 'required_if:is_blocked,0', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:is_blocked,0', 'date_format:H:i', 'different:start_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $result = app(AvailabilityService::class)->saveException($validated, Auth::id());

        return back()->with('success', 'Date exception saved. '.$result['confirmed_bookings'].' existing confirmed appointment(s) remain scheduled; review the calendar before making appointment changes.');
    }

    public function destroyException(AvailabilityException $exception): RedirectResponse
    {
        app(AvailabilityService::class)->removeException($exception, Auth::id());

        return back()->with('success', 'Date exception removed. Existing bookings are preserved.');
    }
}

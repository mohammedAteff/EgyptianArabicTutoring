<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Models\AuditLog;
use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
            ->get();

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
        ]);
    }

    public function storeRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'session_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'buffer_minutes' => ['nullable', 'integer', 'min:0', 'max:60'],
            'min_notice_hours' => ['nullable', 'integer', 'min:0', 'max:72'],
            'max_horizon_days' => ['nullable', 'integer', 'min:1', 'max:180'],
        ]);

        $rule = AvailabilityRule::create([
            ...$validated,
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
            'enabled' => true,
        ]);

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'availability_rule_created',
            'entity_type' => AvailabilityRule::class,
            'entity_id' => $rule->id,
            'new_data' => $rule->toArray(),
            'created_at' => now(),
        ]);

        Cache::flush();

        return back()->with('success', 'Weekly availability window added.');
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

        Cache::flush();

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

        Cache::flush();

        return back()->with('success', 'Availability window removed.');
    }

    public function storeException(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'is_blocked' => ['required', 'boolean'],
            'start_time' => ['nullable', 'required_if:is_blocked,0', 'date_format:H:i'],
            'end_time' => ['nullable', 'required_if:is_blocked,0', 'date_format:H:i', 'after:start_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $isBlocked = (bool) $validated['is_blocked'];
        $type = $isBlocked ? 'blocked' : 'special_hours';

        $exception = AvailabilityException::updateOrCreate(
            ['date' => $validated['date']],
            [
                'type' => $type,
                'start_time' => ! $isBlocked && ! empty($validated['start_time']) ? $validated['start_time'].':00' : null,
                'end_time' => ! $isBlocked && ! empty($validated['end_time']) ? $validated['end_time'].':00' : null,
                'notes' => $validated['reason'] ?? null,
            ]
        );

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'availability_exception_saved',
            'entity_type' => AvailabilityException::class,
            'entity_id' => $exception->id,
            'new_data' => $exception->toArray(),
            'created_at' => now(),
        ]);

        Cache::flush();

        return back()->with('success', 'Date exception configured.');
    }

    public function destroyException(AvailabilityException $exception): RedirectResponse
    {
        $exceptionData = $exception->toArray();
        $exception->delete();

        AuditLog::create([
            'administrator_id' => Auth::id(),
            'action' => 'availability_exception_deleted',
            'entity_type' => AvailabilityException::class,
            'entity_id' => $exception->id,
            'previous_data' => $exceptionData,
            'created_at' => now(),
        ]);

        Cache::flush();

        return back()->with('success', 'Date exception removed.');
    }
}

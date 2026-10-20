<?php

namespace App\Http\Controllers\Admin;

use App\Domains\Audit\Services\AuditLogService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Services\BirthdayService;
use App\Domains\Timezone\Services\TimezoneService;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BirthdayController extends Controller
{
    public function index(Request $request, BirthdayService $birthdays, TimezoneService $timezones): View
    {
        $values = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $today = CarbonImmutable::now($timezones->getBusinessTimezone())->startOfDay();
        $month = isset($values['month']) ? CarbonImmutable::createFromFormat('!Y-m', $values['month'], $timezones->getBusinessTimezone()) : $today->startOfMonth();
        abort_unless($month->year >= 2000 && $month->year <= $today->year + 5, 422);

        return view('admin.students.birthdays', [
            'month' => $month, 'today' => $today, 'settings' => $birthdays->settings(), 'businessTimezone' => $timezones->getBusinessTimezone(),
            'birthdays' => $birthdays->between($month, $month->endOfMonth())->groupBy(fn (array $row): int => $row['date']->day),
            'upcoming' => $birthdays->between($today, $today->addDays(30)),
        ]);
    }

    public function all(TimezoneService $timezones, BirthdayService $birthdays): View
    {
        $today = CarbonImmutable::now($timezones->getBusinessTimezone())->startOfDay();
        $records = $birthdays->between($today->startOfYear(), $today->endOfYear())
            ->sortBy(fn (array $row): string => $row['student']->date_of_birth->format('m-d').':'.$row['student']->name)->values();

        return view('admin.students.birthday-list', [
            'birthdayGroups' => $records->groupBy(fn (array $row): int => $row['date']->month),
            'total' => $records->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $values = $request->validate(['enabled' => ['required', 'boolean'], 'days_before' => ['required', 'integer', 'between:0,365'], 'time' => ['required', 'date_format:H:i']]);
        DB::transaction(function () use ($request, $values): void {
            foreach ($values as $key => $value) {
                Setting::set('birthdays.'.$key, $value, 'students');
            }
            app(AuditLogService::class)->log('birthday_reminder_settings_updated', Setting::class, 0, null, $values, $request->user('web')->id);
        });

        return back()->with('success', 'Birthday reminders saved. Only staff receive these notifications.');
    }
}

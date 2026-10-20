<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BirthdayService
{
    /** @return array{enabled: bool, days_before: int, time: string} */
    public function settings(): array
    {
        return ['enabled' => (bool) Setting::get('birthdays.enabled', true),
            'days_before' => (int) Setting::get('birthdays.days_before', 7),
            'time' => (string) Setting::get('birthdays.time', '09:00')];
    }

    public function occurrence(Student $student, int $year): ?CarbonImmutable
    {
        if (! $student->date_of_birth) {
            return null;
        }
        $month = $student->date_of_birth->month;
        $day = min($student->date_of_birth->day, CarbonImmutable::create($year, $month, 1)->daysInMonth);

        return CarbonImmutable::create($year, $month, $day, 0, 0, 0, app(TimezoneService::class)->getBusinessTimezone());
    }

    /** @return Collection<int, array{student: Student, date: CarbonImmutable}> */
    public function between(CarbonImmutable $from, CarbonImmutable $through): Collection
    {
        $birthdays = collect();
        $students = Student::query()->verified()->whereNull('merged_into_student_id')->whereNull('suspended_at')
            ->where('operational_status', 'active')->whereNotNull('date_of_birth')->get(['id', 'first_name', 'last_name', 'date_of_birth']);
        foreach ($students as $student) {
            foreach (range($from->year, $through->year) as $year) {
                $date = $this->occurrence($student, $year);
                if ($date && $date->betweenIncluded($from->startOfDay(), $through->endOfDay())) {
                    $birthdays->push(['student' => $student, 'date' => $date]);
                }
            }
        }

        return $birthdays->sortBy(fn (array $row): string => $row['date']->toDateString().':'.$row['student']->name)->values();
    }

    public function remind(): int
    {
        $settings = $this->settings();
        $now = CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone());
        if (! $settings['enabled'] || $now->format('H:i') < $settings['time']) {
            return 0;
        }
        $target = $now->startOfDay()->addDays($settings['days_before']);
        $sent = 0;
        foreach ($this->between($target, $target) as $birthday) {
            $sent += DB::transaction(function () use ($birthday, $settings): int {
                $student = Student::query()->lockForUpdate()->find($birthday['student']->id);
                if (! $student || $student->identity_status !== 'verified' || $student->operational_status !== 'active' || $student->suspended_at || $student->merged_into_student_id) {
                    return 0;
                }
                if ($this->occurrence($student, $birthday['date']->year)?->toDateString() !== $birthday['date']->toDateString()) {
                    return 0;
                }
                $inserted = DB::table('student_birthday_reminders')->insertOrIgnore([
                    'student_id' => $student->id, 'birthday_date' => $birthday['date']->toDateString(),
                    'days_before' => $settings['days_before'], 'created_at' => now('UTC'),
                ]);
                if (! $inserted) {
                    return 0;
                }
                app(AdminNotificationService::class)->send('student_birthday', 'Birthday coming up · '.$student->name.' (#'.$student->id.')',
                    $student->name.' celebrates their birthday on '.$birthday['date']->format('j F').'.',
                    route('admin.birthdays.index', ['month' => $birthday['date']->format('Y-m')]),
                    ['student_id' => $student->id, 'birthday_date' => $birthday['date']->toDateString()], 'info');

                return 1;
            }, 3);
        }

        return $sent;
    }
}

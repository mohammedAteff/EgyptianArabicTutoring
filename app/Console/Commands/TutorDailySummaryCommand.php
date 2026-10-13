<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\StaffTask;
use App\Domains\Booking\Models\Booking;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class TutorDailySummaryCommand extends Command
{
    protected $signature = 'tutor:daily-summary {--date= : Specific date in Y-m-d format (defaults to today in business timezone)}';

    protected $description = 'Generate daily summary of delivered lessons, cancellations, no-shows, and tasks for the tutor';

    public function handle(TimezoneService $timezones): int
    {
        $tz = $timezones->getBusinessTimezone();
        $dateStr = $this->option('date') ?: now($tz)->toDateString();
        $start = CarbonImmutable::parse($dateStr, $tz)->startOfDay()->utc();
        $end = CarbonImmutable::parse($dateStr, $tz)->endOfDay()->utc();

        $tomorrowStart = $start->addDay();
        $tomorrowEnd = $end->addDay();

        $todayBookings = Booking::query()
            ->where('start_at_utc', '>=', $start)
            ->where('start_at_utc', '<=', $end)
            ->with(['contact', 'student', 'sessionType'])
            ->get();

        $delivered = $todayBookings->where('status', 'completed');
        $confirmed = $todayBookings->where('status', 'confirmed');
        $noShows = $todayBookings->where('status', 'no_show');
        $cancelled = $todayBookings->where('status', 'cancelled');

        $tomorrowBookings = Booking::query()
            ->where('start_at_utc', '>=', $tomorrowStart)
            ->where('start_at_utc', '<=', $tomorrowEnd)
            ->where('status', 'confirmed')
            ->count();

        $tasksCompleted = StaffTask::query()
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<=', $end)
            ->count();

        $tasksDue = StaffTask::query()
            ->where('due_date', $dateStr)
            ->where('status', 'open')
            ->count();

        $this->info('=== End-of-Day Tutor Operations Summary ===');
        $this->info("Date: {$dateStr} ({$tz})");
        $this->newLine();

        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Lessons Scheduled', $todayBookings->count()],
                ['Delivered Lessons (Completed)', $delivered->count()],
                ['Pending / Still Confirmed', $confirmed->count()],
                ['Student No-Shows', $noShows->count()],
                ['Cancellations', $cancelled->count()],
                ['Staff Tasks Completed Today', $tasksCompleted],
                ['Staff Tasks Still Due Today', $tasksDue],
                ['Upcoming Lessons Tomorrow', $tomorrowBookings],
            ]
        );

        if ($delivered->isNotEmpty()) {
            $this->newLine();
            $this->info('Delivered Lessons:');
            $rows = $delivered->map(fn ($b) => [
                $b->id,
                $b->student?->name ?? $b->contact?->name ?? 'Student',
                $b->sessionType?->title ?? 'Lesson',
                $b->business_start->format('H:i'),
            ])->all();
            $this->table(['ID', 'Student', 'Session Type', 'Time (Tutor)'], $rows);
        }

        return self::SUCCESS;
    }
}

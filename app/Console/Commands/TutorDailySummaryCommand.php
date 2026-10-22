<?php

namespace App\Console\Commands;

use App\Domains\Administration\Models\StaffTask;
use App\Domains\Booking\Models\Booking;
use App\Domains\Reporting\Services\BusinessActivityReadModel;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class TutorDailySummaryCommand extends Command
{
    protected $signature = 'tutor:daily-summary {--date= : Specific date in Y-m-d format (defaults to today in business timezone)}';

    protected $description = 'Generate daily summary of delivered lessons, cancellations, no-shows, and tasks for the tutor';

    public function handle(TimezoneService $timezones): int
    {
        $tz = $timezones->getBusinessTimezone();
        $dateStr = $this->option('date') ?: now($tz)->toDateString();
        if (Validator::make(['date' => $dateStr], ['date' => ['required', 'date_format:Y-m-d']])->fails()) {
            $this->error('Use a valid date in Y-m-d format.');

            return self::FAILURE;
        }
        $start = CarbonImmutable::parse($dateStr, $tz)->startOfDay()->utc();
        $end = CarbonImmutable::parse($dateStr, $tz)->endOfDay()->utc();

        $tomorrowStart = CarbonImmutable::parse($dateStr, $tz)->startOfDay()->addDay()->utc();
        $tomorrowEnd = CarbonImmutable::parse($dateStr, $tz)->startOfDay()->addDays(2)->utc();

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
            ->where('start_at_utc', '<', $tomorrowEnd)
            ->where('status', 'confirmed')
            ->count();

        $tasksCompleted = StaffTask::query()
            ->where('completed_at', '>=', $start)
            ->where('completed_at', '<=', $end)
            ->count();

        $tasksDue = StaffTask::query()
            ->where('due_date', $dateStr)
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        $this->info('=== End-of-Day Tutor Operations Summary ===');
        $this->info("Date: {$dateStr} ({$tz})");
        $this->newLine();
        $activity = app(BusinessActivityReadModel::class)->day($dateStr);
        $this->table(['Business Activity', 'Count'], [['New bookings', $activity['newBookings']], ['Payments recorded', $activity['paymentsCount']], ['Leads created', $activity['newLeads']], ['Tasks remaining', $activity['tasksRemaining']]]);
        foreach ($activity['payments'] as $currency => $amount) {
            $this->line('Payments: '.$currency.' '.$amount);
        }

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
                $b->student->name ?? $b->contact->name ?? 'Student',
                $b->sessionType->title ?? 'Lesson',
                $b->business_start->format('H:i'),
            ])->all();
            $this->table(['ID', 'Student', 'Session Type', 'Time (Tutor)'], $rows);
        }

        return self::SUCCESS;
    }
}

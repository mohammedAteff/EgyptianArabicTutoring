<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class EngagementCounterService
{
    /**
     * Cache key for public social proof payload.
     */
    /**
     * Cache TTL in seconds (15 minutes / 900s).
     */
    public const CACHE_TTL = 900;

    /**
     * Get the number of active distinct users in the last 60 seconds.
     */
    public function getLiveUsersCount(): int
    {
        return app(ReportService::class)->nonBotSessionQuery()
            ->where('last_activity_at', '>', now('UTC')->subSeconds(60))->where('last_activity_at', '<=', now('UTC'))->distinct()->count('visitor_id');
    }

    /**
     * Derive metrics for previous calendar month using Cairo boundaries converted to UTC.
     * Half-open interval [month_start, month_end).
     *
     * @return array{unique_visitors: int, sessions: int}
     */
    public function getPreviousMonthTraffic(): array
    {
        $cairoNow = CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone());
        $cairoMonthStart = $cairoNow->subMonthNoOverflow()->startOfMonth();
        $cairoMonthEnd = $cairoNow->startOfMonth();

        $utcMonthStart = $cairoMonthStart->setTimezone('UTC');
        $utcMonthEnd = $cairoMonthEnd->setTimezone('UTC');

        $summary = app(ReportService::class)->getTrafficReport($utcMonthStart, $utcMonthEnd->subMicrosecond())['summary'];

        return ['unique_visitors' => $summary['visitors'], 'sessions' => $summary['sessions']];
    }

    /**
     * Derive learning activity over the configured Cairo calendar window, including today's raw dwell events.
     *
     * @return array{
     *     lesson_hours: float,
     *     study_dwell_hours: float,
     *     total_hours: float,
     *     formatted_time: string,
     *     days: int,
     *     hours: int,
     *     minutes: int
     * }
     */
    public function getCollectiveLearningActivity(int $windowDays = 7): array
    {
        $cairoNow = CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone());
        $cairoDate = $cairoNow->toDateString();
        $cairoWindowStart = $cairoNow->startOfDay()->subDays(max(1, min(90, $windowDays)) - 1);
        $utcWindowStart = $cairoWindowStart->setTimezone('UTC');
        $utcWindowEnd = $cairoNow->setTimezone('UTC');

        // Lesson Hours = sum(bookings duration where status='completed', within window) / 60
        $completedLessonMinutes = (float) app(ReportService::class)->nonBotBookingQuery()
            ->where('status', 'completed')
            ->where('start_at_utc', '>=', $utcWindowStart)
            ->where('start_at_utc', '<', $utcWindowEnd)
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_at_utc, end_at_utc)), 0) as total_mins')
            ->value('total_mins');

        $lessonHours = $completedLessonMinutes / 60.0;

        $dwellSeconds = (float) app(AnalyticsService::class)->reportingMetrics($utcWindowStart, $utcWindowEnd)
            ->where('metric_name', 'section_dwell_seconds')->where('dimension_key', 'section')
            ->whereIn('dimension_value', ['curriculum', 'blog-content', 'resource-preview', 'game-board'])->sum('count');
        $studyDwellHours = $dwellSeconds / 3600.0;
        $totalHours = $lessonHours + $studyDwellHours;

        $totalMinutes = (int) round($totalHours * 60);
        $days = intdiv($totalMinutes, 1440);
        $hours = intdiv($totalMinutes % 1440, 60);
        $minutes = $totalMinutes % 60;

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days} ".($days === 1 ? 'day' : 'days');
        }
        $parts[] = "{$hours} ".($hours === 1 ? 'hour' : 'hours');
        $parts[] = "{$minutes} ".($minutes === 1 ? 'minute' : 'minutes');
        $formattedTime = implode(', ', $parts);

        return [
            'lesson_hours' => round($lessonHours, 2),
            'study_dwell_hours' => round($studyDwellHours, 2),
            'total_hours' => round($totalHours, 2),
            'formatted_time' => $formattedTime,
            'days' => $days,
            'hours' => $hours,
            'minutes' => $minutes,
        ];
    }

    /**
     * Get the consolidated public social proof payload.
     *
     * @return array<string, mixed>
     */
    public function getPublicCountersPayload(): array
    {
        // 1. Live Users
        $liveUsersEnabled = (bool) Setting::get('counters.live_users.public_enabled', false);
        $liveUsersCount = $this->getLiveUsersCount();
        $liveUsersTemplate = (string) Setting::get('counters.live_users.template', '{count} active visitors online right now');
        $liveUsersText = str_replace('{count}', number_format($liveUsersCount), $liveUsersTemplate);

        // 2. Monthly Traffic
        $trafficEnabled = (bool) Setting::get('counters.monthly_traffic.public_enabled', false);
        $trafficSource = (string) Setting::get('counters.monthly_traffic.source', 'unique_visitors');
        $monthlyTraffic = $this->getPreviousMonthTraffic();
        $trafficCount = $trafficSource === 'sessions' ? $monthlyTraffic['sessions'] : $monthlyTraffic['unique_visitors'];
        $trafficTemplate = $trafficSource === 'sessions'
            ? (string) Setting::get('counters.monthly_traffic.template_sessions', 'We had {count} website sessions last month')
            : (string) Setting::get('counters.monthly_traffic.template_visitors', 'We welcomed {count} visitors last month');
        $trafficText = str_replace('{count}', number_format($trafficCount), $trafficTemplate);

        // 3. Learning Hours
        $learningHoursEnabled = (bool) Setting::get('counters.learning_hours.public_enabled', false);
        $windowDays = max(1, (int) Setting::get('counters.learning_hours.window_days', 7));
        $learningActivity = $this->getCollectiveLearningActivity($windowDays);
        $headlineTemplate = (string) Setting::get('counters.learning_hours.headline', 'Globally, students have put in...');
        $subtitleTemplate = (string) Setting::get('counters.learning_hours.subtitle', 'of practice time in the last 7 days');

        $headline = str_replace('{time}', $learningActivity['formatted_time'], $headlineTemplate);
        $subtitle = str_replace('{time}', $learningActivity['formatted_time'], $subtitleTemplate);

        return [
            'live_users' => [
                'enabled' => $liveUsersEnabled,
                'count' => $liveUsersCount,
                'text' => $liveUsersText,
            ],
            'monthly_traffic' => [
                'enabled' => $trafficEnabled,
                'source' => $trafficSource,
                'count' => $trafficCount,
                'text' => $trafficText,
            ],
            'learning_hours' => [
                'enabled' => $learningHoursEnabled,
                'window_days' => $windowDays,
                'total_hours' => $learningActivity['total_hours'],
                'formatted_time' => $learningActivity['formatted_time'],
                'headline' => $headline,
                'subtitle' => $subtitle,
            ],
        ];
    }

    /**
     * Build a cache key isolated by business timezone and calendar day.
     */
    public static function cacheKey(): string
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();

        return 'counters.public.'.hash('sha256', $timezone).'.'.CarbonImmutable::now($timezone)->toDateString();
    }

    public function getCachedPublicPayload(): array
    {
        $cairoDate = CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->toDateString();

        $payload = Cache::remember(self::cacheKey(), self::CACHE_TTL, function () {
            return $this->getPublicCountersPayload();
        });
        $count = $this->getLiveUsersCount();
        $payload['live_users']['count'] = $count;
        $payload['live_users']['enabled'] = (bool) Setting::get('counters.live_users.public_enabled', false);
        $payload['live_users']['text'] = str_replace('{count}', number_format($count), (string) Setting::get('counters.live_users.template', '{count} active visitors online right now'));

        return $payload;
    }
}

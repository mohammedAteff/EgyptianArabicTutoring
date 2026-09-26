<?php

namespace App\Domains\Analytics\Services;

use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EngagementCounterService
{
    /**
     * Cache key for public social proof payload.
     */
    public const CACHE_KEY = 'counters:social_proof_payload';

    /**
     * Cache TTL in seconds (5 minutes / 300s).
     */
    public const CACHE_TTL = 300;

    /**
     * Get the number of active distinct users in the last 60 seconds.
     */
    public function getLiveUsersCount(): int
    {
        return DB::table('visitor_sessions')
            ->where('last_activity_at', '>', now()->subSeconds(60))
            ->distinct('visitor_id')
            ->count('visitor_id');
    }

    /**
     * Derive metrics for previous calendar month using Cairo boundaries converted to UTC.
     * Half-open interval [month_start, month_end).
     *
     * @return array{unique_visitors: int, sessions: int}
     */
    public function getPreviousMonthTraffic(): array
    {
        $cairoNow = CarbonImmutable::now('Africa/Cairo');
        $cairoMonthStart = $cairoNow->subMonthNoOverflow()->startOfMonth();
        $cairoMonthEnd = $cairoNow->startOfMonth();

        $utcMonthStart = $cairoMonthStart->setTimezone('UTC');
        $utcMonthEnd = $cairoMonthEnd->setTimezone('UTC');

        $visitorsCount = DB::table('visitor_sessions')
            ->where('started_at', '>=', $utcMonthStart)
            ->where('started_at', '<', $utcMonthEnd)
            ->distinct('visitor_id')
            ->count('visitor_id');

        $sessionsCount = DB::table('visitor_sessions')
            ->where('started_at', '>=', $utcMonthStart)
            ->where('started_at', '<', $utcMonthEnd)
            ->count('id');

        return [
            'unique_visitors' => $visitorsCount,
            'sessions' => $sessionsCount,
        ];
    }

    /**
     * Derive collective learning activity hours over completed Cairo calendar days.
     * Half-open interval [window_start, window_end) where window_end is midnight today (Cairo),
     * and window_start is window_days complete days prior. Excludes current partial Cairo day.
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
        $cairoNow = CarbonImmutable::now('Africa/Cairo');
        $cairoWindowEnd = $cairoNow->startOfDay();
        $cairoWindowStart = $cairoWindowEnd->subDays($windowDays);

        $utcWindowStart = $cairoWindowStart->setTimezone('UTC');
        $utcWindowEnd = $cairoWindowEnd->setTimezone('UTC');

        // Lesson Hours = sum(bookings duration where status='completed', within window) / 60
        $completedLessonMinutes = (float) DB::table('bookings')
            ->where('status', 'completed')
            ->where('start_at_utc', '>=', $utcWindowStart)
            ->where('start_at_utc', '<', $utcWindowEnd)
            ->selectRaw('COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_at_utc, end_at_utc)), 0) as total_mins')
            ->value('total_mins');

        $lessonHours = $completedLessonMinutes / 60.0;

        // Study Dwell Hours = sum(daily_metrics.count where metric_name='section_dwell_seconds', within window) / 3600
        $dwellSeconds = (float) DB::table('daily_metrics')
            ->where('metric_name', 'section_dwell_seconds')
            ->where('metric_date', '>=', $cairoWindowStart->toDateString())
            ->where('metric_date', '<', $cairoWindowEnd->toDateString())
            ->sum('count');

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
        $headlineTemplate = (string) Setting::get('counters.learning_hours.headline', 'Globally, Line of Action students have put in...');
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
     * Return cached public payload using flat key.
     *
     * @return array<string, mixed>
     */
    public function getCachedPublicPayload(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->getPublicCountersPayload();
        });
    }
}

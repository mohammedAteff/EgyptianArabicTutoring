<?php

namespace App\Domains\Games\Services;

use App\Domains\CMS\Models\Setting;
use App\Domains\Games\Models\Game;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GameAnalyticsService
{
    public function __construct(private ReportService $reports) {}

    /** @return array<string, mixed> */
    public function report(CarbonImmutable $start, CarbonImmutable $end, ?int $gameId = null): array
    {
        $asOf = $end->min(CarbonImmutable::now('UTC'));
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $catalog = Game::query()->when($gameId !== null, fn ($query) => $query->whereKey($gameId))->orderBy('sort_order')->orderBy('id')->get();
        /** @var array<string, array<string, mixed>> $rows */
        $rows = [];
        foreach ($catalog as $game) {
            $rows[$game->slug] = ['id' => $game->id, 'title' => $game->title, 'slug' => $game->slug, 'local' => $game->localView() !== null, 'opened' => 0, 'started' => 0, 'completed' => 0, 'days' => [], 'browsers' => [], 'attempts' => [], 'detail' => []];
        }

        $events = $this->reports->nonBotEventQuery()->whereBetween('created_at', [$start, $asOf])
            ->whereIn('event_name', ['game_opened', 'game_started', 'game_completed', 'game_interrupted', 'game_activity'])
            ->orderBy('created_at')->orderBy('id');
        foreach ($events->lazy(500) as $event) {
            $metadata = $event->metadata ?? [];
            $slug = $metadata['game_slug'] ?? '';
            if (! isset($rows[$slug])) {
                continue;
            }
            $row = &$rows[$slug];
            $phase = substr($event->event_name, 5);
            $day = $event->created_at->copy()->timezone($timezone)->toDateString();
            if (in_array($phase, ['opened', 'started', 'completed'], true)) {
                $row[$phase]++;
                $row['days'][$day][$phase] = ($row['days'][$day][$phase] ?? 0) + 1;
            }
            $browser = $event->visitor_token;
            $attemptId = $metadata['attempt_id'] ?? null;
            if ($browser && is_string($attemptId)) {
                $key = $browser.':'.$attemptId;
                if ($phase === 'started' && ! isset($row['attempts'][$key])) {
                    $row['attempts'][$key] = ['browser' => $browser, 'session' => $event->session_token, 'at' => CarbonImmutable::instance($event->created_at), 'metadata' => $metadata];
                    $row['browsers'][$browser] = ($row['browsers'][$browser] ?? 0) + 1;
                }
                if (in_array($phase, ['completed', 'interrupted', 'activity'], true)) {
                    $detail = &$row['detail'][$key];
                    $detail ??= ['seconds' => null, 'terminal' => null];
                    if ($detail['terminal'] === null && isset($metadata['duration_seconds']) && is_numeric($metadata['duration_seconds']) && $metadata['duration_seconds'] >= 0 && $metadata['duration_seconds'] <= 86400) {
                        $detail['seconds'] = max($detail['seconds'] ?? 0, (int) $metadata['duration_seconds']);
                    }
                    if ($phase !== 'activity' && $detail['terminal'] === null) {
                        $detail['terminal'] = ['phase' => $phase, 'reason' => $metadata['reason'] ?? 'unknown', 'ending' => $metadata['ending'] ?? 'legacy', 'at' => CarbonImmutable::instance($event->created_at)];
                    }
                    unset($detail);
                }
            }
            unset($row);
        }

        /** @var array<string, array<array-key, int>> $breakdowns */
        $breakdowns = ['practice_language' => [], 'ui_locale' => [], 'device_type' => [], 'detected_country_code' => [], 'mode' => [], 'level' => []];
        $timeline = [];
        $history = DB::table('daily_metrics')->where('dimension_key', 'game')->whereIn('dimension_value', $catalog->modelKeys())->where('reporting_timezone', $timezone)
            ->whereIn('metric_name', ['opened', 'started', 'completed'])->whereBetween('metric_date', [$start->timezone($timezone)->toDateString(), $asOf->timezone($timezone)->toDateString()])->get()->groupBy('dimension_value');
        foreach ($rows as &$row) {
            $durations = [];
            $sessions = [];
            $sessionDurations = [];
            $row += ['linked_completed' => 0, 'timed' => 0, 'advanced' => 0, 'interrupted' => 0, 'page_left' => 0, 'pending' => 0, 'without_completion' => 0, 'uses_history' => false];
            foreach ($row['attempts'] as $key => $attempt) {
                if ($attempt['session']) {
                    $sessions[$attempt['session']] = true;
                }
                $detail = $row['detail'][$key] ?? null;
                $terminal = $detail['terminal'] ?? null;
                if ($terminal && $terminal['at']->lt($attempt['at'])) {
                    $terminal = null;
                }
                if (($terminal['phase'] ?? null) === 'completed') {
                    $row['linked_completed']++;
                    if (in_array($terminal['ending'], ['timed', 'advanced'], true)) {
                        $row[$terminal['ending']]++;
                    }
                } elseif ($terminal) {
                    $row['interrupted']++;
                    $row['page_left'] += $terminal['reason'] === 'page_left' ? 1 : 0;
                } elseif ($attempt['at']->gt($asOf->subDay())) {
                    $row['pending']++;
                } else {
                    $row['without_completion']++;
                }
                if (isset($detail['seconds'])) {
                    $observedThrough = $terminal['at'] ?? $asOf;
                    $wallSeconds = max(0, (int) ceil($attempt['at']->diffInSeconds($observedThrough, false)));
                    $seconds = min($detail['seconds'], $wallSeconds, 86400);
                    $durations[] = $seconds;
                    if ($attempt['session']) {
                        $sessionDurations[$attempt['session']] = ($sessionDurations[$attempt['session']] ?? 0) + $seconds;
                    }
                }
                foreach ($breakdowns as $dimension => &$counts) {
                    $value = mb_substr((string) ($attempt['metadata'][$dimension] ?? 'Unknown'), 0, 50);
                    if ($dimension === 'level' && ($attempt['metadata']['mode'] ?? null) === 'wild') {
                        $value = 'Not applicable';
                    }
                    $counts[$value] = ($counts[$value] ?? 0) + 1;
                }
                unset($counts);
            }
            $row['attempt_count'] = count($row['attempts']);
            $row['tracked_browsers'] = count($row['browsers']);
            $row['repeat_browsers'] = count(array_filter($row['browsers'], fn (int $count): bool => $count > 1));
            $row['completion_rate'] = $row['attempt_count'] ? round(100 * $row['linked_completed'] / $row['attempt_count'], 1) : null;
            $row['duration_samples'] = count($durations);
            $row['play_sessions'] = count($sessions);
            $row['session_samples'] = count($sessionDurations);
            $row['average_session_seconds'] = $sessionDurations !== [] ? round(array_sum($sessionDurations) / count($sessionDurations)) : null;
            $row['active_seconds'] = array_sum($durations);
            $row['average_seconds'] = $durations !== [] ? round(array_sum($durations) / count($durations)) : null;
            sort($durations);
            $middle = intdiv(count($durations), 2);
            $row['median_seconds'] = $durations === [] ? null : (count($durations) % 2 ? $durations[$middle] : ($durations[$middle - 1] + $durations[$middle]) / 2);
            $this->includeHistory($row, $history->get((string) $row['id'], collect()), $start, $asOf, $timezone);
            foreach ($row['days'] as $day => $counts) {
                foreach ($counts as $phase => $count) {
                    $timeline[$day][$phase] = ($timeline[$day][$phase] ?? 0) + $count;
                }
            }
            unset($row['browsers'], $row['attempts'], $row['detail'], $row['days']);
        }
        unset($row);
        foreach ($breakdowns as &$counts) {
            arsort($counts);
        }
        unset($counts);
        ksort($timeline);
        $collection = collect(array_values($rows))->sortByDesc('started')->values();

        return ['rows' => $collection, 'breakdowns' => $breakdowns, 'timeline' => $timeline, 'as_of' => $asOf, 'retention_days' => (int) Setting::get('analytics_retention_days', 180),
            'summary' => ['opened' => $collection->sum('opened'), 'started' => $collection->sum('started'), 'completed' => $collection->sum('linked_completed'), 'active_seconds' => $collection->sum('active_seconds'), 'duration_samples' => $collection->sum('duration_samples')]];
    }

    /** @param array<array-key, mixed> $row */
    private function includeHistory(array &$row, Collection $history, CarbonImmutable $start, CarbonImmutable $end, string $timezone): void
    {
        foreach ($history as $metric) {
            $day = CarbonImmutable::parse($metric->metric_date, $timezone);
            if ($start->gt($day->utc()) || $end->lt($day->endOfDay()->utc()->startOfSecond())) {
                continue;
            }
            $raw = $row['days'][$metric->metric_date][$metric->metric_name] ?? 0;
            if ($metric->count > $raw) {
                $row[$metric->metric_name] += (int) $metric->count - $raw;
                $row['days'][$metric->metric_date][$metric->metric_name] = (int) $metric->count;
                $row['uses_history'] = true;
            }
        }
    }
}

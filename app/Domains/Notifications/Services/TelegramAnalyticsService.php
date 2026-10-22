<?php

namespace App\Domains\Notifications\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Analytics\Services\ContentAnalyticsService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class TelegramAnalyticsService
{
    public function __construct(private ContentAnalyticsService $content, private TelegramReadService $read, private AnalyticsService $analytics, private TelegramReportFormatter $formatter) {}

    /** @param array<string, mixed> $state
     * @return array{text: string, buttons: list<array<string, mixed>>}
     */
    public function page(CarbonImmutable $start, CarbonImmutable $end, array $state): array
    {
        $section = $state['section'] ?? 'overview';
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $days = (int) $start->setTimezone($timezone)->startOfDay()->diffInDays($end->setTimezone($timezone)->startOfDay()) + 1;
        $buttons = [];
        $page = max(0, (int) ($state['page'] ?? 0));
        $title = $start->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->format('j M Y').' – '.$end->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->format('j M Y').' · '.e(app(TimezoneService::class)->getBusinessTimezone());
        if ($section === 'overview') {
            $text = $this->read->stats($days, $start, $end);
        } elseif (in_array($section, ['games', 'resources'], true)) {
            $id = isset($state['id']) ? (int) $state['id'] : null;
            $report = $section === 'games' ? $this->content->games($start, $end, $id) : $this->content->resources($start, $end, $id);
            $rows = $report['rows'];
            $lines = ['<b>'.($section === 'games' ? '🎮 Games' : '📚 Resources').'</b>', $title, ''];
            $item = $id !== null ? $rows->first() : null;
            if ($item) {
                $lines[] = '<b>'.e($item['title']).'</b>';
                $view = $state['view'] ?? 'overview';
                if (in_array($view, ['countries', 'sources'], true)) {
                    $lines = array_merge($lines, $this->dimensions($item[$view], $view, $page));
                    $this->pagination($buttons, $page, count($item[$view]), 10);
                } elseif ($view === 'trend') {
                    $lines[] = 'Daily recorded activity';
                    krsort($item['days']);
                    foreach (array_slice($item['days'], $page * 10, 10, true) as $date => $values) {
                        $lines[] = $date.' · '.implode(' · ', array_map(fn ($key, $count): string => $key.': '.$count, array_keys($values), $values));
                    }
                    if ($item['days'] === []) {
                        $lines[] = 'No recorded activity.';
                    }
                    $this->pagination($buttons, $page, count($item['days']), 10);
                } elseif ($section === 'games') {
                    $lines = array_merge($lines, $this->gameLines($item));
                } else {
                    $lines = array_merge($lines, $this->resourceLines($item));
                }
                foreach (['overview' => 'Overview', 'trend' => 'Daily trend', 'countries' => 'Countries', 'sources' => 'Sources'] as $key => $label) {
                    $buttons[] = ['label' => $label, 'state' => ['view' => $key, 'page' => 0]];
                }
                $buttons[] = ['label' => $section === 'games' ? 'Open game' : 'Open resource', 'url' => route($section === 'games' ? 'games.show' : 'resources.show', $item['slug'])];
                $buttons[] = ['label' => 'All '.$section, 'state' => ['id' => null, 'view' => null, 'page' => 0]];
            } elseif ($id !== null) {
                $lines[] = 'This item is no longer available.';
                $buttons[] = ['label' => 'All '.$section, 'state' => ['id' => null, 'view' => null]];
            } else {
                $lines[] = $section === 'games'
                    ? 'Opens: '.$rows->sum('opened').' · Starts: '.$rows->sum('started').' · Recorded completions: '.$rows->sum('completed')
                    : 'Requests: '.$rows->sum('requests').' · Access actions: '.$rows->sum('download_actions');
                $lines[] = $section === 'games' ? 'Linked attempts: '.$rows->sum('attempts').' · Linked completions: '.$rows->sum('linked_completed') : 'Requests followed by access: '.$rows->sum('linked_downloads');
                $previousStart = $start->setTimezone($timezone)->subDays($days)->utc();
                $previousEnd = $end->min(CarbonImmutable::now('UTC'))->setTimezone($timezone)->subDays($days)->utc();
                $previous = $section === 'games' ? $this->content->games($previousStart, $previousEnd) : $this->content->resources($previousStart, $previousEnd);
                $metric = $section === 'games' ? 'started' : 'requests';
                $current = $rows->sum($metric);
                $before = $previous['rows']->sum($metric);
                $lines[] = 'Previous comparable period '.$metric.': '.$before.' · Change: '.($current - $before >= 0 ? '+' : '').($current - $before);
                $lines[] = 'Includes today only through the current time, when applicable.';
                $lines[] = '';
                $page = max(0, (int) ($state['page'] ?? 0));
                foreach ($rows->slice($page * 6, 6) as $row) {
                    $lines[] = '<b>'.e($row['title']).'</b>'.($row['external'] ? ' · External' : '');
                    $lines[] = $section === 'games' ? $row['opened'].' opens · '.$row['started'].' starts · '.$row['completed'].' recorded completions' : $row['requests'].' requests · '.$row['download_actions'].' access actions';
                    if ($row['id'] !== null) {
                        $buttons[] = ['label' => Str::limit($row['title'], 32), 'state' => ['id' => $row['id'], 'view' => 'overview', 'page' => 0]];
                    }
                }
                if ($rows->isEmpty()) {
                    $lines[] = 'No items recorded.';
                }
                if ($page > 0) {
                    $buttons[] = ['label' => '← Previous', 'state' => ['page' => $page - 1]];
                }
                if (($page + 1) * 6 < $rows->count()) {
                    $buttons[] = ['label' => 'Next →', 'state' => ['page' => $page + 1]];
                }
            }
            $lines[] = '';
            $lines[] = 'Detailed views use retained activity. Missing historical detail is not reconstructed.';
            $text = implode("\n", $lines);
        } elseif ($section === 'funnel') {
            $data = $this->analytics->getPrimaryBookingFunnel($start, $end);
            $text = '<b>📈 Booking funnel</b>'."\n".$title."\n\n".'Visitors in cohort: '.$data['visitors']."\n".'Booking CTA reached / qualified: '.$data['cta_qualified']."\n".'Booking started: '.$data['booking_started']."\n".'Slot held: '.$data['slot_held']."\n".'Booking completed: '.$data['booking_completed']."\n".'Overall conversion: '.$data['overall_conversion'].'%'."\n".e($data['maturity_label'])."\n".'CTA qualified includes observed and inferred stages; recent cohorts are still developing.';
        } elseif ($section === 'countries') {
            $lines = ['<b>🌍 Countries · website visitors</b>', $title, ''];
            $countries = $this->analytics->reportingCountries($start, $end);
            foreach ($countries->slice($page * 10, 10) as $country) {
                $lines[] = e($this->formatter->country((string) $country->country_code)).' — '.$country->unique_visitors;
            }
            $this->pagination($buttons, $page, $countries->count(), 10);
            $text = implode("\n", $lines);
        } else {
            $lines = ['<b>📣 Sources · recorded website activity</b>', $title, ''];
            $sources = $this->analytics->getAcquisitionPerformance($start, $end);
            $lines[] = 'Top sources and campaigns · up to 50 groups';
            foreach ($sources->slice($page * 8, 8) as $source) {
                $lines[] = '<b>'.e(Str::limit((string) $source->source, 60)).'</b>'.($source->campaign ? ' · '.e(Str::limit((string) $source->campaign, 60)) : '');
                $lines[] = $source->visitors.' visitors · '.$source->bookings.' booking events · '.$source->leads.' resource requests';
            }
            $this->pagination($buttons, $page, $sources->count(), 8);
            $text = implode("\n", $lines);
        }
        foreach (['overview' => '📊 Overview', 'games' => '🎮 Games', 'resources' => '📚 Resources', 'funnel' => '📈 Booking funnel', 'countries' => '🌍 Countries', 'sources' => '📣 Sources'] as $key => $label) {
            if ($key !== $section) {
                $buttons[] = ['label' => $label, 'command' => 'stats', 'state' => ['section' => $key, 'id' => null, 'view' => null, 'page' => 0]];
            }
        }

        return ['text' => $text, 'buttons' => $buttons];
    }

    /** @param array<string, mixed> $row
     * @return list<string>
     */
    private function gameLines(array $row): array
    {
        $lines = ['Opens: '.$row['opened'].' · Tracked visitors (retained detail): '.$row['tracked_visitors'], 'Recorded starts: '.$row['started'].' · Recorded completions: '.$row['completed'], '', '<b>Linked play attempts · retained detail</b>',
            'Started: '.$row['attempts'].' · Completed: '.$row['linked_completed'], 'Completion: '.($row['completion_rate'] !== null ? $row['completion_rate'].'% ('.$row['linked_completed'].'/'.$row['attempts'].')' : 'Not available'),
            'Recent unfinished (<24h): '.$row['pending'].' · No completion recorded after 24h: '.$row['without_completion'], 'Repeat tracked players: '.$row['repeat_players'], 'Legacy starts without a play ID: '.$row['legacy_starts'], '', '<b>Submitted results · engagement data</b>',
            'Average score: '.($row['average_score'] !== null ? $row['average_score'].'%' : 'Not available').' · '.$row['score_samples'].' valid samples'];
        foreach ($row['score_distribution'] as $range => $count) {
            $lines[] = $range.': '.$count;
        }
        $lines[] = 'Average active play: '.($row['average_seconds'] !== null ? $row['average_seconds'].' sec' : 'Not available').' · '.$row['duration_samples'].' samples';
        $lines[] = 'Later confirmed/completed/no-show bookings: '.$row['later_bookings'].' · same tracked browser, within this period; association, not causation.';
        $lines[] = 'Starts belong to this period; completions are counted through its report end. Visitors represent tracked browsers.';
        if ($row['external']) {
            $lines[] = 'External game: an outbound open does not confirm play. Start/result tracking requires integration.';
        }

        return $lines;
    }

    /** @param array<string, mixed> $row
     * @return list<string>
     */
    private function resourceLines(array $row): array
    {
        return ['Detail-page views: '.$row['views'].' · Access-panel views: '.$row['gate_views'], 'Requests: '.$row['requests'].' · Distinct requesting contacts: '.$row['contacts'],
            'First-time requesters: '.$row['new_requesters'].' · Returning requesters: '.$row['returning_requesters'], 'First-time means no earlier resource request under this contact record.', '',
            'Recorded access actions: '.$row['download_actions'], 'Requests followed by access: '.$row['linked_downloads'].'/'.$row['requests'].' · '.($row['download_rate'] !== null ? $row['download_rate'].'%' : 'Not available'),
            'Requests without recorded access: '.$row['without_download'], '', $row['external'] ? 'External access actions are redirects; file receipt is not confirmed.' : 'Local access actions are file delivery requests; reading or successful receipt is not confirmed.',
            'Later confirmed/completed/no-show bookings after a tracked request: '.$row['later_bookings'].' · same browser, within this period; association, not causation.',
            'Request-to-access figures follow requests created within the selected period, through report end.'];
    }

    /** @param array<string, array{events: int, visitors: array<string, bool>}> $values
     * @return list<string>
     */
    private function dimensions(array $values, string $dimension, int $page): array
    {
        uasort($values, fn (array $a, array $b): int => count($b['visitors']) <=> count($a['visitors']));
        $lines = ['<b>'.($dimension === 'countries' ? '🌍 Countries' : '📣 Sources').'</b>', 'Tracked browsers and recorded events; repeat events count separately.'];
        foreach (array_slice($values, $page * 10, 10, true) as $label => $row) {
            $lines[] = ($dimension === 'countries' ? e($this->formatter->country((string) $label)) : e(Str::limit((string) $label, 70))).' — '.count($row['visitors']).' browsers · '.$row['events'].' events';
        }
        if ($values === []) {
            $lines[] = 'No recorded data.';
        }

        return $lines;
    }

    /** @param list<array<string, mixed>> $buttons */
    private function pagination(array &$buttons, int $page, int $count, int $perPage): void
    {
        if ($page > 0) {
            $buttons[] = ['label' => '← Previous', 'state' => ['page' => $page - 1]];
        }
        if (($page + 1) * $perPage < $count) {
            $buttons[] = ['label' => 'Next →', 'state' => ['page' => $page + 1]];
        }
    }
}

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
        $title = $this->formatter->period($start, $end);
        if (in_array($section, ['overview', 'pages', 'changes'], true)) {
            $text = $this->read->stats($days, $start, $end, $section);
        } elseif ($section === 'details') {
            $text = '<b>📊 Website details</b>'."\n".$title."\n\nChoose a breakdown.";
            foreach (['pages' => 'Pages', 'countries' => 'Countries', 'sources' => 'Sources', 'funnel' => 'Booking funnel', 'changes' => 'Changes', 'notes' => 'How counted'] as $key => $label) {
                $buttons[] = ['label' => $label, 'state' => ['section' => $key, 'page' => 0], 'row' => 'sections'];
            }
        } elseif ($section === 'notes') {
            $text = '<b>ℹ️ How analytics are counted</b>'."\n".$title."\n\nVisitors are tracked browsers. Where raw history is unavailable, the report labels a sum of daily uniques; that is not a period-unique count.\n\nToday ends at the current time. Comparisons use the same local time and matching visitor-count basis.\n\nDetailed breakdowns use retained activity. Missing historical details are not reconstructed.\n\nGame results are submitted engagement data. External game opens do not confirm play. Resource access actions do not prove that a file was received or read.";
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
                    $lines = array_merge($lines, $this->gameLines($item, $view));
                } else {
                    $lines = array_merge($lines, $this->resourceLines($item, $view));
                }
                $views = ['overview' => 'Overview'] + ($section === 'games' ? ['play' => 'Play attempts', 'results' => 'Scores & time'] : ['journey' => 'Request journey']) + ['trend' => 'Daily trend', 'countries' => 'Countries', 'sources' => 'Sources', 'notes' => 'How counted'];
                foreach ($views as $key => $label) {
                    if ($key !== $view) {
                        $buttons[] = ['label' => $label, 'state' => ['view' => $key, 'page' => 0], 'row' => 'views'];
                    }
                }
                $buttons[] = ['label' => $section === 'games' ? 'Open game ↗' : 'Open resource ↗', 'url' => route($section === 'games' ? 'games.show' : 'resources.show', $item['slug']), 'row' => 'actions'];
                $buttons[] = ['label' => '← All '.$section, 'state' => ['id' => null, 'view' => null, 'page' => 0], 'row' => 'back'];
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
                $lines[] = 'Today is counted through now.';
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
        $destinations = $section === 'overview' ? ['games' => '🎮 Games', 'resources' => '📚 Resources', 'details' => 'Website details']
            : (in_array($section, ['games', 'resources'], true) ? ['overview' => '← Analytics'] : ['details' => '← Details', 'overview' => '← Analytics']);
        foreach ($destinations as $key => $label) {
            if ($key !== $section) {
                $buttons[] = ['label' => $label, 'command' => 'stats', 'state' => ['section' => $key, 'id' => null, 'view' => null, 'page' => 0], 'row' => 'sections'];
            }
        }

        return ['text' => $text, 'buttons' => $buttons];
    }

    /** @param array<string, mixed> $row
     * @return list<string>
     */
    private function gameLines(array $row, string $view): array
    {
        if ($view === 'overview') {
            return ['Opens: '.$row['opened'], 'Tracked browsers: '.$row['tracked_visitors'], '', 'Recorded starts: '.$row['started'], 'Recorded completions: '.$row['completed'], '', 'Linked completion: '.($row['completion_rate'] !== null ? $row['completion_rate'].'% ('.$row['linked_completed'].'/'.$row['attempts'].')' : 'Not available'), 'Linked figures use retained play attempts.'];
        }
        if ($view === 'play') {
            return ['<b>Play attempts · retained activity</b>', 'Started: '.$row['attempts'], 'Completed: '.$row['linked_completed'], '', 'Unfinished under 24h: '.$row['pending'], 'No finish after 24h: '.$row['without_completion'], 'Repeat players: '.$row['repeat_players'], 'Legacy starts without a play ID: '.$row['legacy_starts']];
        }
        if ($view === 'notes') {
            return ['<b>How counted</b>', 'Starts belong to this period. Completions are linked through report end to the same game, browser and play ID.', '', 'Detailed views use retained activity; missing history is not reconstructed.', 'Results are submitted engagement data, not verified assessments.', '', 'Later bookings: '.$row['later_bookings'].' · confirmed/completed/no-show, same browser within this period. Association, not causation.', $row['external'] ? 'External game opens do not confirm play; start/result tracking requires integration.' : 'Active time excludes time while the game is hidden or unfocused.'];
        }
        $lines = ['<b>Scores &amp; active time</b>',
            'Average score: '.($row['average_score'] !== null ? $row['average_score'].'%' : 'Not available').' · '.$row['score_samples'].' valid samples'];
        foreach ($row['score_distribution'] as $range => $count) {
            $lines[] = $range.': '.$count;
        }
        $lines[] = 'Average active play: '.($row['average_seconds'] !== null ? $row['average_seconds'].' sec' : 'Not available').' · '.$row['duration_samples'].' samples';

        return $lines;
    }

    /** @param array<string, mixed> $row
     * @return list<string>
     */
    private function resourceLines(array $row, string $view): array
    {
        if ($view === 'overview') {
            return ['Detail-page views: '.$row['views'], 'Access-panel views: '.$row['gate_views'], '', 'Requests: '.$row['requests'], 'Access actions: '.$row['download_actions'], 'Requests followed by access: '.$row['linked_downloads'].'/'.$row['requests'].' · '.($row['download_rate'] !== null ? $row['download_rate'].'%' : 'Not available')];
        }
        if ($view === 'journey') {
            return ['<b>Request journey</b>', 'Requesting contacts: '.$row['contacts'], 'First-time: '.$row['new_requesters'], 'Returning: '.$row['returning_requesters'], '', 'Requests without access: '.$row['without_download'], 'Later bookings: '.$row['later_bookings'], 'Later bookings share a tracked browser and follow a request within this period; association, not causation.'];
        }

        return ['<b>How counted</b>', 'First-time requesters have no earlier resource request under the same contact record.', '',
            'Request-to-access figures follow requests created in this period, through report end. Repeated access actions do not increase the number of converted requests.', '',
            $row['external'] ? 'External actions are redirects. File receipt is not confirmed.' : 'Local actions are file delivery requests. Reading or successful receipt is not confirmed.', '',
            'Later bookings include confirmed, completed and no-show bookings after a request by the same browser, within this period. Association, not causation.', '',
            'Detailed views use retained activity; missing history is not reconstructed.'];
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

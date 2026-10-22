<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Booking\Models\Booking;
use App\Domains\Games\Models\Game;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Resources\Models\Resource;
use App\Domains\Resources\Models\ResourceDownload;
use App\Domains\Resources\Models\ResourceRequest;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ContentAnalyticsService
{
    public function __construct(private ReportService $reports) {}

    /** @return array<string, mixed> */
    public function games(CarbonImmutable $start, CarbonImmutable $end, ?int $gameId = null, bool $includeHistory = true): array
    {
        $asOf = $end->min(CarbonImmutable::now('UTC'));
        $catalog = Game::query()->when($gameId !== null, fn ($query) => $query->whereKey($gameId))->get(['id', 'slug', 'title', 'target_url', 'status'])->keyBy('slug');
        $groups = [];
        foreach ($catalog as $slug => $game) {
            $groups[$slug] = $this->gameGroup($slug, $game->title, $game->id, (bool) $game->target_url, $game->status);
        }
        $query = $this->reports->nonBotEventQuery()->whereBetween('created_at', [$start, $asOf])->whereIn('event_name', ['game_opened', 'game_started', 'game_completed']);
        foreach ($query->orderBy('id')->lazyById(500) as $event) {
            $slug = (string) ($event->metadata['game_slug'] ?? '');
            if (! isset($groups[$slug])) {
                if ($gameId !== null || $slug === '') {
                    continue;
                }
                $groups[$slug] = $this->gameGroup($slug, (string) ($event->metadata['game_title'] ?? $slug), null, false, 'historical');
            }
            $row = &$groups[$slug];
            $phase = substr($event->event_name, 5);
            $row[$phase]++;
            $identity = $event->visitor_token;
            if ($identity) {
                $at = CarbonImmutable::instance($event->created_at);
                if (! isset($row['visitors'][$identity]) || $row['visitors'][$identity]->gt($at)) {
                    $row['visitors'][$identity] = $at;
                }
            }
            $date = $event->created_at->copy()->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString();
            $row['days'][$date][$phase] = ($row['days'][$date][$phase] ?? 0) + 1;
            $this->dimension($row['countries'], $event->metadata['detected_country_code'] ?? 'ZZ', $identity);
            $source = ($event->utm_source ?: 'Direct traffic').($event->utm_medium ? ' · '.$event->utm_medium : '').($event->utm_campaign ? ' · '.$event->utm_campaign : '');
            $this->dimension($row['sources'], $source, $identity);
            $attempt = $event->metadata['attempt_id'] ?? null;
            if (is_string($attempt) && $identity) {
                $key = $identity.':'.$attempt;
                $facts = ['at' => CarbonImmutable::instance($event->created_at), 'identity' => $identity, 'metadata' => $event->metadata];
                if ($phase === 'started' && (! isset($row['attempts'][$key]) || $row['attempts'][$key]['at']->gt($facts['at']))) {
                    $row['attempts'][$key] = $facts;
                } elseif ($phase === 'completed' && (! isset($row['finishes'][$key]) || $row['finishes'][$key]['at']->gt($facts['at']))) {
                    $row['finishes'][$key] = $facts;
                }
            }
            unset($row);
        }
        $identities = [];
        foreach ($groups as $group) {
            $identities = array_merge($identities, array_keys($group['visitors']));
        }
        $bookings = $this->bookingEvents($start, $asOf, array_values(array_unique($identities)));
        $rows = [];
        foreach ($groups as $row) {
            $linked = 0;
            $pending = 0;
            $scores = [];
            $durations = [];
            $distribution = ['0–49%' => 0, '50–79%' => 0, '80–100%' => 0];
            $players = [];
            foreach ($row['attempts'] as $key => $attempt) {
                $players[$attempt['identity']] = ($players[$attempt['identity']] ?? 0) + 1;
                $finish = $row['finishes'][$key] ?? null;
                if (! $finish || $finish['at']->lt($attempt['at'])) {
                    $pending += $attempt['at']->gt($asOf->subDay()) ? 1 : 0;

                    continue;
                }
                $linked++;
                $metadata = $finish['metadata'];
                if (isset($metadata['score'], $metadata['total']) && is_numeric($metadata['score']) && is_numeric($metadata['total'])
                    && (float) $metadata['total'] > 0 && (float) $metadata['score'] >= 0 && (float) $metadata['score'] <= (float) $metadata['total']) {
                    $score = 100 * (float) $metadata['score'] / (float) $metadata['total'];
                    $scores[] = $score;
                    $distribution[$score < 50 ? '0–49%' : ($score < 80 ? '50–79%' : '80–100%')]++;
                }
                if (isset($metadata['duration_seconds']) && is_numeric($metadata['duration_seconds']) && $metadata['duration_seconds'] >= 0 && $metadata['duration_seconds'] <= 86400) {
                    $durations[] = (float) $metadata['duration_seconds'];
                }
            }
            $attempts = count($row['attempts']);
            $rows[] = array_diff_key($row, array_flip(['attempts', 'finishes', 'visitors'])) + [
                'tracked_visitors' => count($row['visitors']), 'attempts' => $attempts, 'linked_completed' => $linked, 'pending' => $pending,
                'without_completion' => $attempts - $linked - $pending, 'legacy_starts' => max(0, $row['started'] - $attempts),
                'completion_rate' => $attempts > 0 ? round(100 * $linked / $attempts, 1) : null,
                'repeat_players' => count(array_filter($players, fn (int $count): bool => $count > 1)),
                'score_samples' => count($scores), 'score_sum' => array_sum($scores), 'average_score' => $scores === [] ? null : round(array_sum($scores) / count($scores), 1),
                'score_distribution' => $distribution, 'duration_samples' => count($durations), 'duration_sum' => array_sum($durations),
                'average_seconds' => $durations === [] ? null : (int) round(array_sum($durations) / count($durations)),
                'later_bookings' => $this->bookingsAfter($row['visitors'], $bookings),
            ];
        }

        if ($includeHistory) {
            $rows = $this->history($rows, 'game', ['opened', 'started', 'completed'], $start, $asOf);
        }

        return ['rows' => collect($rows)->sortByDesc('opened')->values(), 'as_of' => $asOf];
    }

    /** @return array<string, mixed> */
    public function resources(CarbonImmutable $start, CarbonImmutable $end, ?int $resourceId = null, bool $includeHistory = true): array
    {
        $asOf = $end->min(CarbonImmutable::now('UTC'));
        $catalog = Resource::query()->when($resourceId !== null, fn ($query) => $query->whereKey($resourceId))->get(['id', 'title', 'slug', 'status', 'external_url', 'category_id']);
        $operational = $this->reports->getResourcesReport($start, $asOf, $resourceId !== null ? ['resource_id' => $resourceId] : [])['rows']->keyBy('id');
        $requests = ResourceRequest::query()->whereIn('resource_id', $catalog->pluck('id'))->whereBetween('created_at', [$start, $asOf])->get(['id', 'resource_id', 'contact_id', 'visitor_token', 'source', 'created_at']);
        $bookings = $this->bookingEvents($start, $asOf, $requests->pluck('visitor_token')->filter()->unique()->values()->all());
        $downloads = ResourceDownload::query()->whereIn('resource_id', $catalog->pluck('id'))->whereBetween('created_at', [$start, $asOf])->get(['id', 'resource_id', 'request_id', 'contact_id', 'created_at']);
        $priorContacts = ResourceRequest::query()->whereIn('contact_id', $requests->pluck('contact_id')->filter()->unique())->where('created_at', '<', $start)->distinct()->pluck('contact_id')->flip();
        $events = $this->reports->nonBotEventQuery()->whereBetween('created_at', [$start, $asOf])->where(function ($query): void {
            $query->whereIn('event_name', ['resource_gate_viewed', 'resource_requested', 'resource_downloaded'])
                ->orWhere(fn ($pages) => $pages->where('event_name', 'page_view')->where(fn ($paths) => $paths->where('page', 'like', '%/resources/%')->orWhere('page', 'like', '%/ressources/%')->orWhere('page', 'like', '%/ressourcen/%')));
        })->get(['event_name', 'metadata', 'page', 'visitor_token', 'session_token', 'utm_source', 'utm_medium', 'utm_campaign', 'created_at']);
        $byId = $catalog->keyBy('id');
        $bySlug = $catalog->keyBy('slug');
        $eventsByResource = $events->groupBy(function ($event) use ($byId, $bySlug): int {
            $id = $event->metadata['resource_id'] ?? null;
            if ($id !== null && $byId->has($id)) {
                return (int) $id;
            }
            $slug = $event->metadata['resource_slug'] ?? null;
            if ($slug === null && preg_match('~/(?:resources|ressources|ressourcen)/([^/]+)/?$~', (string) parse_url($event->page ?? '', PHP_URL_PATH), $match)) {
                $slug = rawurldecode($match[1]);
            }

            return (int) ($bySlug->get($slug)->id ?? 0);
        });
        $rows = [];
        foreach ($catalog as $resource) {
            $resourceEvents = $eventsByResource->get($resource->id, collect());
            $leads = $requests->where('resource_id', $resource->id);
            $actions = $downloads->where('resource_id', $resource->id);
            $linked = (int) ($operational->get($resource->id)['requests_followed_by_access'] ?? 0);
            $contacts = $leads->pluck('contact_id')->filter()->unique();
            $returning = $contacts->filter(fn ($id): bool => $priorContacts->has($id))->count();
            $firstRequests = [];
            foreach ($leads->whereNotNull('visitor_token')->groupBy('visitor_token') as $identity => $visitorRequests) {
                if ($identity !== '') {
                    $firstRequests[$identity] = CarbonImmutable::instance($visitorRequests->sortBy('created_at')->first()->created_at);
                }
            }
            $countries = [];
            $sources = [];
            $days = [];
            foreach ($resourceEvents as $event) {
                $identity = $event->visitor_token;
                $this->dimension($countries, $event->metadata['detected_country_code'] ?? 'ZZ', $identity);
                $source = ($event->utm_source ?: 'Direct traffic').($event->utm_medium ? ' · '.$event->utm_medium : '').($event->utm_campaign ? ' · '.$event->utm_campaign : '');
                $this->dimension($sources, $source, $identity);
                if (in_array($event->event_name, ['page_view', 'resource_gate_viewed'], true)) {
                    $day = $event->created_at->copy()->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString();
                    $metric = $event->event_name === 'page_view' ? 'views' : 'gate_views';
                    $days[$day][$metric] = ($days[$day][$metric] ?? 0) + 1;
                }
            }
            foreach ($leads as $lead) {
                $day = $lead->created_at->copy()->setTimezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString();
                $days[$day]['requests'] = ($days[$day]['requests'] ?? 0) + 1;
            }
            $rows[] = ['id' => $resource->id, 'title' => $resource->title, 'slug' => $resource->slug, 'status' => $resource->status, 'external' => (bool) $resource->external_url,
                'views' => $resourceEvents->where('event_name', 'page_view')->count(), 'gate_views' => $resourceEvents->where('event_name', 'resource_gate_viewed')->count(),
                'requests' => $leads->count(), 'contacts' => $contacts->count(), 'new_requesters' => $contacts->count() - $returning, 'returning_requesters' => $returning,
                'download_actions' => $actions->count(), 'linked_downloads' => $linked, 'without_download' => $leads->count() - $linked,
                'later_bookings' => $this->bookingsAfter($firstRequests, $bookings),
                'download_rate' => $leads->isNotEmpty() ? $operational->get($resource->id)['conversion_rate'] : null,
                'repeat_actions' => $actions->count() - $actions->pluck('contact_id')->filter()->unique()->count(), 'countries' => $countries, 'sources' => $sources, 'days' => $days];
        }

        if ($includeHistory) {
            $rows = $this->history($rows, 'resource', ['views', 'gate_views'], $start, $asOf);
        }

        return ['rows' => collect($rows)->sortByDesc('requests')->values(), 'as_of' => $asOf];
    }

    /** @return array{id: ?int, slug: string, title: string, external: bool, status: string, opened: int, started: int, completed: int, visitors: array<string, CarbonImmutable>, attempts: array<string, array{at: CarbonImmutable, identity: string, metadata: array<string, mixed>}>, finishes: array<string, array{at: CarbonImmutable, identity: string, metadata: array<string, mixed>}>, countries: array<string, array{events: int, visitors: array<string, bool>}>, sources: array<string, array{events: int, visitors: array<string, bool>}>, days: array<string, array<string, int>>} */
    private function gameGroup(string $slug, string $title, ?int $id, bool $external, string $status): array
    {
        return ['id' => $id, 'slug' => $slug, 'title' => $title, 'external' => $external, 'status' => $status,
            'opened' => 0, 'started' => 0, 'completed' => 0, 'visitors' => [], 'attempts' => [], 'finishes' => [], 'countries' => [], 'sources' => [], 'days' => []];
    }

    /** Persist non-personal item totals before raw activity is removed. */
    public function aggregateDay(CarbonImmutable $day): void
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $day = $day->setTimezone($timezone)->startOfDay();
        $from = $day->utc();
        $through = $day->addDay()->utc()->subMicrosecond();
        foreach (['game' => ['rows' => $this->games($from, $through, includeHistory: false)['rows'], 'metrics' => ['opened', 'started', 'completed']],
            'resource' => ['rows' => $this->resources($from, $through, includeHistory: false)['rows'], 'metrics' => ['views', 'gate_views']]] as $kind => $data) {
            foreach ($data['rows'] as $row) {
                if ($row['id'] === null) {
                    continue;
                }
                foreach ($data['metrics'] as $metric) {
                    if ($row[$metric] === 0) {
                        continue;
                    }
                    $identity = ['metric_date' => $day->toDateString(), 'reporting_timezone' => $timezone, 'metric_name' => $metric, 'dimension_key' => $kind, 'dimension_value' => (string) $row['id']];
                    $stored = (int) DB::table('daily_metrics')->where($identity)->value('count');
                    if ($stored <= $row[$metric]) {
                        DB::table('daily_metrics')->updateOrInsert($identity, ['count' => $row[$metric], 'created_at' => now('UTC'), 'updated_at' => now('UTC')]);
                    }
                }
            }
        }
    }

    /** @param list<array<string, mixed>> $rows
     * @param  list<string>  $metrics
     * @return list<array<string, mixed>>
     */
    private function history(array $rows, string $kind, array $metrics, CarbonImmutable $from, CarbonImmutable $through): array
    {
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $stored = DB::table('daily_metrics')->where('reporting_timezone', $timezone)->where('dimension_key', $kind)->whereIn('metric_name', $metrics)
            ->whereBetween('metric_date', [$from->setTimezone($timezone)->toDateString(), $through->setTimezone($timezone)->toDateString()])->get()->groupBy('dimension_value');
        foreach ($rows as &$row) {
            $row['uses_durable_history'] = false;
            foreach ($stored->get((string) $row['id'], collect()) as $metric) {
                $day = CarbonImmutable::parse($metric->metric_date, $timezone);
                if ($from->gt($day->utc()) || $through->lt($day->endOfDay()->utc()->startOfSecond())) {
                    continue;
                }
                $raw = (int) ($row['days'][$metric->metric_date][$metric->metric_name] ?? 0);
                if ($metric->count > $raw) {
                    $row[$metric->metric_name] += (int) $metric->count - $raw;
                    $row['days'][$metric->metric_date][$metric->metric_name] = (int) $metric->count;
                    $row['uses_durable_history'] = true;
                }
            }
        }
        unset($row);

        return $rows;
    }

    /** @param array<string, array{events: int, visitors: array<string, bool>}> $values */
    private function dimension(array &$values, string $label, ?string $identity): void
    {
        $values[$label] ??= ['events' => 0, 'visitors' => []];
        $values[$label]['events']++;
        if ($identity) {
            $values[$label]['visitors'][$identity] = true;
        }
    }

    /** @param list<string> $identities
     * @return Collection<int|string, Collection<int, Booking>>
     */
    private function bookingEvents(CarbonImmutable $from, CarbonImmutable $through, array $identities): Collection
    {
        return $this->reports->nonBotBookingQuery(true)->whereBetween('created_at', [$from, $through])->whereIn('visitor_token', $identities)
            ->whereIn('status', ['confirmed', 'completed', 'no_show'])->get(['id', 'visitor_token', 'created_at'])->toBase()->groupBy('visitor_token');
    }

    /** @param array<string, CarbonImmutable> $firstSeen
     * @param  Collection<int|string, Collection<int, Booking>>  $bookings
     */
    private function bookingsAfter(array $firstSeen, Collection $bookings): int
    {
        $count = 0;
        foreach ($firstSeen as $identity => $at) {
            $count += $bookings->get($identity, collect())->filter(fn ($booking): bool => $booking->created_at->gte($at))->count();
        }

        return $count;
    }
}

<?php

namespace App\Console\Commands;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Booking\Models\Booking;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AggregateDailyCountryMetricsCommand extends Command
{
    protected $signature = 'analytics:aggregate-daily-country
                            {--date= : Target business calendar date in YYYY-MM-DD format (defaults to yesterday)}';

    protected $description = 'Aggregate first-party activity by detected country for one complete business calendar day';

    public function handle(): int
    {
        $dateInput = $this->option('date');
        $targetDate = $dateInput
            ? CarbonImmutable::parse((string) $dateInput, app(TimezoneService::class)->getBusinessTimezone())->toDateString()
            : CarbonImmutable::now(app(TimezoneService::class)->getBusinessTimezone())->subDay()->toDateString();

        $cairoStart = CarbonImmutable::parse($targetDate, app(TimezoneService::class)->getBusinessTimezone())->startOfDay();
        $cairoEnd = $cairoStart->addDay();
        $startUtc = $cairoStart->setTimezone('UTC');
        $endUtc = $cairoEnd->setTimezone('UTC');

        $sessions = VisitorSession::query()
            ->with('visitor:id,visitor_token,detected_country_code,is_bot')
            ->where('is_bot', false)
            ->whereHas('visitor', function ($query): void {
                $query->where('is_bot', false);
            })
            ->where('started_at', '>=', $startUtc)
            ->where('started_at', '<', $endUtc)
            ->get(['id', 'session_token', 'session_id', 'visitor_id', 'detected_country_code', 'is_bot']);

        $sessionByToken = collect();
        foreach ($sessions as $session) {
            foreach ([$session->session_token, $session->session_id] as $token) {
                if (is_string($token) && $token !== '') {
                    $sessionByToken->put($token, $session);
                }
            }
        }

        $events = AnalyticsEvent::query()
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->where('is_bot', false)
            ->get(['id', 'event_name', 'visitor_token', 'session_token', 'metadata']);

        // An event can occur in a current Cairo day while its 30-minute
        // session started shortly before midnight. Load those session
        // snapshots as well so event attribution does not silently become ZZ.
        $eventSessionTokens = $events->pluck('session_token')->filter()->unique()->values();
        if ($eventSessionTokens->isNotEmpty()) {
            $eventSessions = VisitorSession::query()
                ->with('visitor:id,visitor_token,detected_country_code,is_bot')
                ->where(function ($query) use ($eventSessionTokens): void {
                    $query->whereIn('session_token', $eventSessionTokens->all())
                        ->orWhereIn('session_id', $eventSessionTokens->all());
                })
                ->get(['id', 'session_token', 'session_id', 'visitor_id', 'detected_country_code', 'is_bot']);

            foreach ($eventSessions as $session) {
                foreach ([$session->session_token, $session->session_id] as $token) {
                    if (is_string($token) && $token !== '') {
                        $sessionByToken->put($token, $session);
                    }
                }
            }
        }

        $visitorTokens = $events->pluck('visitor_token')
            ->merge($sessions->map(fn (VisitorSession $session): ?string => $session->visitor?->visitor_token))
            ->filter()
            ->unique()
            ->values();

        $visitors = Visitor::query()
            ->whereIn('visitor_token', $visitorTokens->all())
            ->get(['id', 'visitor_token', 'detected_country_code', 'is_bot'])
            ->keyBy('visitor_token');

        $botVisitorTokens = $visitors
            ->filter(fn (Visitor $visitor): bool => $visitor->is_bot)
            ->keys()
            ->flip();

        $visitors = $visitors
            ->reject(fn (Visitor $visitor): bool => $visitor->is_bot)
            ->keyBy('visitor_token');

        $metrics = [
            'unique_visitors' => [],
            'sessions' => $sessions->groupBy(fn (VisitorSession $session): string => $this->normalizeCountry($session->detected_country_code))->map->count()->all(),
            'booking_cta_clicks' => [],
            'bookings_completed' => [],
            'resource_requests' => [],
            'page_views' => [],
        ];
        $activeVisitorCountries = [];

        foreach ($sessions as $session) {
            $visitorToken = $session->visitor?->visitor_token;
            if ($visitorToken) {
                $country = $this->normalizeCountry($session->visitor->detected_country_code);
                $activeVisitorCountries[$country][$visitorToken] = true;
            }
        }

        foreach ($events as $event) {
            if ($event->visitor_token && $botVisitorTokens->has($event->visitor_token)) {
                continue;
            }

            $linkedSession = $event->session_token ? $sessionByToken->get($event->session_token) : null;
            if ($linkedSession?->is_bot || $linkedSession?->visitor?->is_bot) {
                continue;
            }

            $eventCountry = $this->resolveEventCountry($event, $sessionByToken);
            if ($event->event_name === 'page_view') {
                $metrics['page_views'][$eventCountry] = ($metrics['page_views'][$eventCountry] ?? 0) + 1;
            }
            if ($event->event_name === 'booking_cta_clicked') {
                $metrics['booking_cta_clicks'][$eventCountry] = ($metrics['booking_cta_clicks'][$eventCountry] ?? 0) + 1;
            }
            if ($event->event_name === 'resource_requested') {
                $metrics['resource_requests'][$eventCountry] = ($metrics['resource_requests'][$eventCountry] ?? 0) + 1;
            }

            if ($event->visitor_token && $visitors->has($event->visitor_token)) {
                $visitorCountry = $this->normalizeCountry($visitors->get($event->visitor_token)->detected_country_code);
                $activeVisitorCountries[$visitorCountry][$event->visitor_token] = true;
            }
        }

        foreach ($activeVisitorCountries as $country => $tokens) {
            $metrics['unique_visitors'][$country] = count($tokens);
        }

        $bookings = Booking::query()
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->whereIn('status', ['confirmed', 'completed', 'no_show'])
            ->get(['visitor_token', 'detected_country_code']);

        $botVisitorTokens = Visitor::query()
            ->where('is_bot', true)
            ->whereIn('visitor_token', $bookings->pluck('visitor_token')->filter()->unique()->all())
            ->pluck('visitor_token')
            ->flip();

        foreach ($bookings as $booking) {
            if ($booking->visitor_token && $botVisitorTokens->has($booking->visitor_token)) {
                continue;
            }

            $country = $this->normalizeCountry($booking->detected_country_code);
            $metrics['bookings_completed'][$country] = ($metrics['bookings_completed'][$country] ?? 0) + 1;
        }

        // Bounced sessions calculation
        $bouncedSessionsByCountry = [];
        $bouncedSessions = VisitorSession::query()
            ->where('is_bot', false)
            ->whereHas('visitor', function ($query): void {
                $query->where('is_bot', false);
            })
            ->where('started_at', '>=', $startUtc)
            ->where('started_at', '<', $endUtc)
            ->whereRaw('TIMESTAMPDIFF(SECOND, started_at, last_activity_at) < 10')
            ->whereRaw('(SELECT COUNT(*) FROM analytics_events WHERE (analytics_events.session_token = visitor_sessions.session_token OR analytics_events.session_token = visitor_sessions.session_id) AND analytics_events.event_name = "page_view" AND analytics_events.is_bot = 0) = 1')
            ->whereRaw('(SELECT COUNT(*) FROM analytics_events WHERE (analytics_events.session_token = visitor_sessions.session_token OR analytics_events.session_token = visitor_sessions.session_id) AND analytics_events.event_name IN ('.implode(',', array_fill(0, count(AnalyticsService::CONVERSION_EVENTS), '?')).') AND analytics_events.is_bot = 0) = 0', AnalyticsService::CONVERSION_EVENTS)
            ->get(['id', 'detected_country_code']);

        foreach ($bouncedSessions as $bSession) {
            $country = $this->normalizeCountry($bSession->detected_country_code);
            $bouncedSessionsByCountry[$country] = ($bouncedSessionsByCountry[$country] ?? 0) + 1;
        }
        $metrics['bounced_sessions_count'] = $bouncedSessionsByCountry;

        $countries = collect($metrics)
            ->flatMap(fn (array $counts): array => array_keys($counts))
            ->push('ZZ')
            ->unique()
            ->sort()
            ->values();

        DB::transaction(function () use ($targetDate, $countries, $metrics): void {
            // Rebuild this one activity day as a deterministic set. This removes
            // stale country buckets when an operator reruns the command after
            // correcting source data, while the unique key prevents duplicates.
            DailyCountryMetric::query()->where('reporting_timezone', app(TimezoneService::class)->getBusinessTimezone())->whereDate('metric_date', $targetDate)->delete();

            foreach ($countries as $country) {
                $uniqueVisitors = (int) ($metrics['unique_visitors'][$country] ?? 0);
                $pageViews = (int) ($metrics['page_views'][$country] ?? 0);

                DailyCountryMetric::query()->create([
                    'metric_date' => $targetDate,
                    'reporting_timezone' => app(TimezoneService::class)->getBusinessTimezone(),
                    'country_code' => $country,
                    'unique_visitors' => $uniqueVisitors,
                    'sessions' => (int) ($metrics['sessions'][$country] ?? 0),
                    'booking_cta_clicks' => (int) ($metrics['booking_cta_clicks'][$country] ?? 0),
                    'bookings_completed' => (int) ($metrics['bookings_completed'][$country] ?? 0),
                    'resource_requests' => (int) ($metrics['resource_requests'][$country] ?? 0),
                    // Compatibility names used by older traffic code.
                    'visitors' => $uniqueVisitors,
                    'page_views' => $pageViews,
                    'bounced_sessions_count' => (int) ($metrics['bounced_sessions_count'][$country] ?? 0),
                ]);
            }
        });

        $this->info("Country metrics aggregated for {$targetDate}: {$countries->count()} country buckets.");

        return self::SUCCESS;
    }

    /**
     * Event country is resolved in the contract's order: server metadata,
     * then the session snapshot, then ZZ.
     *
     * @param  Collection<string, VisitorSession>  $sessions
     */
    protected function resolveEventCountry(AnalyticsEvent $event, Collection $sessions): string
    {
        $metadataCountry = is_array($event->metadata) ? ($event->metadata['detected_country_code'] ?? null) : null;
        $normalizedMetadata = strtoupper(trim((string) $metadataCountry));
        if (preg_match('/^[A-Z]{2}$/', $normalizedMetadata)
            && ! in_array($normalizedMetadata, ['XX', 'T1'], true)) {
            return $this->normalizeCountry($normalizedMetadata);
        }

        $session = $event->session_token ? $sessions->get($event->session_token) : null;

        return $this->normalizeCountry($session?->detected_country_code);
    }

    protected function normalizeCountry(?string $country, bool $allowUnknown = true): ?string
    {
        $normalized = strtoupper(trim((string) $country));
        if ($normalized === 'ZZ' && $allowUnknown) {
            return 'ZZ';
        }
        if (! preg_match('/^[A-Z]{2}$/', $normalized) || in_array($normalized, ['XX', 'T1', 'ZZ'], true)) {
            return $allowUnknown ? 'ZZ' : null;
        }

        return $normalized;
    }
}

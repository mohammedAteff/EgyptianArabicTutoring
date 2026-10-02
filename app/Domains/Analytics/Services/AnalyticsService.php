<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\DailyCountryMetric;
use App\Domains\Analytics\Models\MarketingTouch;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\CMS\Models\Setting;
use App\Domains\Reporting\Services\ReportService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AnalyticsService
{
    public const CONVERSION_EVENTS = ['booking_completed', 'resource_requested', 'resource_downloaded', 'whatsapp_clicked', 'form_submitted', 'short_form_completed', 'long_form_completed', 'package_session_scheduled', 'booking_rescheduled', 'game_completed'];

    /** @return Collection<int, \stdClass> */
    public function reportingMetrics(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $reports = app(ReportService::class);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $startUtc = CarbonImmutable::instance($start)->utc();
        $endUtc = CarbonImmutable::instance($end)->utc();
        $events = $reports->nonBotEventQuery()->whereBetween('created_at', [$startUtc, $endUtc])->get();
        $sessions = $reports->nonBotSessionQuery()->with('visitor')->whereBetween('started_at', [$startUtc, $endUtc])->get();
        $sessionKeys = $sessions->flatMap(fn ($session) => [$session->session_token, $session->session_id])->filter()->unique();
        $sessionEvents = $reports->nonBotEventQuery()->whereIn('session_token', $sessionKeys)->get();
        $rows = collect();
        $byDate = $events->groupBy(fn ($event) => $event->created_at->copy()->setTimezone($timezone)->toDateString());
        $sessionsByDate = $sessions->groupBy(fn ($session) => $session->started_at->copy()->setTimezone($timezone)->toDateString());
        foreach ($byDate->keys()->merge($sessionsByDate->keys())->unique() as $date) {
            $dayEvents = $byDate->get($date, collect());
            $daySessions = $sessionsByDate->get($date, collect());
            $counts = [];
            foreach ($dayEvents as $event) {
                $name = $event->event_name;
                $counts[$name] = ($counts[$name] ?? 0) + 1;
                $section = $event->metadata['section_id'] ?? null;
                if ($section && in_array($name, ['section_view', 'section_dwell'], true)) {
                    $metric = $name === 'section_view' ? 'section_views' : 'section_dwell_seconds';
                    $compound = $metric.'|section|'.$section;
                    $amount = $name === 'section_view' ? 1 : max(0, (float) ($event->metadata['dwell_seconds'] ?? 0));
                    $counts[$compound] = ($counts[$compound] ?? 0) + $amount;
                }
            }
            $counts['sessions'] = $daySessions->count();
            $counts['unique_visitors'] = $dayEvents->pluck('visitor_token')->merge($daySessions->map(fn ($session) => $session->visitor?->visitor_token))->filter()->unique()->count();
            $counts['bounced_sessions'] = $daySessions->filter(fn ($session): bool => $this->isBounce($session, $sessionEvents->filter(fn ($event): bool => in_array($event->session_token, [$session->session_token, $session->session_id], true))))->count();
            foreach ($counts as $compound => $count) {
                [$metric, $dimension, $value] = array_pad(explode('|', $compound, 3), 3, '');
                $key = implode('|', [$date, $metric, $dimension, $value]);
                $rows->put($key, (object) ['metric_date' => $date, 'metric_name' => $metric, 'dimension_key' => $dimension, 'dimension_value' => $value, 'count' => $count]);
            }
        }
        $rollups = DB::table('daily_metrics')->where('reporting_timezone', $timezone)
            ->whereBetween('metric_date', [$startUtc->setTimezone($timezone)->toDateString(), $endUtc->setTimezone($timezone)->toDateString()])->get();
        foreach ($rollups as $row) {
            $day = CarbonImmutable::parse($row->metric_date, $timezone);
            if ($startUtc->gt($day->utc()) || $endUtc->lt($day->endOfDay()->utc()->startOfSecond())) {
                continue;
            }
            $key = implode('|', [$row->metric_date, $row->metric_name, $row->dimension_key, $row->dimension_value]);
            if (! $rows->has($key) || (float) $row->count > (float) $rows->get($key)->count) {
                $rows->put($key, $row);
            }
        }

        return $rows->values();
    }

    private function isBounce(VisitorSession $session, Collection $events): bool
    {
        return $events->where('event_name', 'page_view')->count() === 1
            && ! $events->contains(fn ($event): bool => in_array($event->event_name, self::CONVERSION_EVENTS, true))
            && $session->started_at->diffInSeconds($session->last_activity_at, true) < 10;
    }

    /** @return Collection<int, object{unique_visitors: int, sessions: int, page_views: int, bounced_sessions_count: int, booking_cta_clicks: int, bookings_completed: int, resource_requests: int, country_code: string}&\stdClass> */
    public function reportingCountries(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $reports = app(ReportService::class);
        $timezone = app(TimezoneService::class)->getBusinessTimezone();
        $startUtc = CarbonImmutable::instance($start)->utc();
        $endUtc = CarbonImmutable::instance($end)->utc();
        $columns = ['unique_visitors', 'sessions', 'page_views', 'bounced_sessions_count', 'booking_cta_clicks', 'bookings_completed', 'resource_requests'];
        $events = $reports->nonBotEventQuery()->whereBetween('created_at', [$startUtc, $endUtc])->get();
        $sessions = $reports->nonBotSessionQuery()->with('visitor')->whereBetween('started_at', [$startUtc, $endUtc])->get();
        $sessionKeys = $sessions->flatMap(fn ($session) => [$session->session_token, $session->session_id])->filter()->unique();
        $sessionEvents = $reports->nonBotEventQuery()->whereIn('session_token', $sessionKeys)->get();
        $eventTokens = $events->pluck('session_token')->filter()->unique();
        $eventSessions = VisitorSession::query()->where(fn ($query) => $query->whereIn('session_token', $eventTokens)->orWhereIn('session_id', $eventTokens))->get()->flatMap(fn ($session) => [$session->session_token => $session, $session->session_id => $session]);
        $visitors = Visitor::query()->where('is_bot', false)->whereIn('visitor_token', $events->pluck('visitor_token')->merge($sessions->map(fn ($session) => $session->visitor?->visitor_token))->filter()->unique())->get()->keyBy('visitor_token');
        $daily = [];
        $identities = [];
        foreach ($sessions as $session) {
            $date = $session->started_at->copy()->setTimezone($timezone)->toDateString();
            $country = $this->reportCountry($session->detected_country_code);
            $daily[$date][$country]['sessions'] = ($daily[$date][$country]['sessions'] ?? 0) + 1;
            if ($this->isBounce($session, $sessionEvents->filter(fn ($event): bool => in_array($event->session_token, [$session->session_token, $session->session_id], true)))) {
                $daily[$date][$country]['bounced_sessions_count'] = ($daily[$date][$country]['bounced_sessions_count'] ?? 0) + 1;
            }
            if ($session->visitor) {
                $acquisitionCountry = $this->reportCountry($session->visitor->detected_country_code);
                $identities[$date][$acquisitionCountry][$session->visitor->visitor_token] = true;
            }
        }
        foreach ($events as $event) {
            $date = $event->created_at->copy()->setTimezone($timezone)->toDateString();
            $country = $this->reportCountry($event->metadata['detected_country_code'] ?? $eventSessions->get($event->session_token)?->detected_country_code);
            $metric = match ($event->event_name) {
                'page_view' => 'page_views', 'resource_requested' => 'resource_requests', 'booking_cta_clicked' => 'booking_cta_clicks', default => null,
            };
            if ($metric) {
                $daily[$date][$country][$metric] = ($daily[$date][$country][$metric] ?? 0) + 1;
            }
            if ($event->visitor_token) {
                $acquisitionCountry = $this->reportCountry($visitors->get($event->visitor_token)?->detected_country_code);
                $identities[$date][$acquisitionCountry][$event->visitor_token] = true;
            }
        }
        foreach ($reports->nonBotBookingQuery()->whereBetween('created_at', [$startUtc, $endUtc])->whereIn('status', ['confirmed', 'completed', 'no_show'])->get() as $booking) {
            $date = $booking->created_at->copy()->setTimezone($timezone)->toDateString();
            $country = $this->reportCountry($booking->detected_country_code);
            $daily[$date][$country]['bookings_completed'] = ($daily[$date][$country]['bookings_completed'] ?? 0) + 1;
        }
        foreach ($identities as $date => $countries) {
            foreach ($countries as $country => $tokens) {
                $daily[$date][$country]['unique_visitors'] = count($tokens);
            }
        }
        $hasPrunedVisitors = false;
        foreach (DailyCountryMetric::query()->where('reporting_timezone', $timezone)->whereBetween('metric_date', [$startUtc->setTimezone($timezone)->toDateString(), $endUtc->setTimezone($timezone)->toDateString()])->get() as $row) {
            $date = $row->metric_date->toDateString();
            $day = CarbonImmutable::parse($date, $timezone);
            if ($startUtc->gt($day->utc()) || $endUtc->lt($day->endOfDay()->utc()->startOfSecond())) {
                continue;
            }
            foreach ($columns as $column) {
                $raw = $daily[$date][$row->country_code][$column] ?? 0;
                if ($column === 'unique_visitors' && $row->{$column} > $raw) {
                    $hasPrunedVisitors = true;
                }
                $daily[$date][$row->country_code][$column] = max($raw, (int) $row->{$column});
            }
        }
        $totals = [];
        foreach ($daily as $countries) {
            foreach ($countries as $country => $counts) {
                foreach ($columns as $column) {
                    $totals[$country][$column] = ($totals[$country][$column] ?? 0) + ($counts[$column] ?? 0);
                }
            }
        }
        if (! $hasPrunedVisitors) {
            $periodIdentities = [];
            foreach ($identities as $countries) {
                foreach ($countries as $country => $tokens) {
                    $periodIdentities[$country] = ($periodIdentities[$country] ?? []) + $tokens;
                }
            }
            foreach ($totals as $country => &$counts) {
                $counts['unique_visitors'] = count($periodIdentities[$country] ?? []);
            }
            unset($counts);
        }

        return collect($totals)->map(fn (array $row, string $country): object => (object) array_merge(array_fill_keys($columns, 0), $row, ['country_code' => $country]))->sortByDesc('unique_visitors')->values();
    }

    private function reportCountry(?string $code): string
    {
        return $code && ! in_array(strtoupper($code), ['XX', 'ZZ'], true) ? strtoupper($code) : 'ZZ';
    }

    /** @return Collection<int, array{event: string, count: int, visitors: int, rate: float|int}> */
    public function goalReport(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $events = app(ReportService::class)->nonBotEventQuery()->whereBetween('created_at', [$start, $end])->where('is_bot', false);
        $audience = app(ReportService::class)->getTrafficReport($start->copy()->utc(), $end->copy()->utc())['summary']['visitors'];

        return collect((array) Setting::get('analytics.goals', []))->filter(fn ($name): bool => in_array($name, self::CONVERSION_EVENTS, true))->map(function ($name) use ($events, $audience): array {
            $goal = (clone $events)->where('event_name', $name);
            $visitors = (clone $goal)->distinct()->count('visitor_token');

            return ['event' => $name, 'count' => $goal->count(), 'visitors' => $visitors, 'rate' => $audience ? round(100 * $visitors / $audience, 1) : 0];
        })->values();
    }

    /** @return Collection<int, array{section_id: string, page_template: string, total_views: int, total_dwell_seconds: float, avg_attention_duration: float|int, entry_bounce_rate: float|null, drop_off_rate: float|null}> */
    public function sectionReport(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $templates = ['hero' => 'landing', 'pricing' => 'landing', 'curriculum' => 'landing', 'tutor-bio' => 'landing', 'blog-content' => 'blog', 'resource-preview' => 'resource', 'game-board' => 'game'];
        $metrics = $this->reportingMetrics($start, $end)->where('dimension_key', 'section')->groupBy('dimension_value');
        $events = app(ReportService::class)->nonBotEventQuery()->where('is_bot', false)->whereBetween('created_at', [CarbonImmutable::parse($start)->setTimezone('UTC'), CarbonImmutable::parse($end)->setTimezone('UTC')])->get();
        $sessions = VisitorSession::query()->whereIn('session_token', $events->pluck('session_token')->filter()->unique())->get()->keyBy('session_token');
        $bySession = $events->groupBy('session_token');

        return collect(array_unique([...array_keys($templates), ...$metrics->keys()->all()]))->map(function (string $id) use ($templates, $metrics, $events, $sessions, $bySession): array {
            $rows = $metrics->get($id, collect());
            $views = (int) $rows->where('metric_name', 'section_views')->sum('count');
            $dwell = (float) $rows->where('metric_name', 'section_dwell_seconds')->sum('count');
            $exposures = $events->where('event_name', 'section_view')->filter(fn ($event): bool => ($event->metadata['section_id'] ?? null) === $id)->pluck('session_token')->filter()->unique();
            $bounces = $exposures->filter(fn ($token): bool => $sessions->has($token) && $this->isBounce($sessions->get($token), $bySession->get($token, collect())))->count();
            $terminal = $exposures->filter(function ($token) use ($bySession, $id): bool {
                $sections = $bySession->get($token, collect())->where('event_name', 'section_view')->sortBy('created_at');

                return ($sections->last()?->metadata['section_id'] ?? null) === $id;
            })->count();

            return ['section_id' => $id, 'page_template' => $templates[$id] ?? 'general', 'total_views' => $views, 'total_dwell_seconds' => $dwell, 'avg_attention_duration' => $views ? round($dwell / $views, 1) : 0, 'entry_bounce_rate' => $exposures->count() ? round(100 * $bounces / $exposures->count(), 1) : null, 'drop_off_rate' => $exposures->count() ? round(100 * $terminal / $exposures->count(), 1) : null];
        })->values();
    }

    public function linkAuthenticatedStudent(Request $request, int $studentId): void
    {
        $token = $request->attributes->get('analytics_visitor_token');
        $sessionToken = $request->attributes->get('analytics_session_token');
        if (! is_string($token) || ! is_string($sessionToken)) {
            return;
        }
        Visitor::query()->where('visitor_token', $token)->whereNull('student_id')->whereHas('sessions', fn ($query) => $query->where('session_token', $sessionToken))->update(['student_id' => $studentId]);
    }

    public function excluded(Request $request): bool
    {
        if ($request->user('web') || $request->is('admin*', 'preview*', '*/preview', 'build/*', 'assets/*', 'up') || $request->header('X-Analytics-Synthetic') === '1') {
            return true;
        }
        $fingerprint = hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'));

        return in_array($fingerprint, (array) Setting::get('analytics.internal_hashes', []), true);
    }

    public const ALLOWED_EVENTS = [
        'page_view',
        'session_started',
        'booking_cta_clicked',
        'booking_started',
        'booking_slot_held',
        'booking_completed',
        'booking_cancelled',
        'booking_rescheduled',
        'resource_gate_viewed',
        'resource_requested',
        'resource_downloaded',
        'game_opened',
        'game_started',
        'game_completed',
        'social_link_clicked',
        'whatsapp_clicked',
        'telegram_clicked',
        'outbound_link_clicked',
        'faq_opened',
        'navigation_click',
        'section_view',
        'section_dwell',
        'session_activity',
        'form_submitted',
        'short_form_completed',
        'long_form_completed',
        'package_session_scheduled',
    ];

    public const SERVER_ONLY_EVENTS = [
        'booking_completed',
        'booking_cancelled',
        'booking_rescheduled',
        'booking_slot_held',
        'resource_requested',
        'resource_downloaded',
        'form_submitted',
        'short_form_completed',
        'long_form_completed',
        'package_session_scheduled',
    ];

    /**
     * Events for which browser retries are collapsed for five seconds.
     *
     * @var list<string>
     */
    public const RAPID_DEDUPLICATED_EVENTS = [
        'booking_cta_clicked',
        'resource_requested',
    ];

    /**
     * Events which are recorded at most once during one analytics session.
     *
     * @var list<string>
     */
    public const SESSION_DEDUPLICATED_EVENTS = [
        'session_started',
        'resource_gate_viewed',
    ];

    public const ALLOWED_CLIENT_METADATA_KEYS = [
        'page_view' => ['title', 'referrer', 'path'],
        'session_started' => [],
        'booking_cta_clicked' => ['cta_location', 'button_text', 'target', 'label'],
        'booking_started' => ['step', 'session_type_id', 'source'],
        'resource_gate_viewed' => ['resource_id', 'resource_slug', 'resource_title'],
        'game_opened' => ['game_slug', 'game_title', 'target_url'],
        'game_started' => ['game_slug', 'game_title', 'level'],
        'game_completed' => ['game_slug', 'game_title', 'score', 'total', 'duration_seconds', 'level'],
        'social_link_clicked' => ['platform', 'target_url', 'placement', 'target', 'context', 'language'],
        'whatsapp_clicked' => ['platform', 'target_url', 'placement', 'target', 'context', 'language'],
        'telegram_clicked' => ['platform', 'target_url', 'placement', 'target', 'context', 'language'],
        'outbound_link_clicked' => ['url', 'text', 'placement', 'target', 'destination'],
        'faq_opened' => ['question_id', 'question_text', 'category'],
        'navigation_click' => ['item_text', 'target_url', 'placement'],
        'section_view' => ['section_id', 'page_template', 'path'],
        'section_dwell' => ['section_id', 'page_template', 'dwell_seconds', 'path'],
    ];

    public function __construct(
        protected ?FunnelProgressionService $funnelProgressionService = null
    ) {
        $this->funnelProgressionService = $funnelProgressionService ?? app(FunnelProgressionService::class);
    }

    public function trackEvent(
        string $eventType,
        ?string $page = null,
        ?string $visitorToken = null,
        ?string $sessionToken = null,
        array $metadata = [],
        ?CarbonInterface $occurredAt = null,
        ?string $eventUuid = null
    ): ?AnalyticsEvent {
        return $this->track(
            eventName: $eventType,
            metadata: $metadata,
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            page: $page,
            occurredAt: $occurredAt,
            eventUuid: $eventUuid
        );
    }

    public function startSession(?string $visitorToken = null, ?string $countryCode = null): VisitorSession
    {
        $token = $visitorToken ?: (string) Str::uuid();

        $visitor = Visitor::where('visitor_token', $token)
            ->orWhere('visitor_id', $token)
            ->first();

        if (! $visitor) {
            $visitor = Visitor::create([
                'visitor_token' => $token,
                'visitor_id' => $token,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'detected_country_code' => $countryCode,
            ]);
        } else {
            $visitor->update(['last_seen_at' => now()]);
        }

        return VisitorSession::create([
            'session_token' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            // A session is bound to the network country observed at its start.
            // Do not backfill a later unresolved session from the visitor's
            // immutable acquisition country.
            'detected_country_code' => $countryCode,
        ]);
    }

    public function recordSession(string $visitorToken, ?string $countryCode = null): VisitorSession
    {
        return $this->startSession($visitorToken, $countryCode);
    }

    public function track(
        string $eventName,
        array $metadata = [],
        ?Request $request = null,
        ?string $visitorToken = null,
        ?string $sessionToken = null,
        ?string $page = null,
        ?CarbonInterface $occurredAt = null,
        ?string $eventUuid = null
    ): ?AnalyticsEvent {
        if (! in_array($eventName, self::ALLOWED_EVENTS, true)) {
            Log::warning("AnalyticsService: rejected unauthorized event '{$eventName}'");

            return null;
        }

        try {
            $req = $request ?? request();
            if ($req && $this->excluded($req)) {
                return null;
            }

            $sessionVisitorToken = null;
            if ($req && $req->hasSession()) {
                $sessionVisitorToken = $req->session()->get('visitor_id') ?? $req->session()->get('analytics_visitor_token');
            }

            $vToken = $sessionVisitorToken
                ?? $visitorToken
                ?? ($req ? $req->attributes->get('analytics_visitor_token') : null)
                ?? ($req ? $req->cookie('_va_visitor') : null);

            $sToken = $sessionToken
                ?? ($req ? $req->attributes->get('analytics_session_token') : null)
                ?? ($req ? $req->cookie('_va_session') : null);

            if ($sToken && in_array($eventName, self::RAPID_DEDUPLICATED_EVENTS, true)) {
                $dedupMetadata = $metadata;
                ksort($dedupMetadata);
                $dedupKey = 'va_dedup_rapid_'.$eventName.'_'.$sToken.'_'.hash('sha256', (string) json_encode($dedupMetadata));

                if (! Cache::add($dedupKey, true, now()->addSeconds(5))) {
                    return null;
                }
            }

            if ($sToken && in_array($eventName, self::SESSION_DEDUPLICATED_EVENTS, true)) {
                $dedupKey = 'va_dedup_session_'.$eventName.'_'.$sToken;

                if (! Cache::add($dedupKey, true, now()->addMinutes(30))) {
                    return null;
                }
            }

            $pageUrl = $page ?? ($req ? substr($req->fullUrl(), 0, 500) : '/');
            $referrer = $req ? substr((string) $req->header('referer', ''), 0, 500) : null;
            $isBot = $req ? (bool) ($req->attributes->get('analytics_is_bot', false)) : false;

            $visSession = null;
            if ($sToken) {
                $visSession = VisitorSession::where('session_token', $sToken)
                    ->orWhere('session_id', $sToken)
                    ->first();
            }

            if ($visSession) {
                // A session row is the server-side identity boundary. A
                // browser payload may never substitute another visitor UUID.
                $vToken = $visSession->visitor?->visitor_token;
                if ($visSession->is_bot) {
                    $isBot = true;
                }
            }

            $utmSource = $req ? $req->query('utm_source') : null;
            $utmMedium = $req ? $req->query('utm_medium') : null;
            $utmCampaign = $req ? $req->query('utm_campaign') : null;
            $utmContent = $req ? $req->query('utm_content') : null;
            $utmTerm = $req ? $req->query('utm_term') : null;

            if (! $utmSource) {
                if ($visSession && $visSession->utm_source) {
                    $utmSource = $visSession->utm_source;
                    $utmMedium = $visSession->utm_medium;
                    $utmCampaign = $visSession->utm_campaign;
                    $utmContent = $visSession->utm_content;
                    $utmTerm = $visSession->utm_term;
                } elseif ($req && $req->hasSession()) {
                    $utmSource = $req->session()->get('utm_source');
                    $utmMedium = $req->session()->get('utm_medium');
                    $utmCampaign = $req->session()->get('utm_campaign');
                    $utmContent = $req->session()->get('utm_content');
                    $utmTerm = $req->session()->get('utm_term');
                }
            }

            $ipHash = null;
            if ($req) {
                $rawIp = $req->ip() ?? '127.0.0.1';
                $appKey = (string) config('app.key', 'secret-fallback');
                $ipHash = hash('sha256', $rawIp.$appKey);
            }

            $eventTime = $occurredAt
                ?? (isset($metadata['_occurred_at']) ? CarbonImmutable::parse($metadata['_occurred_at']) : null)
                ?? (isset($metadata['occurred_at']) ? CarbonImmutable::parse($metadata['occurred_at']) : null)
                ?? now();

            // Detect server-authoritative country
            $detectedCountry = null;
            if ($req) {
                $detectedCountry = app(GeoIpService::class)->detectCountryFromRequest($req);
            }
            if (! $detectedCountry && $visSession) {
                $detectedCountry = $visSession->detected_country_code;
            }
            // Every persisted event carries a normalized snapshot. ZZ means
            // that the server could not resolve the network country; it is not
            // a caller-supplied country claim.
            $metadata['detected_country_code'] = strtoupper($detectedCountry ?: 'ZZ');

            // Enforce privacy: raw IP, client country overrides, and internal timestamps must never be stored in event metadata
            unset($metadata['ip'], $metadata['country_code'], $metadata['_occurred_at'], $metadata['occurred_at']);

            try {
                $event = AnalyticsEvent::create([
                    'event_uuid' => $eventUuid,
                    'event_name' => $eventName,
                    'visitor_token' => $vToken ? substr($vToken, 0, 64) : null,
                    'visitor_id' => $vToken ? substr($vToken, 0, 64) : null,
                    'session_token' => $sToken ? substr($sToken, 0, 64) : null,
                    'page' => $pageUrl,
                    'referrer' => $referrer ?: null,
                    'utm_source' => $utmSource ? substr($utmSource, 0, 100) : null,
                    'utm_medium' => $utmMedium ? substr($utmMedium, 0, 100) : null,
                    'utm_campaign' => $utmCampaign ? substr($utmCampaign, 0, 100) : null,
                    'utm_content' => $utmContent ? substr($utmContent, 0, 100) : null,
                    'utm_term' => $utmTerm ? substr($utmTerm, 0, 100) : null,
                    'metadata' => ! empty($metadata) ? $metadata : null,
                    'ip_hash' => $ipHash,
                    'is_bot' => $isBot,
                    'created_at' => $eventTime,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                Log::info("AnalyticsService: idempotent duplicate event suppressed for UUID {$eventUuid}");

                return $eventUuid ? AnalyticsEvent::where('event_uuid', $eventUuid)->first() : null;
            }

            if (! $isBot && $vToken) {
                try {
                    $visitor = Visitor::firstOrCreate(
                        ['visitor_token' => $vToken],
                        [
                            'first_seen_at' => $eventTime,
                            'last_seen_at' => $eventTime,
                            'is_bot' => $isBot,
                        ]
                    );
                    if ($visitor) {
                        $this->funnelProgressionService->recordVisit($visitor, $eventTime);

                        // First Non-Direct Touch acquisition attribution (Section 18)
                        // Atomic conditional update to eliminate race condition between concurrent first arrivals
                        if ($utmSource || $this->isValidExternalReferrer($referrer)) {
                            $updated = Visitor::query()
                                ->where('id', $visitor->id)
                                ->whereNull('acquisition_source')
                                ->update([
                                    'acquisition_source' => $utmSource ?: parse_url((string) $referrer, PHP_URL_HOST),
                                    'acquisition_medium' => $utmMedium,
                                    'acquisition_campaign' => $utmCampaign,
                                    'acquisition_content' => $utmContent,
                                    'acquisition_term' => $utmTerm,
                                    'acquisition_touch_at' => $eventTime,
                                ]);
                            if ($updated) {
                                $visitor->refresh();
                            }
                        }

                        // Record timestamped non-direct marketing touch (Section 18B & 19)
                        $hasArrivalUtm = $req ? $req->filled('utm_source') : ! empty($utmSource);
                        if ($hasArrivalUtm || $this->isValidExternalReferrer($referrer)) {
                            $touchSource = $req && $req->filled('utm_source') ? $req->query('utm_source') : $utmSource;
                            $touchMedium = $req && $req->filled('utm_medium') ? $req->query('utm_medium') : $utmMedium;
                            $touchCampaign = $req && $req->filled('utm_campaign') ? $req->query('utm_campaign') : $utmCampaign;
                            $touchContent = $req && $req->filled('utm_content') ? $req->query('utm_content') : $utmContent;
                            $touchTerm = $req && $req->filled('utm_term') ? $req->query('utm_term') : $utmTerm;

                            $this->recordMarketingTouch(
                                visitor: $visitor,
                                sessionToken: $sToken,
                                utmSource: $touchSource,
                                utmMedium: $touchMedium,
                                utmCampaign: $touchCampaign,
                                utmContent: $touchContent,
                                utmTerm: $touchTerm,
                                referrer: $referrer,
                                touchAt: $eventTime
                            );
                        }

                        // Milestone stages
                        $stageMap = [
                            'booking_cta_clicked' => 'booking_cta',
                            'booking_started' => 'booking_started',
                            'booking_slot_held' => 'slot_held',
                            'booking_completed' => 'booking_completed',
                        ];

                        if (isset($stageMap[$eventName])) {
                            $this->funnelProgressionService->recordStage(
                                $visitor,
                                $stageMap[$eventName],
                                $eventTime,
                                true
                            );
                        } elseif ($eventName === 'page_view' && (
                            str_contains((string) $pageUrl, '/booking') ||
                            str_contains((string) $pageUrl, '/reservation') ||
                            str_contains((string) $pageUrl, '/buchen')
                        )) {
                            // Direct visit to booking qualifies booking_started and imputes booking_cta
                            $this->funnelProgressionService->recordStage(
                                $visitor,
                                'booking_started',
                                $eventTime,
                                false
                            );
                        }
                    }
                } catch (\Throwable $fe) {
                    Log::warning('FunnelProgressionService update failed: '.$fe->getMessage());
                }
            }

            return $event;
        } catch (\Throwable $e) {
            Log::error('AnalyticsService error: '.$e->getMessage(), [
                'event' => $eventName,
                'exception' => $e,
            ]);

            return null;
        }
    }

    public function getActiveVisitorsCount(?int $windowMinutes = null): int
    {
        $window = $windowMinutes ?? (int) Setting::get('active_visitor_window', 5);
        $cutoff = CarbonImmutable::now()->subMinutes($window);

        return (int) app(ReportService::class)->nonBotEventQuery()
            ->where('created_at', '>=', $cutoff)
            ->where('is_bot', false)
            ->whereNotNull('visitor_token')
            ->distinct('visitor_token')
            ->count('visitor_token');
    }

    public function getActiveVisitorsSummary(?int $windowMinutes = null, int $limit = 15): Collection
    {
        $window = $windowMinutes ?? (int) Setting::get('active_visitor_window', 5);
        $cutoff = CarbonImmutable::now()->subMinutes($window);

        return app(ReportService::class)->nonBotEventQuery()
            ->where('created_at', '>=', $cutoff)
            ->where('is_bot', false)
            ->whereNotNull('visitor_token')
            ->select('visitor_token', 'page', 'utm_source', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('visitor_token')
            ->take($limit)
            ->map(function ($events, $token) {
                $latest = $events->first();

                return [
                    'visitor_token' => substr($token, 0, 8).'...',
                    'page' => $latest->page,
                    'page_label' => $this->friendlyPageLabel($latest->page),
                    'source' => $latest->utm_source ?: 'Direct / Organic',
                    'last_active_at' => $latest->created_at,
                    'minutes_ago' => (int) floor($latest->created_at->diffInMinutes(now(), true)),
                ];
            })
            ->values();
    }

    private function friendlyPageLabel(?string $page): string
    {
        $path = trim((string) (parse_url((string) $page, PHP_URL_PATH) ?: $page), '/');
        $path = preg_replace('#^arabictutor/?#', '', $path);

        return $path === '' ? 'Home' : ucwords(str_replace(['/', '-'], [' / ', ' '], (string) $path));
    }

    public function getPrimaryBookingFunnel(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        return $this->funnelProgressionService->getCohortFunnel($startDate, $endDate);
    }

    public function isValidExternalReferrer(?string $referrer): bool
    {
        if (! $referrer) {
            return false;
        }

        $refHost = parse_url($referrer, PHP_URL_HOST);
        if (! $refHost) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $refHost !== $appHost && ! in_array($refHost, ['localhost', '127.0.0.1'], true);
    }

    public function recordMarketingTouch(
        Visitor $visitor,
        ?string $sessionToken,
        ?string $utmSource,
        ?string $utmMedium,
        ?string $utmCampaign,
        ?string $utmContent,
        ?string $utmTerm,
        ?string $referrer,
        ?CarbonInterface $touchAt = null
    ): ?MarketingTouch {
        $source = $utmSource ?: ($this->isValidExternalReferrer($referrer) ? parse_url((string) $referrer, PHP_URL_HOST) : null);

        if (! $source && ! $utmCampaign && ! $utmContent) {
            return null;
        }

        $time = $touchAt ? CarbonImmutable::parse($touchAt) : CarbonImmutable::now();

        $normalized = [
            'visitor_id' => (string) $visitor->id,
            'session_token' => $sessionToken,
            'utm_source' => $source ? substr(trim($source), 0, 100) : null,
            'utm_medium' => $utmMedium ? substr(trim($utmMedium), 0, 100) : null,
            'utm_campaign' => $utmCampaign ? substr(trim($utmCampaign), 0, 100) : null,
            'utm_content' => $utmContent ? substr(trim($utmContent), 0, 100) : null,
            'utm_term' => $utmTerm ? substr(trim($utmTerm), 0, 100) : null,
            'referrer' => $referrer ? substr(trim((string) $referrer), 0, 500) : null,
        ];
        $dedupeHash = hash('sha256', (string) json_encode([
            ...$normalized,
            'time_bucket' => intdiv($time->timestamp, 5),
        ], JSON_UNESCAPED_SLASHES));

        // Keep a bounded fallback for rows created before the unique hash was
        // introduced. The hash is the atomic guard for concurrent requests.
        $existing = MarketingTouch::query()
            ->where('visitor_id', $visitor->id)
            ->where('utm_source', $source)
            ->where('utm_medium', $utmMedium)
            ->where('utm_campaign', $utmCampaign)
            ->where('utm_content', $utmContent)
            ->where('utm_term', $utmTerm)
            ->where('referrer', $referrer)
            ->where('session_token', $sessionToken)
            ->where(function ($query) use ($dedupeHash, $time): void {
                $query->where('dedupe_hash', $dedupeHash)
                    ->orWhere(function ($legacy) use ($time): void {
                        $legacy->whereNull('dedupe_hash')
                            ->where('touch_at', '>=', $time->subSeconds(5))
                            ->where('touch_at', '<=', $time->addSeconds(5));
                    });
            })
            ->first();

        if ($existing) {
            return $existing;
        }

        try {
            return MarketingTouch::create([
                'visitor_id' => $visitor->id,
                'visitor_token' => $visitor->visitor_token,
                'session_token' => $sessionToken,
                'utm_source' => $normalized['utm_source'],
                'utm_medium' => $normalized['utm_medium'],
                'utm_campaign' => $normalized['utm_campaign'],
                'utm_content' => $normalized['utm_content'],
                'utm_term' => $normalized['utm_term'],
                'referrer' => $normalized['referrer'],
                'is_direct' => ! $source,
                'dedupe_hash' => $dedupeHash,
                'touch_at' => $time,
            ]);
        } catch (QueryException $exception) {
            if (! in_array($exception->getCode(), ['23000', '23505'], true)) {
                throw $exception;
            }

            return MarketingTouch::where('dedupe_hash', $dedupeHash)->first();
        }
    }

    /**
     * Booking conversion attribution: Last Non-Direct Touch within exact 30-day lookback (Section 18B).
     */
    public function getBookingConversionAttribution(string $visitorToken, CarbonInterface $bookingTime): array
    {
        $bookingAt = CarbonImmutable::parse($bookingTime);
        $lookbackStart = $bookingAt->subDays(30);

        // 1. Authoritative lookup: MarketingTouch table
        $touch = MarketingTouch::query()
            ->where('visitor_token', $visitorToken)
            ->where('is_direct', false)
            ->where('touch_at', '>=', $lookbackStart)
            ->where('touch_at', '<=', $bookingAt)
            ->orderByDesc('touch_at')
            ->first();

        // 2. Fallback lookup: VisitorSession (for backwards compatibility / direct session seeding in existing tests)
        if (! $touch) {
            $touch = VisitorSession::query()
                ->whereHas('visitor', fn ($q) => $q->where('visitor_token', $visitorToken))
                ->where('started_at', '>=', $lookbackStart)
                ->where('started_at', '<=', $bookingAt)
                ->where(function ($q) {
                    $q->whereNotNull('utm_source')
                        ->orWhereNotNull('referrer');
                })
                ->orderByDesc('started_at')
                ->get()
                ->first(function ($s) {
                    return ! empty($s->utm_source) || $this->isValidExternalReferrer($s->referrer);
                });
        }

        if ($touch) {
            return [
                'utm_source' => $touch->utm_source ?: parse_url((string) $touch->referrer, PHP_URL_HOST),
                'utm_medium' => $touch->utm_medium,
                'utm_campaign' => $touch->utm_campaign,
                'utm_content' => $touch->utm_content,
                'utm_term' => $touch->utm_term,
                'referrer' => $touch->referrer,
                'touch_at' => $touch->touch_at ?? $touch->started_at,
            ];
        }

        return [
            'utm_source' => 'Direct / None',
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_content' => null,
            'utm_term' => null,
            'referrer' => null,
            'touch_at' => null,
        ];
    }

    public function getResourceFunnel(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $events = app(ReportService::class)->nonBotEventQuery()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_bot', false)
            ->select('event_name', DB::raw('count(distinct visitor_token) as total_events'))
            ->whereIn('event_name', [
                'resource_gate_viewed',
                'resource_requested',
                'resource_downloaded',
            ])
            ->groupBy('event_name')
            ->pluck('total_events', 'event_name');

        $gateViewed = $events->get('resource_gate_viewed', 0);
        $requested = $events->get('resource_requested', 0);
        $downloaded = $events->get('resource_downloaded', 0);

        return [
            'gate_viewed' => $gateViewed,
            'requested' => $requested,
            'downloaded' => $downloaded,
            'request_rate' => $gateViewed > 0 ? round(($requested / $gateViewed) * 100, 1) : 0,
            'download_rate' => $requested > 0 ? round(($downloaded / $requested) * 100, 1) : 0,
        ];
    }

    public function getGameFunnel(CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $events = app(ReportService::class)->nonBotEventQuery()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_bot', false)
            ->select('event_name', DB::raw('count(distinct visitor_token) as total_events'))
            ->whereIn('event_name', [
                'game_opened',
                'game_started',
                'game_completed',
            ])
            ->groupBy('event_name')
            ->pluck('total_events', 'event_name');

        $opened = $events->get('game_opened', 0);
        $started = $events->get('game_started', 0);
        $completed = $events->get('game_completed', 0);

        return [
            'opened' => $opened,
            'started' => $started,
            'completed' => $completed,
            'completion_rate' => $started > 0 ? round(($completed / $started) * 100, 1) : 0,
        ];
    }

    public function getAcquisitionPerformance(CarbonInterface $startDate, CarbonInterface $endDate): Collection
    {
        $reports = app(ReportService::class);
        $events = $reports->nonBotEventQuery()->whereBetween('created_at', [$startDate, $endDate])->get();
        $sessions = $reports->nonBotSessionQuery()->with('visitor')->whereBetween('started_at', [$startDate, $endDate])->get();
        $key = fn ($row): string => json_encode([$row->utm_source ?: 'direct', $row->utm_medium ?: 'none', $row->utm_campaign ?: 'none', $row->utm_content ?: 'none'], JSON_THROW_ON_ERROR);
        $eventGroups = $events->groupBy($key);
        $sessionGroups = $sessions->groupBy($key);

        return $eventGroups->keys()->merge($sessionGroups->keys())->unique()->map(function ($group) use ($eventGroups, $sessionGroups): object {
            [$source, $medium, $campaign, $content] = json_decode($group, true, flags: JSON_THROW_ON_ERROR);
            $events = $eventGroups->get($group, collect());
            $sessions = $sessionGroups->get($group, collect());

            return (object) ['source' => $source, 'medium' => $medium, 'campaign' => $campaign, 'content' => $content,
                'visitors' => $events->pluck('visitor_token')->merge($sessions->map(fn ($session) => $session->visitor?->visitor_token))->filter()->unique()->count(),
                'sessions' => $sessions->count(), 'page_views' => $events->where('event_name', 'page_view')->count(),
                'bookings' => $events->where('event_name', 'booking_completed')->count(), 'leads' => $events->where('event_name', 'resource_requested')->count()];
        })->sortByDesc('visitors')->take(50)->values();
    }

    /** @return Collection<int, array{date: string, country: string, source: string, medium: string, campaign: string, content: string, context: string, language: string, clicks: int, visitors: int}> */
    public function whatsappReport(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return app(ReportService::class)->nonBotEventQuery()->where('is_bot', false)->where('event_name', 'whatsapp_clicked')->whereBetween('created_at', [$start, $end])->get()
            ->groupBy(fn ($event) => json_encode([$event->created_at->copy()->timezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString(), $this->reportCountry($event->metadata['detected_country_code'] ?? null), $event->utm_source ?: 'direct', $event->utm_medium ?: 'none', $event->utm_campaign ?: 'none', $event->utm_content ?: 'none', $event->metadata['context'] ?? 'public', $event->metadata['language'] ?? 'unknown']))
            ->map(fn ($events): array => $this->whatsappReportRow($events))->values();
    }

    /**
     * @param  Collection<int, AnalyticsEvent>  $events
     * @return array{date: string, country: string, source: string, medium: string, campaign: string, content: string, context: string, language: string, clicks: int, visitors: int}
     */
    private function whatsappReportRow(Collection $events): array
    {
        $event = $events->firstOrFail();

        return [
            'date' => $event->created_at->copy()->timezone(app(TimezoneService::class)->getBusinessTimezone())->toDateString(),
            'country' => $this->reportCountry($event->metadata['detected_country_code'] ?? null),
            'source' => $event->utm_source ?: 'direct',
            'medium' => $event->utm_medium ?: 'none',
            'campaign' => $event->utm_campaign ?: 'none',
            'content' => $event->utm_content ?: 'none',
            'context' => match ($event->metadata['context'] ?? null) {
                'portal' => 'portal', default => 'public'
            },
            'language' => match ($event->metadata['language'] ?? null) {
                'en' => 'en', 'fr' => 'fr', 'de' => 'de', default => 'unknown'
            },
            'clicks' => $events->count(),
            'visitors' => $events->pluck('visitor_token')->filter()->unique()->count(),
        ];
    }

    /**
     * Track a visitor hit during maintenance mode.
     */
    public function trackMaintenanceVisit(Request $request): void
    {
        if ($this->excluded($request)) {
            return;
        }
        $visitorToken = $request->attributes->get('analytics_visitor_token') ?? $request->cookie('_va_visitor');
        if (! is_string($visitorToken) || ! Str::isUuid($visitorToken)) {
            $visitorToken = (string) Str::uuid();
        }
        $request->attributes->set('analytics_maintenance_visitor_token', $visitorToken);

        $countryCode = 'XX';
        try {
            $countryCode = app(GeoIpService::class)->getCountryCode($request->ip());
        } catch (\Throwable $e) {
            $countryCode = 'XX';
        }

        DB::table('maintenance_visits')->insert([
            'visitor_id' => substr($visitorToken, 0, 100),
            'ip_address' => null,
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'country_code' => $countryCode ?: 'XX',
            'url' => substr($request->fullUrl(), 0, 500),
            'referrer' => substr((string) $request->header('referer'), 0, 500) ?: null,
            'is_bounced' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

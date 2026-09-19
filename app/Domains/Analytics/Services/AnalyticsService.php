<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\MarketingTouch;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AnalyticsService
{
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
    ];

    public const SERVER_ONLY_EVENTS = [
        'booking_completed',
        'booking_cancelled',
        'booking_rescheduled',
        'booking_slot_held',
        'resource_requested',
        'resource_downloaded',
    ];

    public const DEDUPLICATED_EVENTS = [
        'booking_cta_clicked',
        'booking_started',
        'booking_slot_held',
        'booking_completed',
        'resource_requested',
        'resource_downloaded',
        'whatsapp_clicked',
        'telegram_clicked',
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
        'social_link_clicked' => ['platform', 'target_url', 'placement', 'target'],
        'whatsapp_clicked' => ['platform', 'target_url', 'placement', 'target'],
        'telegram_clicked' => ['platform', 'target_url', 'placement', 'target'],
        'outbound_link_clicked' => ['url', 'text', 'placement', 'target', 'destination'],
        'faq_opened' => ['question_id', 'question_text', 'category'],
        'navigation_click' => ['item_text', 'target_url', 'placement'],
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
        ?CarbonInterface $occurredAt = null
    ): ?AnalyticsEvent {
        return $this->track(
            eventName: $eventType,
            metadata: $metadata,
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            page: $page,
            occurredAt: $occurredAt
        );
    }

    public function track(
        string $eventName,
        array $metadata = [],
        ?Request $request = null,
        ?string $visitorToken = null,
        ?string $sessionToken = null,
        ?string $page = null,
        ?CarbonInterface $occurredAt = null
    ): ?AnalyticsEvent {
        if (! in_array($eventName, self::ALLOWED_EVENTS, true)) {
            Log::warning("AnalyticsService: rejected unauthorized event '{$eventName}'");

            return null;
        }

        try {
            $req = $request ?? request();

            $vToken = $visitorToken
                ?? ($req ? $req->attributes->get('analytics_visitor_token') : null)
                ?? ($req ? $req->cookie('_va_visitor') : null);

            $sToken = $sessionToken
                ?? ($req ? $req->attributes->get('analytics_session_token') : null)
                ?? ($req ? $req->cookie('_va_session') : null);

            if ($sToken && in_array($eventName, self::DEDUPLICATED_EVENTS, true)) {
                $dedupKey = "va_dedup_{$eventName}_{$sToken}_".md5(json_encode($metadata));
                if (Cache::has($dedupKey)) {
                    return null;
                }
                Cache::put($dedupKey, true, now()->addSeconds(5));
            }

            $pageUrl = $page ?? ($req ? substr($req->fullUrl(), 0, 500) : '/');
            $referrer = $req ? substr((string) $req->header('referer', ''), 0, 500) : null;
            $isBot = $req ? (bool) ($req->attributes->get('analytics_is_bot', false)) : false;

            $visSession = null;
            if ($sToken) {
                $visSession = VisitorSession::where('session_token', $sToken)->first();
            }

            if ($visSession) {
                if (! $vToken && $visSession->visitor) {
                    $vToken = $visSession->visitor->visitor_token;
                }
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

            // Enforce privacy: raw IP and internal timestamps must never be stored in event metadata
            unset($metadata['ip'], $metadata['_occurred_at'], $metadata['occurred_at']);

            $event = AnalyticsEvent::create([
                'event_name' => $eventName,
                'visitor_token' => $vToken ? substr($vToken, 0, 64) : null,
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

        return (int) AnalyticsEvent::query()
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

        return AnalyticsEvent::query()
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
                    'source' => $latest->utm_source ?: 'Direct / Organic',
                    'last_active_at' => $latest->created_at,
                    'minutes_ago' => $latest->created_at->diffInMinutes(now(), true),
                ];
            })
            ->values();
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

        // Rapid duplicate guard: do not create redundant duplicate touch records within 2 seconds
        $existing = MarketingTouch::query()
            ->where('visitor_id', $visitor->id)
            ->where('utm_source', $source)
            ->where('utm_campaign', $utmCampaign)
            ->where('utm_content', $utmContent)
            ->where('touch_at', '>=', $time->subSeconds(2))
            ->where('touch_at', '<=', $time->addSeconds(2))
            ->first();

        if ($existing) {
            return $existing;
        }

        return MarketingTouch::create([
            'visitor_id' => $visitor->id,
            'visitor_token' => $visitor->visitor_token,
            'session_token' => $sessionToken,
            'utm_source' => $source ? substr($source, 0, 100) : null,
            'utm_medium' => $utmMedium ? substr($utmMedium, 0, 100) : null,
            'utm_campaign' => $utmCampaign ? substr($utmCampaign, 0, 100) : null,
            'utm_content' => $utmContent ? substr($utmContent, 0, 100) : null,
            'utm_term' => $utmTerm ? substr($utmTerm, 0, 100) : null,
            'referrer' => $referrer ? substr((string) $referrer, 0, 500) : null,
            'touch_at' => $time,
        ]);
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
        $events = AnalyticsEvent::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_bot', false)
            ->select('event_name', DB::raw('count(*) as total_events'))
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
        $events = AnalyticsEvent::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_bot', false)
            ->select('event_name', DB::raw('count(*) as total_events'))
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
        return AnalyticsEvent::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_bot', false)
            ->select(
                DB::raw('COALESCE(utm_source, "direct") as source'),
                DB::raw('COALESCE(utm_medium, "none") as medium'),
                DB::raw('COALESCE(utm_campaign, "none") as campaign'),
                DB::raw('COALESCE(utm_content, "none") as content'),
                DB::raw('count(distinct visitor_token) as visitors'),
                DB::raw('count(case when event_name = "page_view" then 1 end) as page_views'),
                DB::raw('count(case when event_name = "booking_completed" then 1 end) as bookings'),
                DB::raw('count(case when event_name = "resource_requested" then 1 end) as leads')
            )
            ->groupBy('source', 'medium', 'campaign', 'content')
            ->orderByDesc('visitors')
            ->take(50)
            ->get();
    }
}

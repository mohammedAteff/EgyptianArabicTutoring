<?php

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\AnalyticsEvent;
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
        'booking_cta_clicked' => ['cta_location', 'button_text', 'target'],
        'booking_started' => ['step', 'session_type_id', 'source'],
        'resource_gate_viewed' => ['resource_id', 'resource_slug', 'resource_title'],
        'game_opened' => ['game_slug', 'game_title', 'target_url'],
        'game_started' => ['game_slug', 'game_title', 'level'],
        'game_completed' => ['game_slug', 'game_title', 'score', 'total', 'duration_seconds', 'level'],
        'social_link_clicked' => ['platform', 'target_url', 'placement'],
        'whatsapp_clicked' => ['platform', 'target_url', 'placement', 'target'],
        'telegram_clicked' => ['platform', 'target_url', 'placement', 'target'],
        'outbound_link_clicked' => ['url', 'text', 'placement'],
        'faq_opened' => ['question_id', 'question_text', 'category'],
        'navigation_click' => ['item_text', 'target_url', 'placement'],
    ];

    public function trackEvent(
        string $eventType,
        ?string $page = null,
        ?string $visitorToken = null,
        ?string $sessionToken = null,
        array $metadata = []
    ): ?AnalyticsEvent {
        return $this->track(
            eventName: $eventType,
            metadata: $metadata,
            visitorToken: $visitorToken,
            sessionToken: $sessionToken,
            page: $page
        );
    }

    public function track(
        string $eventName,
        array $metadata = [],
        ?Request $request = null,
        ?string $visitorToken = null,
        ?string $sessionToken = null,
        ?string $page = null
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

            // Enforce privacy: raw IP must never be stored in metadata
            unset($metadata['ip']);

            return AnalyticsEvent::create([
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
                'created_at' => now(),
            ]);
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
        $events = AnalyticsEvent::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('is_bot', false)
            ->select('event_name', DB::raw('count(distinct visitor_token) as unique_visitors'), DB::raw('count(*) as total_events'))
            ->whereIn('event_name', [
                'page_view',
                'booking_cta_clicked',
                'booking_started',
                'booking_slot_held',
                'booking_completed',
            ])
            ->groupBy('event_name')
            ->get()
            ->keyBy('event_name');

        $visitors = $events->get('page_view')?->unique_visitors ?? 0;
        $ctaClicked = $events->get('booking_cta_clicked')?->unique_visitors ?? 0;
        $started = $events->get('booking_started')?->unique_visitors ?? 0;
        $slotHeld = $events->get('booking_slot_held')?->unique_visitors ?? 0;
        $completed = $events->get('booking_completed')?->unique_visitors ?? 0;

        return [
            'visitors' => $visitors,
            'cta_clicked' => $ctaClicked,
            'booking_started' => $started,
            'slot_held' => $slotHeld,
            'booking_completed' => $completed,
            'cta_rate' => $visitors > 0 ? round(($ctaClicked / $visitors) * 100, 1) : 0,
            'start_rate' => $ctaClicked > 0 ? round(($started / $ctaClicked) * 100, 1) : 0,
            'hold_rate' => $started > 0 ? round(($slotHeld / $started) * 100, 1) : 0,
            'complete_rate' => $slotHeld > 0 ? round(($completed / $slotHeld) * 100, 1) : 0,
            'overall_conversion' => $visitors > 0 ? round(($completed / $visitors) * 100, 2) : 0,
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
                DB::raw('count(distinct visitor_token) as visitors'),
                DB::raw('count(case when event_name = "page_view" then 1 end) as page_views'),
                DB::raw('count(case when event_name = "booking_completed" then 1 end) as bookings'),
                DB::raw('count(case when event_name = "resource_requested" then 1 end) as leads')
            )
            ->groupBy('source', 'medium', 'campaign')
            ->orderByDesc('visitors')
            ->take(25)
            ->get();
    }
}

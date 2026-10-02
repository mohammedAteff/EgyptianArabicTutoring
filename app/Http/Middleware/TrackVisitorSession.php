<?php

namespace App\Http\Middleware;

use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Analytics\Services\GeoIpService;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitorSession
{
    public function __construct(
        protected AnalyticsService $analyticsService,
        protected GeoIpService $geoIpService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkip($request)) {
            return $next($request);
        }

        $visitorCookieName = '_va_visitor';
        $sessionCookieName = '_va_session';
        $sessionTimeoutMinutes = (int) Setting::get('session_timeout_minutes', 30);

        $newVisitorCookie = null;
        $newSessionCookie = null;

        try {
            $userAgent = (string) $request->header('User-Agent', '');
            $isBot = $this->detectBot($userAgent);

            $isSecure = app()->isProduction() || $request->isSecure();

            // 1. Identify or initialize Visitor
            $visitorToken = $request->cookie($visitorCookieName);
            if (! $visitorToken || ! Str::isUuid($visitorToken)) {
                $visitorToken = (string) Str::uuid();
                $newVisitorCookie = cookie($visitorCookieName, $visitorToken, 60 * 24 * 365, '/', null, $isSecure, false, false, 'Lax');
            }

            $deviceType = $this->detectDevice($userAgent);
            $detectedCountry = $this->geoIpService->detectCountryFromRequest($request);

            $visitor = Visitor::firstOrCreate(
                ['visitor_token' => $visitorToken],
                [
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                    'device_type' => $deviceType,
                    'user_agent' => substr($userAgent, 0, 500),
                    'is_bot' => $isBot,
                    'detected_country_code' => $detectedCountry,
                ]
            );

            $visitorUpdates = [];
            if (! $visitor->wasRecentlyCreated) {
                $visitorUpdates['last_seen_at'] = now();
            }
            if (! empty($visitorUpdates)) {
                $visitor->update($visitorUpdates);
            }

            // 2. Identify or initialize Session
            $sessionToken = $request->cookie($sessionCookieName);
            $session = null;

            if ($sessionToken) {
                $session = VisitorSession::where('session_token', $sessionToken)->first();

                // A session cookie is not an identity credential. Never let a
                // visitor reuse another visitor's session row.
                if ($session && (int) $session->visitor_id !== (int) $visitor->id) {
                    $session = null;
                }
            }

            $now = CarbonImmutable::now();
            $shouldStartNewSession = false;

            if (! $session) {
                $shouldStartNewSession = true;
            } elseif ($session->last_activity_at && $session->last_activity_at->diffInMinutes($now, true) > $sessionTimeoutMinutes) {
                $shouldStartNewSession = true;
            }

            $rawReferrer = substr((string) $request->header('referer', ''), 0, 500) ?: null;
            $hasUtm = $request->filled('utm_source');
            $hasExternalReferrer = $this->analyticsService->isValidExternalReferrer($rawReferrer);

            if ($shouldStartNewSession) {
                $sessionToken = (string) Str::uuid();
                $session = VisitorSession::create([
                    'session_token' => $sessionToken,
                    'visitor_id' => $visitor->id,
                    'started_at' => now(),
                    'last_activity_at' => now(),
                    'utm_source' => $request->query('utm_source'),
                    'utm_medium' => $request->query('utm_medium'),
                    'utm_campaign' => $request->query('utm_campaign'),
                    'utm_content' => $request->query('utm_content'),
                    'utm_term' => $request->query('utm_term'),
                    'referrer' => $rawReferrer,
                    'landing_page' => substr($request->fullUrl(), 0, 255),
                    'is_bot' => $isBot,
                    // Session geography is a start-of-session snapshot. An
                    // unresolved first request stays NULL rather than being
                    // rewritten from the visitor's acquisition country.
                    'detected_country_code' => $detectedCountry,
                ]);

                $newSessionCookie = cookie($sessionCookieName, $sessionToken, $sessionTimeoutMinutes, '/', null, $isSecure, false, false, 'Lax');
            } else {
                $updateData = ['last_activity_at' => now()];

                // When an existing session receives new marketing touch, update the active session UTMs
                if ($hasUtm || $hasExternalReferrer) {
                    $updateData['utm_source'] = $request->query('utm_source') ?: parse_url((string) $rawReferrer, PHP_URL_HOST);
                    $updateData['utm_medium'] = $request->query('utm_medium') ?: $session->utm_medium;
                    $updateData['utm_campaign'] = $request->query('utm_campaign') ?: $session->utm_campaign;
                    $updateData['utm_content'] = $request->query('utm_content') ?: $session->utm_content;
                    $updateData['utm_term'] = $request->query('utm_term') ?: $session->utm_term;
                    $updateData['referrer'] = $rawReferrer ?: $session->referrer;
                }

                $session->update($updateData);
                // Slide session expiration forward on active request
                $newSessionCookie = cookie($sessionCookieName, $sessionToken, $sessionTimeoutMinutes, '/', null, $isSecure, false, false, 'Lax');
            }

            // Persist timestamped non-direct marketing touch (Section 18B & 19)
            if (! $isBot && ($hasUtm || $hasExternalReferrer)) {
                $this->analyticsService->recordMarketingTouch(
                    visitor: $visitor,
                    sessionToken: $sessionToken,
                    utmSource: $request->query('utm_source'),
                    utmMedium: $request->query('utm_medium'),
                    utmCampaign: $request->query('utm_campaign'),
                    utmContent: $request->query('utm_content'),
                    utmTerm: $request->query('utm_term'),
                    referrer: $rawReferrer,
                    touchAt: $now
                );
            }

            $request->attributes->set('analytics_visitor_token', $visitorToken);
            $request->attributes->set('analytics_session_token', $sessionToken);
            $request->attributes->set('analytics_is_bot', $isBot);

            if ($request->hasSession()) {
                $sessionStore = $request->session();
                $sessionStore->put('analytics_visitor_token', $visitorToken);
                $sessionStore->put('analytics_session_token', $sessionToken);

                $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
                foreach ($utmParams as $param) {
                    if ($request->query($param)) {
                        $sessionStore->put($param, (string) $request->query($param));
                    } elseif (! $sessionStore->has($param) && $session && $session->{$param}) {
                        $sessionStore->put($param, (string) $session->{$param});
                    }
                }
            }

            if ($shouldStartNewSession) {
                $this->analyticsService->track(
                    eventName: 'session_started',
                    request: $request,
                    visitorToken: $visitorToken,
                    sessionToken: $sessionToken,
                );
            }

            if ($request->isMethod('GET') && ! $request->ajax() && ! $request->prefetch()) {
                $this->analyticsService->track(
                    eventName: 'page_view',
                    metadata: [],
                    request: $request,
                    visitorToken: $visitorToken,
                    sessionToken: $sessionToken
                );
            }
        } catch (\Throwable $e) {
            Log::error('TrackVisitorSession middleware error: '.$e->getMessage());
        }

        $response = $next($request);

        if ($newVisitorCookie) {
            if (method_exists($response, 'withCookie')) {
                $response->withCookie($newVisitorCookie);
            } else {
                $response->headers->setCookie($newVisitorCookie);
            }
        }
        if ($newSessionCookie) {
            if (method_exists($response, 'withCookie')) {
                $response->withCookie($newSessionCookie);
            } else {
                $response->headers->setCookie($newSessionCookie);
            }
        }

        return $response;
    }

    protected function shouldSkip(Request $request): bool
    {
        return $this->analyticsService->excluded($request)
            || $this->detectBot((string) $request->userAgent())
            || $request->is('admin*')
            || $request->is('livewire*')
            || $request->is('up')
            || $request->is('build*')
            || $request->is('assets*')
            || $request->is('favicon.ico')
            || $request->is('robots.txt');
    }

    protected function detectBot(string $userAgent): bool
    {
        if (trim($userAgent) === '' || strlen($userAgent) < 5) {
            return true;
        }

        $pattern = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegrambot|slackbot|discordbot|twitterbot|pinterest|googlebot|bingbot|yandex|baiduspider|duckduckbot|ahrefs|semrush|petalbot|bytespider|applebot|curl|wget|python-requests|headlesschrome/i';

        return (bool) preg_match($pattern, $userAgent);
    }

    protected function detectDevice(string $userAgent): string
    {
        if (preg_match('/mobile|android|iphone|ipod/i', $userAgent)) {
            return 'mobile';
        }
        if (preg_match('/tablet|ipad/i', $userAgent)) {
            return 'tablet';
        }

        return 'desktop';
    }
}

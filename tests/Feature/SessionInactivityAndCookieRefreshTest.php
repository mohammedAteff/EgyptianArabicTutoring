<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\CMS\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SessionInactivityAndCookieRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        VisitorSession::query()->delete();
        AnalyticsEvent::query()->delete();
        Visitor::query()->delete();
    }

    public function test_active_visitor_session_within_timeout_is_continued_and_slides_cookie(): void
    {
        Setting::set('session_timeout_minutes', '30', 'analytics');

        $visitorToken = (string) Str::uuid();
        $sessionToken = (string) Str::uuid();

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now()->subMinutes(25),
            'last_seen_at' => now()->subMinutes(25),
            'device_type' => 'desktop',
            'is_bot' => false,
        ]);

        $session = VisitorSession::create([
            'session_token' => $sessionToken,
            'visitor_id' => $visitor->id,
            'started_at' => now()->subMinutes(25),
            'last_activity_at' => now()->subMinutes(20), // 20 min ago (< 30 min timeout)
            'is_bot' => false,
        ]);

        $response = $this->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $sessionToken,
        ])->get('/');

        $response->assertOk();

        // Should not have created a new session
        $this->assertSame(1, VisitorSession::where('visitor_id', $visitor->id)->count());
        $refreshedSession = $session->fresh();
        $this->assertSame($sessionToken, $refreshedSession->session_token);
        $this->assertTrue($refreshedSession->last_activity_at->isAfter(now()->subMinute()));

        // Response should contain refreshed sliding _va_session cookie
        $response->assertCookie('_va_session', $sessionToken);
    }

    public function test_inactive_session_past_timeout_is_expired_and_starts_new_session(): void
    {
        Setting::set('session_timeout_minutes', '30', 'analytics');

        $visitorToken = (string) Str::uuid();
        $oldSessionToken = (string) Str::uuid();

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now()->subHours(2),
            'last_seen_at' => now()->subMinutes(35),
            'device_type' => 'desktop',
            'is_bot' => false,
        ]);

        $oldSession = VisitorSession::create([
            'session_token' => $oldSessionToken,
            'visitor_id' => $visitor->id,
            'started_at' => now()->subHours(2),
            'last_activity_at' => now()->subMinutes(35), // 35 min ago (> 30 min timeout)
            'is_bot' => false,
        ]);

        $response = $this->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $oldSessionToken,
        ])->get('/');

        $response->assertOk();

        // Should have created a new session record
        $this->assertSame(2, VisitorSession::where('visitor_id', $visitor->id)->count());
        $newSession = VisitorSession::where('visitor_id', $visitor->id)->where('session_token', '!=', $oldSessionToken)->first();
        $this->assertNotNull($newSession);
        $this->assertSame($visitor->id, $newSession->visitor_id);

        // Response should set new session cookie
        $response->assertCookie('_va_session', $newSession->session_token);
    }

    public function test_custom_session_timeout_setting_is_respected(): void
    {
        // Set timeout to 15 minutes
        Setting::set('session_timeout_minutes', '15', 'analytics');

        $visitorToken = (string) Str::uuid();
        $oldSessionToken = (string) Str::uuid();

        $visitor = Visitor::create([
            'visitor_token' => $visitorToken,
            'first_seen_at' => now()->subHour(),
            'last_seen_at' => now()->subMinutes(18),
            'device_type' => 'desktop',
            'is_bot' => false,
        ]);

        VisitorSession::create([
            'session_token' => $oldSessionToken,
            'visitor_id' => $visitor->id,
            'started_at' => now()->subHour(),
            'last_activity_at' => now()->subMinutes(18), // 18 min ago (> 15 min custom timeout)
            'is_bot' => false,
        ]);

        $response = $this->withCookies([
            '_va_visitor' => $visitorToken,
            '_va_session' => $oldSessionToken,
        ])->get('/');

        $response->assertOk();
        $this->assertSame(2, VisitorSession::where('visitor_id', $visitor->id)->count());
    }

    public function test_active_visitor_window_setting_is_used_by_analytics_service(): void
    {
        $analyticsService = app(AnalyticsService::class);

        // 1. Configure active window to 10 minutes
        Setting::set('active_visitor_window', '10', 'analytics');

        $recentVisitor = (string) Str::uuid();
        $staleVisitor = (string) Str::uuid();

        // Create event 7 minutes ago (within 10-min window, outside default 5-min window)
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => $recentVisitor,
            'session_token' => (string) Str::uuid(),
            'page' => '/',
            'is_bot' => false,
            'created_at' => CarbonImmutable::now()->subMinutes(7),
        ]);

        // Create event 15 minutes ago (outside 10-min window)
        AnalyticsEvent::create([
            'event_name' => 'page_view',
            'visitor_token' => $staleVisitor,
            'session_token' => (string) Str::uuid(),
            'page' => '/about',
            'is_bot' => false,
            'created_at' => CarbonImmutable::now()->subMinutes(15),
        ]);

        // Default call without arguments should read setting (10 minutes) -> count = 1
        $count = $analyticsService->getActiveVisitorsCount();
        $this->assertSame(1, $count);

        $summary = $analyticsService->getActiveVisitorsSummary();
        $this->assertCount(1, $summary);
        $this->assertSame('/', $summary->first()['page']);
    }
}

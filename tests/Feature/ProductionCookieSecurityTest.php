<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Analytics\Models\Visitor;
use App\Domains\Analytics\Models\VisitorSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

class ProductionCookieSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        VisitorSession::query()->delete();
        AnalyticsEvent::query()->delete();
        Visitor::query()->delete();
    }

    public function test_production_environment_sets_secure_cookies_with_lax_and_valid_lifetimes(): void
    {
        // Configure app as production
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);
        $this->assertTrue(app()->isProduction());

        $response = $this->get('/');

        $response->assertStatus(200);

        $cookies = collect($response->headers->getCookies())->keyBy(fn (Cookie $c) => $c->getName());

        $this->assertTrue($cookies->has('_va_visitor'), 'Response must set _va_visitor cookie');
        $this->assertTrue($cookies->has('_va_session'), 'Response must set _va_session cookie');

        /** @var Cookie $visitorCookie */
        $visitorCookie = $cookies->get('_va_visitor');
        $this->assertTrue($visitorCookie->isSecure(), '_va_visitor must be Secure in production');
        $this->assertEquals('lax', strtolower((string) $visitorCookie->getSameSite()), '_va_visitor must be SameSite=Lax');
        $this->assertEquals('/', $visitorCookie->getPath(), '_va_visitor path must be /');

        $response->assertCookie('_va_visitor');
        $decryptedVisitorToken = $response->getCookie('_va_visitor')?->getValue();
        $this->assertTrue(Str::isUuid($decryptedVisitorToken), '_va_visitor value must be a pseudonymous UUID without PII');
        $this->assertFalse(str_contains($decryptedVisitorToken, '@'), '_va_visitor value must not contain PII');

        // Verify ~365 days lifetime (within 5 minutes tolerance)
        $expectedVisitorExpiry = time() + (60 * 24 * 365 * 60);
        $this->assertEqualsWithDelta($expectedVisitorExpiry, $visitorCookie->getExpiresTime(), 300, '_va_visitor must have 365-day lifetime');

        /** @var Cookie $sessionCookie */
        $sessionCookie = $cookies->get('_va_session');
        $this->assertTrue($sessionCookie->isSecure(), '_va_session must be Secure in production');
        $this->assertEquals('lax', strtolower((string) $sessionCookie->getSameSite()), '_va_session must be SameSite=Lax');
        $this->assertEquals('/', $sessionCookie->getPath(), '_va_session path must be /');

        $response->assertCookie('_va_session');
        $decryptedSessionToken = $response->getCookie('_va_session')?->getValue();
        $this->assertTrue(Str::isUuid($decryptedSessionToken), '_va_session value must be a pseudonymous UUID');

        // Verify ~30 minutes lifetime (within 60 seconds tolerance)
        $expectedSessionExpiry = time() + (30 * 60);
        $this->assertEqualsWithDelta($expectedSessionExpiry, $sessionCookie->getExpiresTime(), 60, '_va_session must have 30-minute lifetime');
    }

    public function test_https_request_in_any_environment_sets_secure_cookies(): void
    {
        config(['app.env' => 'local']);
        $this->assertFalse(app()->isProduction());

        $response = $this->get('https://boltlanding.test/');

        $cookies = collect($response->headers->getCookies())->keyBy(fn (Cookie $c) => $c->getName());

        $this->assertTrue($cookies->has('_va_visitor'));
        $this->assertTrue($cookies->get('_va_visitor')->isSecure(), 'HTTPS request must set Secure cookie');
        $this->assertTrue($cookies->get('_va_session')->isSecure(), 'HTTPS request must set Secure cookie');
    }

    public function test_language_switch_retains_identical_visitor_and_session_ids_across_subpaths(): void
    {
        $this->app['env'] = 'production';
        config(['app.env' => 'production']);

        $visitorUuid = (string) Str::uuid();
        $sessionUuid = (string) Str::uuid();

        $visitor = Visitor::create([
            'visitor_token' => $visitorUuid,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'device_type' => 'desktop',
            'is_bot' => false,
        ]);

        $session = VisitorSession::create([
            'session_token' => $sessionUuid,
            'visitor_id' => $visitor->id,
            'started_at' => now(),
            'last_activity_at' => now(),
            'is_bot' => false,
        ]);

        // Initial visit to English page with cookies
        $initialResponse = $this->withCookies([
            '_va_visitor' => $visitorUuid,
            '_va_session' => $sessionUuid,
        ])->get('/');

        $initialResponse->assertStatus(200);

        // Language switch to French page (/fr) with same cookies
        $frResponse = $this->withCookies([
            '_va_visitor' => $visitorUuid,
            '_va_session' => $sessionUuid,
        ])->get('/fr');

        $frResponse->assertStatus(200);

        // Language switch to German page (/de) with same cookies
        $deResponse = $this->withCookies([
            '_va_visitor' => $visitorUuid,
            '_va_session' => $sessionUuid,
        ])->get('/de');

        $deResponse->assertStatus(200);

        // Verify refreshed session cookie still has same session token and is secure
        $deResponse->assertCookie('_va_session', $sessionUuid);

        $deCookies = collect($deResponse->headers->getCookies())->keyBy(fn (Cookie $c) => $c->getName());
        $this->assertTrue($deCookies->has('_va_session'));
        $this->assertTrue($deCookies->get('_va_session')->isSecure());

        // Database visitor record must remain single
        $this->assertDatabaseHas('visitors', [
            'visitor_token' => $visitorUuid,
        ]);
        $this->assertDatabaseCount('visitors', 1);
    }
}

<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\VideoProviderConnection;
use App\Domains\Lms\Services\BunnyStreamProvider;
use App\Exceptions\VideoProviderUnavailable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BunnyStreamProviderTest extends TestCase
{
    use RefreshDatabase;

    private const VIDEO = '657bb740-a71b-4529-a012-528021c31a92';

    private function connection(): VideoProviderConnection
    {
        return VideoProviderConnection::factory()->create(['api_key' => 'test-api-key-123456789', 'read_only_key' => 'test-callback-key-123456', 'signing_key' => 'test-signing-key-123456', 'account_key' => 'test-account-key-123456', 'allowed_domains' => ['example.test'], 'enabled' => true]);
    }

    /** @return array<string,mixed> */
    private function library(): array
    {
        return ['Id' => 123, 'PullZoneId' => 456, 'PlayerTokenAuthenticationEnabled' => true, 'BlockNoneReferrer' => true, 'EnableMP4Fallback' => false,
            'ExposeOriginals' => false, 'AllowDirectPlay' => false, 'AllowEarlyPlay' => false, 'EnableDRM' => false, 'AllowedReferrers' => ['example.test']];
    }

    /** @return array<string,mixed> */
    private function zone(): array
    {
        return ['Id' => 456, 'Enabled' => true, 'Suspended' => false, 'ZoneSecurityEnabled' => true, 'ZoneSecurityIncludeHashRemoteIP' => false,
            'ZoneSecurityKey' => 'test-signing-key-123456', 'Hostnames' => [['Value' => 'test-library.b-cdn.net', 'ForceSSL' => true]], 'BlockNoneReferrer' => true, 'AllowedReferrers' => ['example.test'], 'EdgeRules' => [],
            'EdgeScriptId' => null, 'MiddlewareScriptId' => null, 'EnableAccessControlOriginHeader' => true, 'AccessControlOriginHeaderExtensions' => ['m3u8', 'ts', 'key', 'm4s', 'mp4']];
    }

    public function test_direct_upload_authorization_is_scoped_and_keeps_all_keys_server_side(): void
    {
        $this->travelTo(now('UTC')->setTimestamp(1791374400));
        $connection = $this->connection();
        $result = app(BunnyStreamProvider::class)->uploadAuthorization($connection, self::VIDEO, 1791378000);
        $this->assertSame('https://video.bunnycdn.com/tusupload', $result['endpoint']);
        $this->assertSame('5f9656acfe433e484938a7cd1054560bf646a82976c09870ca1700c1f80e09d6', $result['signature']);
        $this->assertSame(self::VIDEO, $result['video_id']);
        $this->assertStringNotContainsString('test-api-key', json_encode($result));
        foreach (['api_key', 'read_only_key', 'signing_key', 'account_key'] as $key) {
            $this->assertArrayNotHasKey($key, $connection->toArray());
            $this->assertNotSame($connection->{$key}, $connection->getRawOriginal($key));
        }
    }

    public function test_signed_hls_directory_uses_current_hmac_algorithm_and_unix_expiry(): void
    {
        $this->travelTo(now('UTC')->setTimestamp(1791374400));
        $url = app(BunnyStreamProvider::class)->playbackUrl($this->connection(), self::VIDEO, 1791374520);
        $this->assertSame('https://test-library.b-cdn.net/bcdn_token=HS256-51MNax4vA6CrhYlAzKTddV_cQ8r1RvS1XO6cF8YmZI8&expires=1791374520&token_path=%2F657bb740-a71b-4529-a012-528021c31a92%2F/657bb740-a71b-4529-a012-528021c31a92/playlist.m3u8', $url);
        $this->assertStringNotContainsString('test-signing-key', $url);
        $this->assertStringNotContainsString('original', $url);
        $this->assertStringNotContainsString('.mp4', $url);
    }

    public function test_provider_create_and_status_use_exact_library_endpoints_without_marking_upload_ready(): void
    {
        Http::preventStrayRequests();
        $connection = $this->connection();
        Http::fake(['video.bunnycdn.com/library/123/videos' => Http::response(['guid' => self::VIDEO], 200),
            'video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response(['guid' => self::VIDEO, 'videoLibraryId' => 123, 'status' => 2, 'length' => 300, 'hasMP4Fallback' => false])]);
        $provider = app(BunnyStreamProvider::class);
        $this->assertSame(self::VIDEO, $provider->create($connection, 'LMS media'));
        $this->assertSame(['status' => 2, 'duration' => 300, 'has_mp4_fallback' => false], $provider->video($connection, self::VIDEO));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST' && $request->url() === 'https://video.bunnycdn.com/library/123/videos' && $request->hasHeader('AccessKey', 'test-api-key-123456789'));
    }

    public function test_protection_verification_checks_linked_library_zone_domain_and_key(): void
    {
        Http::preventStrayRequests();
        Http::fake(['api.bunny.net/videolibrary/123' => Http::response($this->library()), 'api.bunny.net/pullzone/456' => Http::response($this->zone())]);
        app(BunnyStreamProvider::class)->verifyProtection($this->connection(), 'example.test');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request->hasHeader('AccessKey', 'test-account-key-123456'));
    }

    public static function unsafeProviderSettings(): iterable
    {
        yield 'library embed token disabled' => ['library', 'PlayerTokenAuthenticationEnabled', false];
        yield 'library original exposed' => ['library', 'ExposeOriginals', true];
        yield 'library fallback enabled' => ['library', 'EnableMP4Fallback', true];
        yield 'library standalone play enabled' => ['library', 'AllowDirectPlay', true];
        yield 'library early play enabled' => ['library', 'AllowEarlyPlay', true];
        yield 'library unsigned referrers allowed' => ['library', 'BlockNoneReferrer', false];
        yield 'library wildcard domain' => ['library', 'AllowedReferrers', ['*']];
        yield 'unconfigured DRM' => ['library', 'EnableDRM', true];
        yield 'zone auth disabled' => ['zone', 'ZoneSecurityEnabled', false];
        yield 'zone IP hash unexpectedly required' => ['zone', 'ZoneSecurityIncludeHashRemoteIP', true];
        yield 'zone unsigned direct links allowed' => ['zone', 'BlockNoneReferrer', false];
        yield 'zone wrong signing key' => ['zone', 'ZoneSecurityKey', 'wrong-key'];
        yield 'zone unrelated host' => ['zone', 'Hostnames', [['Value' => 'foreign.b-cdn.net']]];
        yield 'zone inactive' => ['zone', 'Enabled', false];
        yield 'zone extra allowed origin' => ['zone', 'AllowedReferrers', ['example.test', 'foreign.test']];
        yield 'zone bypass rules' => ['zone', 'EdgeRules', [['Actions' => [['Type' => 8]]]]];
    }

    #[DataProvider('unsafeProviderSettings')]
    public function test_insecure_or_unverified_provider_settings_fail_closed(string $target, string $field, mixed $value): void
    {
        Http::preventStrayRequests();
        $library = $this->library();
        $zone = $this->zone();
        if ($target === 'library') {
            $library[$field] = $value;
        } else {
            $zone[$field] = $value;
        }
        Http::fake(['api.bunny.net/videolibrary/123' => Http::response($library), 'api.bunny.net/pullzone/456' => Http::response($zone)]);
        $this->expectException(VideoProviderUnavailable::class);
        app(BunnyStreamProvider::class)->verifyProtection($this->connection(), 'example.test');
    }

    public function test_missing_config_mints_no_upload_capability(): void
    {
        $connection = VideoProviderConnection::factory()->create();
        $this->expectException(VideoProviderUnavailable::class);
        app(BunnyStreamProvider::class)->uploadAuthorization($connection, self::VIDEO, now('UTC')->addHour()->timestamp);
    }

    public function test_expired_authorization_is_rejected(): void
    {
        $this->freezeTime();
        $this->expectException(VideoProviderUnavailable::class);
        app(BunnyStreamProvider::class)->playbackUrl($this->connection(), self::VIDEO, now('UTC')->timestamp);
    }

    public function test_provider_timeout_has_safe_failure_without_credentials_or_response_body(): void
    {
        Http::preventStrayRequests();
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::failedConnection()]);
        try {
            app(BunnyStreamProvider::class)->video($this->connection(), self::VIDEO);
            $this->fail('A timed-out provider must not return usable metadata.');
        } catch (VideoProviderUnavailable $exception) {
            $this->assertSame(503, $exception->getStatusCode());
            $this->assertSame('Protected video is temporarily unavailable. Please try again later.', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_provider_wrong_credentials_do_not_leak_raw_error_payload(): void
    {
        Http::preventStrayRequests();
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response(['secret' => 'LEAK ME'], 401)]);
        try {
            app(BunnyStreamProvider::class)->video($this->connection(), self::VIDEO);
            $this->fail('Wrong credentials must fail closed.');
        } catch (VideoProviderUnavailable $exception) {
            $this->assertStringNotContainsString('LEAK ME', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_idempotent_remote_delete_accepts_not_found_without_retrying_create(): void
    {
        Http::preventStrayRequests();
        Http::fake(['video.bunnycdn.com/library/123/videos/'.self::VIDEO => Http::response([], 404)]);
        app(BunnyStreamProvider::class)->delete($this->connection(), self::VIDEO);
        Http::assertSent(fn ($request): bool => $request->method() === 'DELETE');
        Http::assertSentCount(1);
    }
}

<?php

namespace App\Domains\Lms\Services;

use App\Domains\Lms\Models\VideoProviderConnection;
use App\Exceptions\VideoProviderUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class BunnyStreamProvider implements VideoDeliveryProvider
{
    public function create(VideoProviderConnection $connection, string $title): string
    {
        $this->configured($connection);
        $result = $this->request($connection, 'POST', $this->libraryPath($connection).'/videos', ['title' => $title]);
        $id = $result->json('guid');
        if (! is_string($id) || ! $this->videoId($id)) {
            throw new VideoProviderUnavailable;
        }

        return strtolower($id);
    }

    /** @return array{status:int,duration:int,has_mp4_fallback:bool} */
    public function video(VideoProviderConnection $connection, string $videoId): array
    {
        $this->configured($connection);
        if (! $this->videoId($videoId)) {
            throw new VideoProviderUnavailable;
        }
        $data = $this->request($connection, 'GET', $this->libraryPath($connection).'/videos/'.$videoId)->json();
        if (! is_array($data) || ($data['guid'] ?? null) !== $videoId || (int) ($data['videoLibraryId'] ?? 0) !== (int) $connection->library_id
            || ! is_int($data['status'] ?? null) || ! in_array($data['status'], range(0, 8), true)
            || ! is_int($data['length'] ?? null) || $data['length'] < 0 || $data['length'] > 86400
            || ! is_bool($data['hasMP4Fallback'] ?? null)) {
            throw new VideoProviderUnavailable;
        }

        return ['status' => $data['status'], 'duration' => $data['length'], 'has_mp4_fallback' => $data['hasMP4Fallback']];
    }

    public function delete(VideoProviderConnection $connection, string $videoId): void
    {
        $this->configured($connection);
        if (! $this->videoId($videoId)) {
            throw new VideoProviderUnavailable;
        }
        $this->request($connection, 'DELETE', $this->libraryPath($connection).'/videos/'.$videoId, [], true);
    }

    public function verifyProtection(VideoProviderConnection $connection, string $domain): void
    {
        $this->configured($connection, true);
        if (! in_array($domain, $connection->allowed_domains ?? [], true)) {
            throw new VideoProviderUnavailable;
        }
        $library = $this->request($connection, 'GET', '/videolibrary/'.$connection->library_id, [], false, true)->json();
        if (! is_array($library) || (int) ($library['Id'] ?? 0) !== (int) $connection->library_id
            || ($library['PlayerTokenAuthenticationEnabled'] ?? null) !== true || ($library['BlockNoneReferrer'] ?? null) !== true
            || ($library['EnableMP4Fallback'] ?? null) !== false || ($library['ExposeOriginals'] ?? null) !== false
            || ($library['AllowDirectPlay'] ?? null) !== false || ($library['AllowEarlyPlay'] ?? null) !== false
            || ($library['EnableDRM'] ?? null) !== false
            || ! $this->domains($library['AllowedReferrers'] ?? null, $connection->allowed_domains ?? [], $domain)
            || ! is_int($library['PullZoneId'] ?? null) || $library['PullZoneId'] < 1) {
            throw new VideoProviderUnavailable;
        }
        $zone = $this->request($connection, 'GET', '/pullzone/'.$library['PullZoneId'], [], false, true)->json();
        $hostnames = is_array($zone) && is_array($zone['Hostnames'] ?? null) ? array_column($zone['Hostnames'], 'Value') : [];
        $secureHost = is_array($zone) && is_array($zone['Hostnames'] ?? null)
            && collect($zone['Hostnames'])->contains(fn ($host): bool => is_array($host) && ($host['Value'] ?? null) === $connection->cdn_hostname && ($host['ForceSSL'] ?? null) === true);
        $cors = is_array($zone) ? ($zone['AccessControlOriginHeaderExtensions'] ?? null) : null;
        if (! is_array($zone) || (int) ($zone['Id'] ?? 0) !== $library['PullZoneId']
            || ($zone['Enabled'] ?? null) !== true || ($zone['Suspended'] ?? null) !== false
            || ($zone['ZoneSecurityEnabled'] ?? null) !== true || ($zone['ZoneSecurityIncludeHashRemoteIP'] ?? null) !== false
            || ! is_string($zone['ZoneSecurityKey'] ?? null) || ! hash_equals($connection->signing_key, $zone['ZoneSecurityKey'])
            || ! in_array($connection->cdn_hostname, $hostnames, true) || ! $secureHost
            || ($zone['BlockNoneReferrer'] ?? null) !== true
            || ! $this->domains($zone['AllowedReferrers'] ?? null, $connection->allowed_domains ?? [], $domain)
            || ! is_array($zone['EdgeRules'] ?? null) || $zone['EdgeRules'] !== []
            || ! array_key_exists('EdgeScriptId', $zone) || ! in_array($zone['EdgeScriptId'], [null, 0], true)
            || ! array_key_exists('MiddlewareScriptId', $zone) || ! in_array($zone['MiddlewareScriptId'], [null, 0], true)
            || ($zone['EnableAccessControlOriginHeader'] ?? null) !== true || ! is_array($cors)
            || (! in_array('*', $cors, true) && array_diff(['m3u8', 'ts', 'key', 'm4s', 'mp4'], $cors) !== [])) {
            throw new VideoProviderUnavailable;
        }
    }

    /** @return array<string,string|int> */
    public function uploadAuthorization(VideoProviderConnection $connection, string $videoId, int $expires): array
    {
        $this->configured($connection);
        $this->authorizationTarget($videoId, $expires);

        return ['endpoint' => 'https://video.bunnycdn.com/tusupload', 'video_id' => $videoId, 'library_id' => (int) $connection->library_id, 'expires' => $expires,
            'signature' => hash('sha256', $connection->library_id.$connection->api_key.$expires.$videoId)];
    }

    public function playbackUrl(VideoProviderConnection $connection, string $videoId, int $expires): string
    {
        $this->configured($connection, true);
        $this->authorizationTarget($videoId, $expires);
        $directory = '/'.$videoId.'/';
        $signingData = 'token_path='.$directory;
        $hash = hash_hmac('sha256', $directory.$expires.$signingData, $connection->signing_key, true);
        $token = 'HS256-'.rtrim(strtr(base64_encode($hash), '+/', '-_'), '=');

        return 'https://'.$connection->cdn_hostname.'/bcdn_token='.$token.'&expires='.$expires.'&token_path='.rawurlencode($directory).$directory.'playlist.m3u8';
    }

    /** @param list<string> $approved */
    private function domains(mixed $actual, array $approved, string $domain): bool
    {
        return is_array($actual) && in_array($domain, $actual, true)
            && array_diff($actual, $approved) === [];
    }

    private function configured(VideoProviderConnection $connection, bool $playback = false): void
    {
        if ($connection->provider !== 'bunny' || ! $connection->enabled || ! $connection->library_id || ! $connection->api_key
            || ($playback && (! $connection->account_key || ! $connection->signing_key
                || ! preg_match('/^[a-z0-9-]+\\.b-cdn\\.net$/D', $connection->cdn_hostname ?? '')))) {
            throw new VideoProviderUnavailable;
        }
    }

    private function videoId(string $id): bool
    {
        return (bool) preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D', $id);
    }

    private function authorizationTarget(string $videoId, int $expires): void
    {
        if (! $this->videoId($videoId) || $expires <= now('UTC')->timestamp || $expires > now('UTC')->addDay()->timestamp) {
            throw new VideoProviderUnavailable;
        }
    }

    private function libraryPath(VideoProviderConnection $connection): string
    {
        return '/library/'.$connection->library_id;
    }

    /** Provider bodies/headers can contain keys; do not throw or log a raw HTTP exception.
     * @param  array<string,mixed>  $body
     */
    private function request(VideoProviderConnection $connection, string $method, string $path, array $body = [], bool $missingIsSuccess = false, bool $account = false): Response
    {
        try {
            $client = Http::baseUrl($account ? 'https://api.bunny.net' : 'https://video.bunnycdn.com')
                ->acceptJson()->withHeaders(['AccessKey' => $account ? $connection->account_key : $connection->api_key])
                ->connectTimeout(2)->timeout(5)->withoutRedirecting();
            $response = match ($method) {
                'POST' => $client->post($path, $body),
                'DELETE' => $client->delete($path),
                default => $client->get($path),
            };
        } catch (ConnectionException) {
            throw new VideoProviderUnavailable;
        }
        if (! $response->successful() && ! ($missingIsSuccess && $response->notFound())) {
            throw new VideoProviderUnavailable;
        }

        return $response;
    }
}

<?php

namespace App\Domains\Analytics\Services;

use GeoIp2\Database\Reader;
use Illuminate\Http\Request;
use Throwable;

class GeoIpService
{
    /**
     * Standard Cloudflare IP ranges (IPv4 and IPv6).
     */
    public const DEFAULT_CLOUDFLARE_PROXIES = [
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    ];

    /**
     * Detect 2-letter ISO country code from HTTP request.
     * Validates trusted proxy headers before falling back to GeoLite2 lookup.
     */
    public function detectCountryFromRequest(Request $request): ?string
    {
        $remoteAddr = (string) $request->server('REMOTE_ADDR', '');

        // Only evaluate Cloudflare / CDN proxy headers if the connecting client is an authentic trusted proxy
        if ($remoteAddr !== '' && $this->isTrustedProxy($remoteAddr)) {
            $headerCountry = $request->header('CF-IPCountry') ?: $request->header('X-Country-Code');
            $sanitizedHeader = $this->cleanIsoCode($headerCountry);
            if ($sanitizedHeader !== null) {
                return $sanitizedHeader;
            }
        }

        // Untrusted proxy or direct client: ignore forwarded headers. When the
        // edge is trusted, resolve the original client address from the
        // validated proxy headers before GeoLite2 lookup.
        $clientIp = $this->resolveClientIp($request, $remoteAddr);

        return $this->getCountryCode($clientIp);
    }

    protected function resolveClientIp(Request $request, string $remoteAddr): ?string
    {
        if ($remoteAddr !== '' && $this->isTrustedProxy($remoteAddr)) {
            $forwarded = $request->header('CF-Connecting-IP') ?: $request->header('X-Forwarded-For');

            foreach (preg_split('/\s*,\s*/', (string) $forwarded, -1, PREG_SPLIT_NO_EMPTY) as $candidate) {
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }

        $candidate = $remoteAddr !== '' ? $remoteAddr : $request->ip();

        return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : null;
    }

    /**
     * Resolve 2-letter ISO country code from an IP address using GeoLite2 database.
     * Returns null on missing database, invalid/private IP, or unresolvable country.
     */
    public function getCountryCode(?string $ip): ?string
    {
        if (! $ip || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }

        $dbPath = (string) config('services.geoip.database_path', storage_path('geoip/GeoLite2-Country.mmdb'));
        if (! file_exists($dbPath) || ! is_readable($dbPath)) {
            return null;
        }

        try {
            $reader = new Reader($dbPath);
            $record = $reader->country($ip);
            $isoCode = $record->country->isoCode;

            return $this->cleanIsoCode($isoCode);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Check if a given remote IP belongs to a configured or default trusted proxy CIDR range.
     */
    public function isTrustedProxy(string $ip): bool
    {
        if ($ip === '') {
            return false;
        }

        $configured = config('services.geoip.trusted_proxies');
        if (is_array($configured)) {
            $proxies = $configured;
        } elseif (is_string($configured) && trim($configured) !== '') {
            $proxies = array_filter(array_map('trim', explode(',', $configured)));
        } else {
            // A proxy must be explicitly configured per deployment. Never
            // trust a forwarded country header merely because it looks like a
            // Cloudflare request.
            $proxies = [];
        }

        foreach ($proxies as $range) {
            if ($this->ipMatches($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Test whether an IP matches a single IP or CIDR block (IPv4 or IPv6).
     */
    public function ipMatches(string $ip, string $cidr): bool
    {
        if ($ip === $cidr) {
            return true;
        }

        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $mask] = explode('/', $cidr, 2);
        $maskInt = (int) $mask;

        // IPv4 CIDR matching
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if ($maskInt < 0 || $maskInt > 32) {
                return false;
            }
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $netmask = $maskInt === 0 ? 0 : (~((1 << (32 - $maskInt)) - 1));

            return ($ipLong & $netmask) === ($subnetLong & $netmask);
        }

        // IPv6 CIDR matching
        if (filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            if ($maskInt < 0 || $maskInt > 128) {
                return false;
            }
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            if ($ipBin === false || $subnetBin === false) {
                return false;
            }

            $bytes = intdiv($maskInt, 8);
            $remainderBits = $maskInt % 8;

            if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
                return false;
            }

            if ($remainderBits > 0) {
                $maskByte = chr((0xFF << (8 - $remainderBits)) & 0xFF);

                return ($ipBin[$bytes] & $maskByte) === ($subnetBin[$bytes] & $maskByte);
            }

            return true;
        }

        return false;
    }

    /**
     * Validate and normalize a 2-letter ISO 3166-1 alpha-2 country code.
     */
    public function cleanIsoCode(?string $code): ?string
    {
        if (! $code) {
            return null;
        }

        $cleaned = strtoupper(trim($code));

        if (! preg_match('/^[A-Z]{2}$/', $cleaned)) {
            return null;
        }

        // Disallow synthetic/internal codes like XX (unknown) or T1 (Tor)
        if (in_array($cleaned, ['XX', 'T1'], true)) {
            return null;
        }

        return $cleaned;
    }
}

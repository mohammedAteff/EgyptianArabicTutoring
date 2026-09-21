<?php

namespace App\Domains\Timezone\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Throwable;

class TimezoneDisplayService
{
    /**
     * Curated city display names for common zones.
     */
    protected const CITY_MAP = [
        'UTC' => 'UTC',
        'Africa/Cairo' => 'Cairo',
        'Europe/Berlin' => 'Berlin',
        'Europe/London' => 'London',
        'Europe/Paris' => 'Paris',
        'Europe/Rome' => 'Rome',
        'Europe/Madrid' => 'Madrid',
        'Europe/Amsterdam' => 'Amsterdam',
        'Europe/Vienna' => 'Vienna',
        'Europe/Brussels' => 'Brussels',
        'Europe/Zurich' => 'Zurich',
        'America/New_York' => 'New York',
        'America/Chicago' => 'Chicago',
        'America/Denver' => 'Denver',
        'America/Los_Angeles' => 'Los Angeles',
        'America/Toronto' => 'Toronto',
        'America/Vancouver' => 'Vancouver',
        'America/Indiana/Indianapolis' => 'Indianapolis',
        'America/Argentina/Buenos_Aires' => 'Buenos Aires',
        'America/Sao_Paulo' => 'São Paulo',
        'Asia/Dubai' => 'Dubai',
        'Asia/Riyadh' => 'Riyadh',
        'Asia/Kuwait' => 'Kuwait City',
        'Asia/Tokyo' => 'Tokyo',
        'Asia/Singapore' => 'Singapore',
        'Australia/Sydney' => 'Sydney',
        'Australia/Melbourne' => 'Melbourne',
    ];

    /**
     * Fallback IANA to ISO-3166-1 alpha-2 country codes.
     */
    protected const TIMEZONE_COUNTRY_MAP = [
        'Africa/Cairo' => 'EG',
        'Europe/Berlin' => 'DE',
        'Europe/London' => 'GB',
        'Europe/Paris' => 'FR',
        'Europe/Rome' => 'IT',
        'Europe/Madrid' => 'ES',
        'Europe/Amsterdam' => 'NL',
        'Europe/Vienna' => 'AT',
        'Europe/Brussels' => 'BE',
        'Europe/Zurich' => 'CH',
        'America/New_York' => 'US',
        'America/Chicago' => 'US',
        'America/Denver' => 'US',
        'America/Los_Angeles' => 'US',
        'America/Toronto' => 'CA',
        'America/Vancouver' => 'CA',
        'America/Indiana/Indianapolis' => 'US',
        'America/Argentina/Buenos_Aires' => 'AR',
        'America/Sao_Paulo' => 'BR',
        'Asia/Dubai' => 'AE',
        'Asia/Riyadh' => 'SA',
        'Asia/Kuwait' => 'KW',
        'Asia/Tokyo' => 'JP',
        'Asia/Singapore' => 'SG',
        'Australia/Sydney' => 'AU',
        'Australia/Melbourne' => 'AU',
    ];

    /**
     * Format a slot for display using reference-instant timezone calculation.
     * Supports both signature orders:
     * - formatSlotForDisplay(string $timezone, CarbonInterface|DateTimeInterface $instant = null)
     * - formatSlotForDisplay(CarbonInterface|DateTimeInterface $instant, string $timezone)
     *
     * @return array{
     *     utc_offset: string,
     *     timezone_country_code: ?string,
     *     timezone_country_name: string,
     *     flag_asset: string,
     *     city: string,
     *     label: string,
     *     formatted_time?: string
     * }
     */
    public function formatSlotForDisplay(mixed $arg1, mixed $arg2 = null): array
    {
        $timezone = 'UTC';
        $instant = null;

        if (is_string($arg1) && ! ($arg2 instanceof DateTimeInterface)) {
            $timezone = $arg1;
            $instant = $arg2 ? Carbon::parse($arg2, 'UTC') : Carbon::now('UTC');
        } elseif (is_string($arg1) && $arg2 instanceof DateTimeInterface) {
            $timezone = $arg1;
            $instant = $arg2;
        } elseif ($arg1 instanceof DateTimeInterface && is_string($arg2)) {
            $instant = $arg1;
            $timezone = $arg2;
        } elseif (is_string($arg2)) {
            $timezone = $arg2;
            $instant = Carbon::now('UTC');
        } else {
            $instant = Carbon::now('UTC');
        }

        $canonicalTz = $this->canonicalizeTimezone($timezone);
        $offsetFormatted = $this->formatUtcOffset($canonicalTz, $instant);
        $countryCode = $this->resolveCountryCode($canonicalTz);
        $countryName = $this->resolveCountryName($countryCode);
        $city = $this->resolveCityName($canonicalTz);

        $candidateFlag = $countryCode
            ? public_path('assets/flags/4x3/'.strtolower($countryCode).'.svg')
            : null;
        $flagAsset = $candidateFlag && is_file($candidateFlag)
            ? '/assets/flags/4x3/'.strtolower($countryCode).'.svg'
            : '/assets/flags/4x3/globe.svg';

        $label = $canonicalTz === 'UTC'
            ? 'UTC (Coordinated Universal Time)'
            : "{$city} ({$canonicalTz}, {$offsetFormatted})";

        return [
            'utc_offset' => $offsetFormatted,
            'timezone_country_code' => $countryCode,
            'timezone_country_name' => $countryName,
            'flag_asset' => $flagAsset,
            'city' => $city,
            'label' => $label,
        ];
    }

    /**
     * Compute the formatted UTC offset (e.g. UTC, UTC+1, UTC+2, UTC-4, UTC+5:30)
     * at the exact scheduled instant of the slot.
     */
    public function formatUtcOffset(string $timezone, DateTimeInterface $instant): string
    {
        if ($timezone === 'UTC' || $timezone === 'GMT' || $timezone === 'Etc/UTC') {
            return 'UTC';
        }

        try {
            $dt = DateTimeImmutable::createFromInterface($instant)->setTimezone(new DateTimeZone('UTC'));
            $tz = new DateTimeZone($timezone);
            $offsetSeconds = $tz->getOffset($dt);
        } catch (Throwable) {
            return 'UTC';
        }

        if ($offsetSeconds === 0) {
            return 'UTC';
        }

        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $abs = abs($offsetSeconds);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);

        if ($minutes === 0) {
            return "UTC{$sign}{$hours}";
        }

        return sprintf('UTC%s%d:%02d', $sign, $hours, $minutes);
    }

    /**
     * Resolve the canonical ISO-3166-1 alpha-2 country code associated with an IANA timezone.
     */
    public function resolveCountryCode(string $timezone): ?string
    {
        if (in_array($timezone, ['UTC', 'GMT', 'Etc/UTC'], true)) {
            return null;
        }

        if (isset(self::TIMEZONE_COUNTRY_MAP[$timezone])) {
            return self::TIMEZONE_COUNTRY_MAP[$timezone];
        }

        try {
            $tz = new DateTimeZone($timezone);
            $loc = $tz->getLocation();
            if ($loc && ! empty($loc['country_code']) && preg_match('/^[A-Z]{2}$/i', (string) $loc['country_code'])) {
                return strtoupper((string) $loc['country_code']);
            }
        } catch (Throwable) {
        }

        return null;
    }

    /**
     * Resolve an accessible English country name from an ISO country code.
     */
    public function resolveCountryName(?string $countryCode): string
    {
        if (! $countryCode) {
            return 'Unknown / Unresolved';
        }

        $name = \Locale::getDisplayRegion('und-'.strtoupper($countryCode), 'en');

        return $name !== '' ? $name : strtoupper($countryCode);
    }

    /**
     * Resolve human-friendly city name from timezone.
     */
    public function resolveCityName(string $timezone): string
    {
        if (isset(self::CITY_MAP[$timezone])) {
            return self::CITY_MAP[$timezone];
        }

        if (in_array($timezone, ['UTC', 'GMT', 'Etc/UTC'], true)) {
            return 'UTC';
        }

        $parts = explode('/', $timezone);
        $last = end($parts);

        return str_replace('_', ' ', $last);
    }

    /**
     * Normalize timezone string.
     */
    protected function canonicalizeTimezone(string $timezone): string
    {
        $trimmed = trim($timezone);
        if ($trimmed === '' || in_array(strtoupper($trimmed), ['UTC', 'GMT', 'ETC/UTC'], true)) {
            return 'UTC';
        }

        try {
            $tz = new DateTimeZone($trimmed);

            return $tz->getName();
        } catch (Throwable) {
            return 'UTC';
        }
    }
}

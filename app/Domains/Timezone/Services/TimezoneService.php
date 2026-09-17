<?php

namespace App\Domains\Timezone\Services;

use App\Domains\Booking\Exceptions\InvalidTimezoneException;
use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Exceptions\DstFoldAmbiguityException;
use App\Domains\Timezone\Exceptions\DstGapException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeImmutable;
use DateTimeZone;

class TimezoneService
{
    /**
     * Get the configured business timezone identifier.
     */
    public function getBusinessTimezone(): string
    {
        $tz = Setting::get('business_timezone', 'Africa/Cairo');

        return $this->isValid($tz) ? $tz : 'Africa/Cairo';
    }

    /**
     * Check if a timezone identifier is a valid IANA timezone.
     */
    public function isValid(?string $timezone): bool
    {
        if (empty($timezone)) {
            return false;
        }

        return in_array($timezone, DateTimeZone::listIdentifiers(DateTimeZone::ALL), true);
    }

    /**
     * Assert that a timezone identifier is valid, throwing an exception otherwise.
     *
     * @throws InvalidTimezoneException
     */
    public function validate(string $timezone): string
    {
        if (! $this->isValid($timezone)) {
            throw new InvalidTimezoneException($timezone);
        }

        return $timezone;
    }

    /**
     * Get a curated, searchable list of IANA timezones with friendly display labels and current UTC offsets.
     *
     * @return array<int, array{id: string, label: string, offset: string}>
     */
    public function getAvailableTimezones(): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);
        $timezones = [];

        foreach ($identifiers as $id) {
            $tz = new DateTimeZone($id);
            $offsetSeconds = $tz->getOffset($now);
            $hours = intdiv($offsetSeconds, 3600);
            $minutes = abs(intdiv($offsetSeconds % 3600, 60));
            $offsetString = sprintf('UTC%+03d:%02d', $hours, $minutes);

            $city = str_replace('_', ' ', basename($id));
            $region = dirname($id);
            $cleanLabel = ($region !== '.') ? "{$city} ({$id} - {$offsetString})" : "{$id} ({$offsetString})";

            $timezones[] = [
                'id' => $id,
                'label' => $cleanLabel,
                'offset' => $offsetString,
                'offset_seconds' => $offsetSeconds,
            ];
        }

        // Sort by offset, then by ID
        usort($timezones, function ($a, $b) {
            if ($a['offset_seconds'] === $b['offset_seconds']) {
                return strcmp($a['id'], $b['id']);
            }

            return $a['offset_seconds'] <=> $b['offset_seconds'];
        });

        return array_map(fn ($item) => [
            'id' => $item['id'],
            'label' => $item['label'],
            'offset' => $item['offset'],
        ], $timezones);
    }

    /**
     * Alias for getAvailableTimezones to provide curated timezone list.
     *
     * @return array<int, array{id: string, label: string, offset: string}>
     */
    public function getCuratedList(): array
    {
        return $this->getAvailableTimezones();
    }

    /**
     * Deterministically resolve a local wall-clock date and time string to a canonical UTC CarbonImmutable instant.
     *
     * - Detects and rejects nonexistent local times during DST spring-forward gaps (throws DstGapException).
     * - Detects and deterministically resolves repeated local times during DST fall-back folds according to $foldPolicy ('first', 'second', or 'reject').
     *
     * @param  string  $localDateTime  e.g. "2026-04-24 00:30:00" or "2026-04-24 00:30"
     * @param  string  $timezone  IANA timezone identifier
     * @param  string  $foldPolicy  'first' (earlier UTC instant / summer time), 'second' (later UTC instant / standard time), or 'reject'
     *
     * @throws DstGapException
     * @throws DstFoldAmbiguityException
     * @throws InvalidTimezoneException
     */
    public function resolveLocalWallTime(
        string $localDateTime,
        string $timezone,
        string $foldPolicy = 'first'
    ): CarbonImmutable {
        $tz = $this->validate($timezone);
        $dtZone = new DateTimeZone($tz);

        $clean = trim($localDateTime);

        // If string contains explicit offset or Zulu indicator, it is an absolute instant rather than local wall-time
        if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $clean)) {
            return CarbonImmutable::parse($clean)->setTimezone('UTC');
        }

        $parsed = CarbonImmutable::parse($clean);
        $expectedFormat = $parsed->format('Y-m-d H:i:s');

        // Check transitions around this day (+/- 2 days)
        $approxTs = strtotime($expectedFormat.' UTC');
        $transitions = $dtZone->getTransitions($approxTs - 172800, $approxTs + 172800);

        $offsets = [];
        if (! empty($transitions)) {
            foreach ($transitions as $tr) {
                $offsets[$tr['offset']] = true;
            }
        }

        // Also add probe offset
        $probe = CarbonImmutable::parse($clean, $dtZone);
        $offsets[$probe->getOffset()] = true;

        $validInstants = [];
        foreach (array_keys($offsets) as $offsetSeconds) {
            $dtUtc = CarbonImmutable::parse($expectedFormat.' UTC')->subSeconds($offsetSeconds);
            if ($dtUtc->setTimezone($dtZone)->format('Y-m-d H:i:s') === $expectedFormat) {
                $validInstants[$dtUtc->getTimestamp()] = $dtUtc;
            }
        }

        ksort($validInstants);

        if (empty($validInstants)) {
            throw new DstGapException($expectedFormat, $tz);
        }

        if (count($validInstants) > 1) {
            if ($foldPolicy === 'reject') {
                throw new DstFoldAmbiguityException($expectedFormat, $tz);
            }

            return ($foldPolicy === 'second') ? end($validInstants) : reset($validInstants);
        }

        return reset($validInstants);
    }

    /**
     * Check if a given local wall-clock time string represents an ambiguous time (falls in a DST fold).
     */
    public function isAmbiguousLocalTime(string $localDateTime, string $timezone): bool
    {
        try {
            $tz = $this->validate($timezone);
            $dtZone = new DateTimeZone($tz);
            $clean = trim($localDateTime);

            if (preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $clean)) {
                return false;
            }

            $parsed = CarbonImmutable::parse($clean);
            $expectedFormat = $parsed->format('Y-m-d H:i:s');
            $approxTs = strtotime($expectedFormat.' UTC');
            $transitions = $dtZone->getTransitions($approxTs - 172800, $approxTs + 172800);

            $offsets = [];
            if (! empty($transitions)) {
                foreach ($transitions as $tr) {
                    $offsets[$tr['offset']] = true;
                }
            }
            $probe = CarbonImmutable::parse($clean, $dtZone);
            $offsets[$probe->getOffset()] = true;

            $matchCount = 0;
            foreach (array_keys($offsets) as $offsetSeconds) {
                $dtUtc = CarbonImmutable::parse($expectedFormat.' UTC')->subSeconds($offsetSeconds);
                if ($dtUtc->setTimezone($dtZone)->format('Y-m-d H:i:s') === $expectedFormat) {
                    $matchCount++;
                }
            }

            return $matchCount > 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Check if a given local wall-clock time string represents a nonexistent time (falls in a DST gap).
     */
    public function isNonexistentLocalTime(string $localDateTime, string $timezone): bool
    {
        try {
            $this->resolveLocalWallTime($localDateTime, $timezone);

            return false;
        } catch (DstGapException) {
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Convert any DateTime to UTC CarbonImmutable.
     */
    public function toUtc(DateTimeImmutable|CarbonInterface|string $dateTime, ?string $sourceTimezone = null): CarbonImmutable
    {
        if ($dateTime instanceof CarbonImmutable) {
            return $dateTime->setTimezone('UTC');
        }

        if ($dateTime instanceof DateTimeImmutable) {
            return CarbonImmutable::instance($dateTime)->setTimezone('UTC');
        }

        if ($dateTime instanceof CarbonInterface) {
            return CarbonImmutable::instance($dateTime)->setTimezone('UTC');
        }

        $sourceTz = $sourceTimezone ? $this->validate($sourceTimezone) : 'UTC';

        if ($sourceTimezone && strtoupper($sourceTz) !== 'UTC') {
            return $this->resolveLocalWallTime((string) $dateTime, $sourceTz);
        }

        return CarbonImmutable::parse($dateTime, $sourceTz)->setTimezone('UTC');
    }

    /**
     * Convert UTC instant to target timezone.
     */
    public function toLocal(DateTimeImmutable|CarbonInterface|string $utcDateTime, string $targetTimezone): CarbonImmutable
    {
        $targetTz = $this->validate($targetTimezone);
        $utc = $this->toUtc($utcDateTime);

        return $utc->setTimezone($targetTz);
    }

    /**
     * Get the formatted UTC offset string (e.g. "+03:00", "-04:00") for a given instant and timezone.
     */
    public function getOffsetString(DateTimeImmutable|CarbonInterface $utcInstant, string $timezone): string
    {
        $local = $this->toLocal($utcInstant, $timezone);

        return $local->format('P');
    }

    /**
     * Generate the complete historical timezone snapshot array for a booking.
     *
     * @return array<string, mixed>
     */
    public function createBookingSnapshot(
        DateTimeImmutable|CarbonInterface $startUtc,
        DateTimeImmutable|CarbonInterface $endUtc,
        ?string $customerTimezone = null,
        ?string $businessTimezone = null
    ): array {
        $bizTz = $businessTimezone ? $this->validate($businessTimezone) : $this->getBusinessTimezone();
        $custTz = $customerTimezone ? $this->validate($customerTimezone) : $bizTz;

        $startUtcCarbon = $this->toUtc($startUtc);
        $endUtcCarbon = $this->toUtc($endUtc);

        $bizStart = $startUtcCarbon->setTimezone($bizTz);
        $bizEnd = $endUtcCarbon->setTimezone($bizTz);

        $custStart = $startUtcCarbon->setTimezone($custTz);
        $custEnd = $endUtcCarbon->setTimezone($custTz);

        return [
            'start_at_utc' => $startUtcCarbon->toDateTimeString(),
            'end_at_utc' => $endUtcCarbon->toDateTimeString(),
            'business_timezone' => $bizTz,
            'customer_timezone' => $custTz,
            'business_local_date_at_booking' => $bizStart->toDateString(),
            'business_local_start_time_at_booking' => $bizStart->toTimeString(),
            'business_local_end_time_at_booking' => $bizEnd->toTimeString(),
            'customer_local_date_at_booking' => $custStart->toDateString(),
            'customer_local_start_time_at_booking' => $custStart->toTimeString(),
            'customer_local_end_time_at_booking' => $custEnd->toTimeString(),
            'business_utc_offset_at_booking' => $bizStart->format('P'),
            'customer_utc_offset_at_booking' => $custStart->format('P'),
        ];
    }
}

<?php

namespace App\Domains\Reporting\Services;

use Carbon\CarbonImmutable;

final class EstimatedActivityDefinition
{
    public const VERSION = 'estimated-activity-v1';

    public const WINDOW_MS = 15000;

    public const MAX_GAP_MS = 20000;

    public const STUDENT_GRACE_MS = 120000;

    public const ADMIN_GRACE_MS = 90000;

    public const MAX_PAYLOAD_BYTES = 512;

    public const MAX_STREAMS = 6;

    public const ANCHORS_PER_MINUTE = 12;

    public const CHECKPOINTS_PER_MINUTE = 36;

    public const MAX_RUN_SECONDS = 86400;

    public static function stamp(CarbonImmutable $time): string
    {
        return $time->utc()->format('Y-m-d H:i:s.u');
    }

    public static function fingerprint(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public static function micros(CarbonImmutable|string $value): int
    {
        $time = is_string($value) ? new \DateTimeImmutable($value, new \DateTimeZone('UTC')) : $value;

        return $time->getTimestamp() * 1000000 + (int) $time->format('u');
    }

    public static function instant(int $micros): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC(intdiv($micros, 1000000))->addMicroseconds($micros % 1000000);
    }

    public static function boundary(int $micros): string
    {
        $seconds = intdiv($micros, 1000000);
        $fraction = $micros % 1000000;

        return gmdate('Y-m-d H:i:s', $seconds).($fraction === 0 ? '' : sprintf('.%06d', $fraction));
    }

    /** Deterministically apportion whole seconds without moving interval boundaries or filling gaps. */
    public static function seconds(int $micros, int &$remainder): int
    {
        $total = $micros + $remainder;
        $remainder = $total % 1000000;

        return intdiv($total, 1000000);
    }
}

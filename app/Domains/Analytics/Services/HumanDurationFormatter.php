<?php

namespace App\Domains\Analytics\Services;

class HumanDurationFormatter
{
    public static function minutes(int $totalMinutes): string
    {
        $totalMinutes = max(0, $totalMinutes);
        $units = [
            'day' => intdiv($totalMinutes, 1440),
            'hour' => intdiv($totalMinutes % 1440, 60),
            'minute' => $totalMinutes % 60,
        ];
        $parts = [];
        foreach ($units as $unit => $count) {
            if ($count > 0) {
                $parts[] = $count.' '.$unit.($count === 1 ? '' : 's');
            }
        }

        return $parts === [] ? '0 minutes' : implode(', ', $parts);
    }
}

<?php

namespace App\Domains\Analytics\Services;

class HumanDurationFormatter
{
    public static function minutes(int $totalMinutes, string $locale = 'en'): string
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
                $parts[] = $locale === 'en'
                    ? $count.' '.$unit.($count === 1 ? '' : 's')
                    : trans_choice(':count '.$unit.'|:count '.$unit.'s', $count, ['count' => $count], $locale);
            }
        }

        return $parts === [] ? ($locale === 'en' ? '0 minutes' : trans_choice(':count minute|:count minutes', 0, ['count' => 0], $locale)) : implode(', ', $parts);
    }
}

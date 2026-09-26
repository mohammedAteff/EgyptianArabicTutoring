<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\Promotion;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PromotionService
{
    public const CACHE_PREFIX = 'marketing:active_promo';

    /**
     * Retrieve the currently active promotion, prioritizing display type if given.
     * Caches with a dynamic TTL bounded by the next state transition (max 300s).
     */
    public function getActivePromotion(?string $displayType = null): ?Promotion
    {
        $cacheKey = $displayType ? self::CACHE_PREFIX.'_'.$displayType : self::CACHE_PREFIX;

        // Determine nearest state transition in seconds to bound TTL
        $secondsUntilTransition = $this->getSecondsUntilNextTransition($displayType);
        $ttl = $secondsUntilTransition !== null ? min(300, max(1, $secondsUntilTransition)) : 300;

        return Cache::remember($cacheKey, $ttl, function () use ($displayType) {
            $now = Carbon::now('UTC');

            $query = Promotion::query()
                ->where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                });

            if ($displayType) {
                $query->where('display_type', $displayType);
            }

            return $query->latest('id')->first();
        });
    }

    /**
     * Compute seconds until the nearest promotion transition (starts_at or ends_at) relative to UTC now.
     */
    public function getSecondsUntilNextTransition(?string $displayType = null): ?int
    {
        $now = Carbon::now('UTC');

        // Look for the next starts_at in the future for active items
        $nextStartQuery = DB::table('promotions')
            ->where('is_active', true)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>', $now);

        if ($displayType) {
            $nextStartQuery->where('display_type', $displayType);
        }

        $nextStart = $nextStartQuery->orderBy('starts_at', 'asc')->value('starts_at');

        // Look for the next ends_at in the future for active items that have already started
        $nextEndQuery = DB::table('promotions')
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->whereNotNull('ends_at')
            ->where('ends_at', '>', $now);

        if ($displayType) {
            $nextEndQuery->where('display_type', $displayType);
        }

        $nextEnd = $nextEndQuery->orderBy('ends_at', 'asc')->value('ends_at');

        $timestamps = [];
        if ($nextStart) {
            $timestamps[] = Carbon::parse($nextStart, 'UTC')->diffInSeconds($now);
        }
        if ($nextEnd) {
            $timestamps[] = Carbon::parse($nextEnd, 'UTC')->diffInSeconds($now);
        }

        if (empty($timestamps)) {
            return null;
        }

        return (int) min($timestamps);
    }

    /**
     * Clear all cached active promotions.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX);
        foreach (['top_bar', 'floating_modal', 'inline_card'] as $type) {
            Cache::forget(self::CACHE_PREFIX.'_'.$type);
        }
    }

    /**
     * Convert datetime string from Cairo local business time to UTC instant.
     */
    public function cairoToUtc(?string $cairoDateTime): ?Carbon
    {
        if (! $cairoDateTime) {
            return null;
        }

        return Carbon::parse($cairoDateTime, 'Africa/Cairo')->setTimezone('UTC');
    }

    /**
     * Convert UTC instant to Cairo local business time.
     */
    public function utcToCairo($utcDateTime): ?Carbon
    {
        if (! $utcDateTime) {
            return null;
        }

        if (! ($utcDateTime instanceof Carbon || $utcDateTime instanceof CarbonImmutable)) {
            $utcDateTime = Carbon::parse($utcDateTime, 'UTC');
        }

        return $utcDateTime->copy()->setTimezone('Africa/Cairo');
    }
}

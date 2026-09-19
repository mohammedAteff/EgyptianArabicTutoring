<?php

namespace App\Domains\Booking\Services;

use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingHoldService
{
    public function __construct(
        protected TimezoneService $timezoneService,
        protected AvailabilityService $availabilityService,
        protected AnalyticsService $analyticsService
    ) {}

    /**
     * Acquire a temporary hold on a booking slot.
     *
     * @throws SlotUnavailableException
     */
    public function acquireHold(
        string $visitorToken,
        ?string $sessionToken,
        SessionType $sessionType,
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        ?int $durationMinutes = null,
        ?string $analyticsVisitorToken = null,
        ?string $analyticsSessionToken = null
    ): BookingHold {
        $holdDuration = $durationMinutes ?? (int) Setting::get('booking_hold_duration_minutes', 10);
        $start = $this->timezoneService->toUtc($startUtc);
        $end = $this->timezoneService->toUtc($endUtc);
        $now = CarbonImmutable::now('UTC');

        $aVisitor = $analyticsVisitorToken ?? (session()->isStarted() ? session('analytics_visitor_token') : null) ?? request()->cookie('_va_visitor') ?? $visitorToken;
        $aSession = $analyticsSessionToken ?? (session()->isStarted() ? session('analytics_session_token') : null) ?? request()->cookie('_va_session') ?? $sessionToken;

        return DB::transaction(function () use ($visitorToken, $sessionToken, $sessionType, $start, $end, $holdDuration, $now, $aVisitor, $aSession) {
            // 1. Resolve canonical slot configuration first
            $config = $this->availabilityService->resolveSlotConfiguration(
                sessionType: $sessionType,
                startUtc: $start,
                endUtc: $end
            );

            // 2. Acquire deterministic calendar row locks for buffer-expanded dates
            $this->availabilityService->acquireCalendarDateLocks(
                startUtc: $start,
                endUtc: $config['end_utc'],
                bufferMinutes: $config['buffer_minutes']
            );

            // 3. Authoritative slot validation
            $this->availabilityService->validateSlotForBooking(
                sessionType: $sessionType,
                startUtc: $start,
                endUtc: $config['end_utc'],
                currentVisitorToken: $visitorToken,
                resolvedConfig: $config
            );

            // 4. Release any previous active holds for THIS visitor
            BookingHold::query()
                ->where('visitor_token', $visitorToken)
                ->where('status', 'active')
                ->update([
                    'status' => 'released',
                    'released_at' => $now,
                ]);

            // 5. Generate unguessable hold token
            $holdToken = Str::random(64);

            // 6. Create and return new hold with canonical end time
            $hold = BookingHold::create([
                'visitor_token' => $visitorToken,
                'session_token' => $sessionToken,
                'hold_token' => $holdToken,
                'session_type_id' => $sessionType->id,
                'slot_start_utc' => $start->toDateTimeString(),
                'slot_end_utc' => $config['end_utc']->toDateTimeString(),
                'expires_at' => $now->addMinutes($holdDuration)->toDateTimeString(),
                'status' => 'active',
            ]);

            // 6. Track server-side analytics event post-commit
            DB::afterCommit(function () use ($aVisitor, $aSession, $hold, $sessionType, $start, $end) {
                $this->analyticsService->trackEvent(
                    eventType: 'booking_slot_held',
                    page: '/booking',
                    visitorToken: $aVisitor,
                    sessionToken: $aSession,
                    metadata: [
                        'hold_id' => $hold->id,
                        'session_type_id' => $sessionType->id,
                        'slot_start_utc' => $start->toDateTimeString(),
                        'slot_end_utc' => $end->toDateTimeString(),
                    ]
                );
            });

            return $hold;
        }, 5);
    }

    /**
     * Release any active holds for a visitor.
     */
    public function releaseVisitorHolds(string $visitorToken): int
    {
        return BookingHold::query()
            ->where('visitor_token', $visitorToken)
            ->where('status', 'active')
            ->update([
                'status' => 'released',
                'released_at' => now(),
            ]);
    }

    /**
     * Clean up expired holds.
     */
    public function cleanExpiredHolds(): int
    {
        return BookingHold::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => 'expired',
            ]);
    }

    /**
     * Verify if a visitor has a valid active hold on a given slot.
     */
    public function hasActiveHold(string $visitorToken, CarbonInterface $startUtc, CarbonInterface $endUtc): bool
    {
        $start = $this->timezoneService->toUtc($startUtc);
        $end = $this->timezoneService->toUtc($endUtc);

        return BookingHold::query()
            ->where('visitor_token', $visitorToken)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->where('slot_start_utc', $start->toDateTimeString())
            ->where('slot_end_utc', $end->toDateTimeString())
            ->exists();
    }
}

<?php

namespace App\Domains\Availability\Services;

use App\Domains\Availability\Models\AvailabilityException;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\CMS\Models\Setting;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AvailabilityService
{
    public function __construct(
        protected TimezoneService $timezoneService
    ) {}

    /**
     * Authoritatively compute available booking slots grouped by customer local date.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getAvailableSlotsGroupedByDate(
        SessionType $sessionType,
        string $customerTimezone,
        ?CarbonInterface $fromDate = null,
        ?CarbonInterface $toDate = null,
        ?string $currentVisitorToken = null
    ): array {
        $customerTz = $this->timezoneService->validate($customerTimezone);
        $businessTz = $this->timezoneService->getBusinessTimezone();

        $nowUtc = CarbonImmutable::now('UTC');
        $nowBusiness = $nowUtc->setTimezone($businessTz);

        // Window determination
        $defaultMinNoticeHours = (int) Setting::get('booking_min_notice_hours', 12);
        $defaultBufferMinutes = (int) Setting::get('booking_buffer_minutes', 15);
        $defaultMaxHorizonDays = (int) Setting::get('booking_max_horizon_days', 60);

        // Fetch enough surrounding holds to evaluate every rule's buffer at
        // the candidate-slot level. An exact-overlap query would miss a hold
        // that ends immediately before a slot but still violates its buffer.
        $maximumBufferMinutes = max(
            $defaultBufferMinutes,
            (int) (AvailabilityRule::query()->where('enabled', true)->max('buffer_minutes') ?? 0)
        );

        $ruleMaxHorizon = AvailabilityRule::query()->where('enabled', true)->whereNotNull('max_horizon_days')->max('max_horizon_days');
        $searchHorizonDays = max($defaultMaxHorizonDays, (int) $ruleMaxHorizon);

        $startSearchBusiness = $fromDate
            ? CarbonImmutable::instance($fromDate)->setTimezone($businessTz)->startOfDay()
            : $nowBusiness->startOfDay();

        $endSearchBusiness = $toDate
            ? CarbonImmutable::instance($toDate)->setTimezone($businessTz)->endOfDay()
            : $nowBusiness->addDays($searchHorizonDays)->endOfDay();

        $startSearchUtc = $startSearchBusiness->setTimezone('UTC');
        $endSearchUtc = $endSearchBusiness->setTimezone('UTC');

        // Fetch active bookings in window
        $activeBookings = Booking::query()
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereNull('cancelled_at')
            ->where('end_at_utc', '>', $startSearchUtc)
            ->where('start_at_utc', '<', $endSearchUtc)
            ->get(['id', 'start_at_utc', 'end_at_utc']);

        // Fetch active unexpired holds for other visitors
        $activeHoldsQuery = BookingHold::query()
            ->where('status', 'active')
            ->where('expires_at', '>', $nowUtc)
            ->where('slot_end_utc', '>', $startSearchUtc->subMinutes($maximumBufferMinutes))
            ->where('slot_start_utc', '<', $endSearchUtc->addMinutes($maximumBufferMinutes));

        if ($currentVisitorToken) {
            $activeHoldsQuery->where('visitor_token', '!=', $currentVisitorToken);
        }

        $activeHolds = $activeHoldsQuery->get(['id', 'slot_start_utc', 'slot_end_utc']);

        // Fetch exceptions in date range
        $exceptions = AvailabilityException::query()
            ->whereBetween('date', [$startSearchBusiness->toDateString(), $endSearchBusiness->toDateString()])
            ->get()
            ->keyBy(fn ($e) => $e->date->toDateString());

        // Fetch recurring weekly rules
        $rulesByWeekday = AvailabilityRule::query()
            ->where('enabled', true)
            ->get()
            ->groupBy('weekday');

        $groupedSlots = [];

        $currentDay = $startSearchBusiness;
        while ($currentDay <= $endSearchBusiness) {
            $dateString = $currentDay->toDateString();
            $weekday = $currentDay->dayOfWeek; // 0=Sunday, 1=Monday...

            /** @var AvailabilityException|null $exception */
            $exception = $exceptions->get($dateString);

            // 1. If date is blocked, skip
            if ($exception && $exception->isBlocked()) {
                $currentDay = $currentDay->addDay();

                continue;
            }

            // 2. Determine daily time intervals
            $intervals = [];
            if ($exception && $exception->isSpecialHours() && $exception->start_time && $exception->end_time) {
                $baseRule = $rulesByWeekday->has($weekday) ? $rulesByWeekday->get($weekday)->first() : null;
                $intervals[] = [
                    'start' => $exception->start_time,
                    'end' => $exception->end_time,
                    'duration' => $baseRule?->session_duration_minutes ?? $sessionType->duration_minutes,
                    'buffer' => $baseRule?->buffer_minutes ?? $defaultBufferMinutes,
                    'min_notice' => $baseRule?->min_notice_hours ?? $defaultMinNoticeHours,
                    'max_horizon' => $baseRule?->max_horizon_days ?? $defaultMaxHorizonDays,
                ];
            } elseif ($rulesByWeekday->has($weekday)) {
                foreach ($rulesByWeekday->get($weekday) as $rule) {
                    $intervals[] = [
                        'start' => $rule->start_time,
                        'end' => $rule->end_time,
                        'duration' => $rule->session_duration_minutes ?? $sessionType->duration_minutes,
                        'buffer' => $rule->buffer_minutes ?? $defaultBufferMinutes,
                        'min_notice' => $rule->min_notice_hours ?? $defaultMinNoticeHours,
                        'max_horizon' => $rule->max_horizon_days ?? $defaultMaxHorizonDays,
                    ];
                }
            }

            // 3. Generate candidate slots for each interval
            foreach ($intervals as $interval) {
                $slotDurationMinutes = (int) $interval['duration'];
                $bufferMinutes = (int) $interval['buffer'];
                $step = $slotDurationMinutes + $bufferMinutes;
                $minNotice = (int) $interval['min_notice'];
                $maxHorizon = (int) $interval['max_horizon'];

                // Special-hours exceptions with nonexistent local times must be rejected/skipped without silent rounding
                if ($this->timezoneService->isNonexistentLocalTime("{$dateString} {$interval['start']}", $businessTz)) {
                    if ($exception && $exception->isSpecialHours()) {
                        continue;
                    }
                }

                $startParts = explode(':', $interval['start']);
                $intervalStartMinutes = ((int) $startParts[0]) * 60 + ((int) $startParts[1]);
                $endParts = explode(':', $interval['end']);
                $intervalEndMinutes = ((int) $endParts[0]) * 60 + ((int) $endParts[1]);

                for ($currMin = $intervalStartMinutes; ($currMin + $slotDurationMinutes) <= $intervalEndMinutes; $currMin += $step) {
                    $slotHour = intdiv($currMin, 60);
                    $slotMinute = $currMin % 60;
                    $slotTimeString = sprintf('%02d:%02d:00', $slotHour, $slotMinute);
                    $localDateTimeString = "{$dateString} {$slotTimeString}";

                    // Check if candidate slot falls into a DST gap (nonexistent local wall time)
                    if ($this->timezoneService->isNonexistentLocalTime($localDateTimeString, $businessTz)) {
                        continue; // Explicitly skip nonexistent wall times without silent rounding!
                    }

                    // Resolve deterministic canonical UTC start instant using configured fold policy ('first')
                    $slotStartUtc = $this->timezoneService->resolveLocalWallTime($localDateTimeString, $businessTz, 'first');

                    // Compute slot end
                    $endMin = $currMin + $slotDurationMinutes;
                    $endHour = intdiv($endMin, 60);
                    $endMinute = $endMin % 60;
                    $endLocalString = sprintf('%02d:%02d:00', $endHour, $endMinute);
                    $endDateTimeString = "{$dateString} {$endLocalString}";

                    if ($this->timezoneService->isNonexistentLocalTime($endDateTimeString, $businessTz)) {
                        continue;
                    }

                    $slotEndUtc = $this->timezoneService->resolveLocalWallTime($endDateTimeString, $businessTz, 'first');

                    // Check minimum notice and maximum horizon
                    if ($slotStartUtc < $nowUtc->addHours($minNotice) || $slotStartUtc > $nowUtc->addDays($maxHorizon)->endOfDay()) {
                        continue;
                    }

                    // Check booking collision (including surrounding buffer)
                    $hasBookingCollision = $activeBookings->contains(function ($b) use ($slotStartUtc, $slotEndUtc, $bufferMinutes) {
                        $bStart = CarbonImmutable::instance($b->start_at_utc);
                        $bEnd = CarbonImmutable::instance($b->end_at_utc);

                        return $slotStartUtc < $bEnd->addMinutes($bufferMinutes) && $slotEndUtc > $bStart->subMinutes($bufferMinutes);
                    });

                    if ($hasBookingCollision) {
                        continue;
                    }

                    // Check hold collision
                    $hasHoldCollision = $activeHolds->contains(function ($h) use ($slotStartUtc, $slotEndUtc, $bufferMinutes) {
                        $holdStart = CarbonImmutable::instance($h->slot_start_utc);
                        $holdEnd = CarbonImmutable::instance($h->slot_end_utc);

                        return $holdStart < $slotEndUtc->addMinutes($bufferMinutes)
                            && $holdEnd > $slotStartUtc->subMinutes($bufferMinutes);
                    });

                    if ($hasHoldCollision) {
                        continue;
                    }

                    // Slot is valid! Project to customer timezone
                    $customerStart = $slotStartUtc->setTimezone($customerTz);
                    $customerEnd = $slotEndUtc->setTimezone($customerTz);
                    $customerDate = $customerStart->toDateString();

                    $businessStart = $slotStartUtc->setTimezone($businessTz);
                    $businessEnd = $slotEndUtc->setTimezone($businessTz);

                    // Disambiguate if ambiguous in customer timezone during DST fold
                    $isAmbiguous = $this->timezoneService->isAmbiguousLocalTime($customerStart->format('Y-m-d H:i:s'), $customerTz);
                    $customerFormatted = $isAmbiguous
                        ? $customerStart->format('g:i A').' ('.$customerStart->format('T').')'
                        : $customerStart->format('g:i A');

                    $slotData = [
                        'slot_start_utc' => $slotStartUtc->toDateTimeString(),
                        'slot_end_utc' => $slotEndUtc->toDateTimeString(),
                        'customer_start_time' => $customerStart->format('H:i'),
                        'customer_end_time' => $customerEnd->format('H:i'),
                        'customer_formatted' => $customerFormatted,
                        'customer_formatted_end' => $customerEnd->format('g:i A'),
                        'customer_date' => $customerDate,
                        'customer_timezone' => $customerTz,
                        'business_start_time' => $businessStart->format('H:i'),
                        'business_end_time' => $businessEnd->format('H:i'),
                        'business_date' => $dateString,
                        'business_timezone' => $businessTz,
                        'duration_minutes' => $slotDurationMinutes,
                    ];

                    $groupedSlots[$customerDate][] = $slotData;
                }
            }

            $currentDay = $currentDay->addDay();
        }

        // Sort dates and slots
        ksort($groupedSlots);
        foreach ($groupedSlots as &$slots) {
            usort($slots, fn ($a, $b) => strcmp($a['slot_start_utc'], $b['slot_start_utc']));
        }

        return $groupedSlots;
    }

    /**
     * Authoritatively check if a specific UTC slot is currently available.
     */
    public function isSlotAvailable(
        SessionType $sessionType,
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        ?string $currentVisitorToken = null
    ): bool {
        try {
            $this->validateSlotForBooking(
                sessionType: $sessionType,
                startUtc: $startUtc,
                endUtc: $endUtc,
                currentVisitorToken: $currentVisitorToken
            );

            return true;
        } catch (SlotUnavailableException) {
            return false;
        }
    }

    /**
     * Resolve the effective buffer minutes for a given UTC slot start.
     */
    public function resolveEffectiveBuffer(CarbonInterface $startUtc): int
    {
        $businessTz = $this->timezoneService->getBusinessTimezone();
        $startBusiness = CarbonImmutable::instance($startUtc)->setTimezone($businessTz);
        $dateBusiness = $startBusiness->toDateString();
        $weekday = $startBusiness->dayOfWeek;

        $exception = AvailabilityException::query()->where('date', $dateBusiness)->first();
        if ($exception && $exception->isSpecialHours()) {
            $baseRule = AvailabilityRule::query()->where('weekday', $weekday)->where('enabled', true)->first();

            return $baseRule?->buffer_minutes ?? (int) Setting::get('booking_buffer_minutes', 15);
        }

        $timeString = $startBusiness->format('H:i:s');
        $rule = AvailabilityRule::query()
            ->where('weekday', $weekday)
            ->where('enabled', true)
            ->where('start_time', '<=', $timeString)
            ->where('end_time', '>=', $timeString)
            ->first();

        if ($rule && $rule->buffer_minutes !== null) {
            return (int) $rule->buffer_minutes;
        }

        $anyRule = AvailabilityRule::query()->where('weekday', $weekday)->where('enabled', true)->first();
        if ($anyRule && $anyRule->buffer_minutes !== null) {
            return (int) $anyRule->buffer_minutes;
        }

        return (int) Setting::get('booking_buffer_minutes', 15);
    }

    /**
     * Acquire deterministic pessimistic row locks on the business calendar dates
     * spanned by the buffer-expanded UTC slot to serialize mutations without relying on gap locks.
     *
     * @return array<int, string> The locked date strings (in business timezone)
     */
    public function acquireCalendarDateLocks(
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        ?int $bufferMinutes = null
    ): array {
        $businessTz = $this->timezoneService->getBusinessTimezone();
        $buffer = $bufferMinutes ?? $this->resolveEffectiveBuffer($startUtc);

        $startUtcImmutable = CarbonImmutable::instance($startUtc);
        $endUtcImmutable = CarbonImmutable::instance($endUtc);

        $startBusinessWithBuffer = $startUtcImmutable->subMinutes($buffer)->setTimezone($businessTz);
        $endBusinessWithBuffer = $endUtcImmutable->addMinutes($buffer)->setTimezone($businessTz);

        $dates = [];
        $current = $startBusinessWithBuffer->startOfDay();
        $endDay = $endBusinessWithBuffer->startOfDay();

        while ($current <= $endDay) {
            $dates[] = $current->toDateString();
            $current = $current->addDay();
        }

        $dates = array_values(array_unique($dates));
        sort($dates); // Consistent ordering prevents deadlocks across multi-date spans

        foreach ($dates as $date) {
            DB::table('booking_calendar_locks')->insertOrIgnore([
                'lock_date' => $date,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('booking_calendar_locks')
                ->where('lock_date', $date)
                ->lockForUpdate()
                ->first();
        }

        return $dates;
    }

    /**
     * Canonical slot resolution matching slot generation exactly.
     *
     * @return array{
     *     start_utc: CarbonImmutable,
     *     end_utc: CarbonImmutable,
     *     duration_minutes: int,
     *     buffer_minutes: int,
     *     min_notice_hours: int,
     *     max_horizon_days: int,
     *     business_date: string,
     *     business_timezone: string
     * }
     *
     * @throws SlotUnavailableException
     */
    public function resolveSlotConfiguration(
        SessionType $sessionType,
        CarbonInterface $startUtc,
        ?CarbonInterface $endUtc = null
    ): array {
        if (! $sessionType->active) {
            throw new SlotUnavailableException('This session type is not currently active.');
        }

        $start = $this->timezoneService->toUtc($startUtc);
        $nowUtc = CarbonImmutable::now('UTC');

        if ($start <= $nowUtc) {
            throw new SlotUnavailableException('Cannot book an appointment in the past.');
        }

        $businessTz = $this->timezoneService->getBusinessTimezone();
        $startBusiness = $start->setTimezone($businessTz);
        $dateBusiness = $startBusiness->toDateString();
        $weekday = $startBusiness->dayOfWeek;

        // Check for date exceptions
        $exception = AvailabilityException::query()->where('date', $dateBusiness)->first();
        if ($exception && $exception->isBlocked()) {
            throw new SlotUnavailableException('This date is blocked for appointments.');
        }

        $defaultMinNoticeHours = (int) Setting::get('booking_min_notice_hours', 12);
        $defaultBufferMinutes = (int) Setting::get('booking_buffer_minutes', 15);
        $defaultMaxHorizonDays = (int) Setting::get('booking_max_horizon_days', 60);

        // Determine candidate intervals for this date
        $intervals = [];
        if ($exception && $exception->isSpecialHours() && $exception->start_time && $exception->end_time) {
            $baseRule = AvailabilityRule::query()->where('weekday', $weekday)->where('enabled', true)->first();
            $intervals[] = [
                'start' => $exception->start_time,
                'end' => $exception->end_time,
                'duration' => $baseRule?->session_duration_minutes ?? $sessionType->duration_minutes,
                'buffer' => $baseRule?->buffer_minutes ?? $defaultBufferMinutes,
                'min_notice' => $baseRule?->min_notice_hours ?? $defaultMinNoticeHours,
                'max_horizon' => $baseRule?->max_horizon_days ?? $defaultMaxHorizonDays,
            ];
        } else {
            $rules = AvailabilityRule::query()->where('weekday', $weekday)->where('enabled', true)->get();
            if ($rules->isEmpty()) {
                throw new SlotUnavailableException('The tutor is not available on this day.');
            }

            foreach ($rules as $rule) {
                $intervals[] = [
                    'start' => $rule->start_time,
                    'end' => $rule->end_time,
                    'duration' => $rule->session_duration_minutes ?? $sessionType->duration_minutes,
                    'buffer' => $rule->buffer_minutes ?? $defaultBufferMinutes,
                    'min_notice' => $rule->min_notice_hours ?? $defaultMinNoticeHours,
                    'max_horizon' => $rule->max_horizon_days ?? $defaultMaxHorizonDays,
                ];
            }
        }

        // Check if requested slot falls into a DST gap (nonexistent local wall time)
        if ($this->timezoneService->isNonexistentLocalTime($startBusiness->format('Y-m-d H:i:s'), $businessTz)) {
            throw new SlotUnavailableException('The requested time does not exist due to Daylight Saving Time adjustment.');
        }

        // Match slot against authorized intervals and grid steps
        $matchedInterval = null;
        foreach ($intervals as $interval) {
            $duration = (int) $interval['duration'];
            $buffer = (int) $interval['buffer'];
            $step = $duration + $buffer;

            // Special-hours exceptions with nonexistent local times must not authorize slots
            if ($this->timezoneService->isNonexistentLocalTime("{$dateBusiness} {$interval['start']}", $businessTz)) {
                if ($exception && $exception->isSpecialHours()) {
                    continue;
                }
            }

            $slotMinutes = $startBusiness->hour * 60 + $startBusiness->minute;
            $startParts = explode(':', $interval['start']);
            $intervalStartMinutes = ((int) $startParts[0]) * 60 + ((int) $startParts[1]);
            $endParts = explode(':', $interval['end']);
            $intervalEndMinutes = ((int) $endParts[0]) * 60 + ((int) $endParts[1]);

            // Slot must fit completely within the interval
            if ($slotMinutes < $intervalStartMinutes || ($slotMinutes + $duration) > $intervalEndMinutes) {
                continue;
            }

            // Must align with grid: difference in minutes from interval start must be a multiple of step
            $diffMinutes = $slotMinutes - $intervalStartMinutes;
            if ($diffMinutes < 0 || ($diffMinutes % $step !== 0)) {
                continue;
            }

            // Seconds must be 0
            if ($startBusiness->second !== 0) {
                continue;
            }

            $matchedInterval = $interval;
            break;
        }

        if (! $matchedInterval) {
            throw new SlotUnavailableException('The requested time is outside available tutoring hours or does not align with the schedule grid.');
        }

        $expectedDuration = (int) $matchedInterval['duration'];
        $expectedEnd = $start->addMinutes($expectedDuration);

        // Validate client-supplied end time if provided
        if ($endUtc !== null) {
            $clientEnd = $this->timezoneService->toUtc($endUtc);
            if ($clientEnd->getTimestamp() !== $expectedEnd->getTimestamp()) {
                throw new SlotUnavailableException("Slot duration does not match the required session duration ({$expectedDuration} mins).");
            }
        }

        $minNoticeHours = (int) $matchedInterval['min_notice'];
        $maxHorizonDays = (int) $matchedInterval['max_horizon'];

        if ($start < $nowUtc->addHours($minNoticeHours)) {
            throw new SlotUnavailableException("Bookings require at least {$minNoticeHours} hours advance notice.");
        }

        if ($start > $nowUtc->addDays($maxHorizonDays)->endOfDay()) {
            throw new SlotUnavailableException("Bookings can only be scheduled up to {$maxHorizonDays} days in advance.");
        }

        return [
            'start_utc' => $start,
            'end_utc' => $expectedEnd,
            'duration_minutes' => $expectedDuration,
            'buffer_minutes' => (int) $matchedInterval['buffer'],
            'min_notice_hours' => $minNoticeHours,
            'max_horizon_days' => $maxHorizonDays,
            'business_date' => $dateBusiness,
            'business_timezone' => $businessTz,
        ];
    }

    /**
     * Authoritatively validate a slot for booking, hold, or reschedule against:
     * - Canonical schedule grid and rule resolution
     * - Active bookings collision including buffer
     * - Active holds collision
     *
     * @param  array<string, mixed>|null  $resolvedConfig
     *
     * @throws SlotUnavailableException
     */
    public function validateSlotForBooking(
        SessionType $sessionType,
        CarbonInterface $startUtc,
        CarbonInterface $endUtc,
        ?string $currentVisitorToken = null,
        ?string $currentHoldToken = null,
        ?int $excludeBookingId = null,
        ?int $excludeHoldId = null,
        bool $isTrustedAdmin = false,
        ?array $resolvedConfig = null
    ): void {
        $nowUtc = CarbonImmutable::now('UTC');

        $config = $resolvedConfig ?? $this->resolveSlotConfiguration(
            sessionType: $sessionType,
            startUtc: $startUtc,
            endUtc: $endUtc
        );

        $start = $config['start_utc'];
        $end = $config['end_utc'];
        $bufferMinutes = (int) $config['buffer_minutes'];

        // Check active bookings collision including surrounding buffer
        $bookingQuery = Booking::query()
            ->whereIn('status', ['confirmed', 'pending'])
            ->whereNull('cancelled_at');

        if ($excludeBookingId) {
            $bookingQuery->where('id', '!=', $excludeBookingId);
        }

        $candidateBookings = $bookingQuery
            ->where('start_at_utc', '<', $end->addMinutes($bufferMinutes))
            ->where('end_at_utc', '>', $start->subMinutes($bufferMinutes))
            ->lockForUpdate()
            ->get(['id', 'start_at_utc', 'end_at_utc']);

        foreach ($candidateBookings as $b) {
            $bStart = CarbonImmutable::instance($b->start_at_utc);
            $bEnd = CarbonImmutable::instance($b->end_at_utc);

            if ($start < $bEnd->addMinutes($bufferMinutes) && $end > $bStart->subMinutes($bufferMinutes)) {
                throw new SlotUnavailableException('This time was just taken or conflicts with the required buffer time between sessions.');
            }
        }

        // Check active holds collision
        $holdQuery = BookingHold::query()
            ->where('status', 'active')
            ->where('expires_at', '>', $nowUtc)
            ->where('slot_start_utc', '<', $end->addMinutes($bufferMinutes))
            ->where('slot_end_utc', '>', $start->subMinutes($bufferMinutes));

        if ($excludeHoldId) {
            $holdQuery->where('id', '!=', $excludeHoldId);
        }

        if ($currentHoldToken) {
            $holdQuery->where(function ($q) use ($currentHoldToken, $currentVisitorToken) {
                $q->where('hold_token', '!=', $currentHoldToken);
                if ($currentVisitorToken) {
                    $q->where('visitor_token', '!=', $currentVisitorToken);
                }
            });
        } elseif ($currentVisitorToken) {
            $holdQuery->where('visitor_token', '!=', $currentVisitorToken);
        }

        if ($holdQuery->lockForUpdate()->exists()) {
            throw new SlotUnavailableException('This time is currently reserved by another visitor. Please choose another available time.');
        }
    }
}

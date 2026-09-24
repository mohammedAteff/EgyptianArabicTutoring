<?php

namespace App\Domains\Booking\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Analytics\Models\VisitorSession;
use App\Domains\Analytics\Services\AnalyticsService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\BookingHold;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingService
{
    public function __construct(
        protected TimezoneService $timezoneService,
        protected ContactService $contactService,
        protected AvailabilityService $availabilityService,
        protected AnalyticsService $analyticsService,
        protected DatabaseCapability $databaseCapability,
    ) {}

    /**
     * Create a new confirmed booking atomically.
     *
     * @param  array{
     *     session_type_id: int,
     *     slot_id?: string,
     *     customer_timezone: string,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone?: string|null,
     *     notes?: string|null,
     *     idempotency_key: string,
     *     hold_id?: int|null,
     *     hold_token?: string|null,
     *     visitor_token?: string|null,
     *     session_token?: string|null,
     *     source?: string|null,
     *     medium?: string|null,
     *     campaign?: string|null,
     *     content?: string|null,
     *     term?: string|null
     * }  $data
     *
     * @throws SlotUnavailableException
     */
    /**
     * Create a confirmed booking via public booking flow requiring authenticated hold.
     *
     * @param  array{
     *     session_type_id: int,
     *     slot_id?: string,
     *     customer_timezone: string,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone?: string|null,
     *     notes?: string|null,
     *     idempotency_key: string,
     *     hold_id: int,
     *     hold_token: string,
     *     visitor_token: string,
     *     session_token?: string|null,
     *     source?: string|null,
     *     medium?: string|null,
     *     campaign?: string|null,
     *     content?: string|null,
     *     term?: string|null
     * }  $data
     *
     * @throws SlotUnavailableException
     */
    public function createPublicBooking(array $data): Booking
    {
        $idempotencyKey = trim($data['idempotency_key'] ?? '');
        if (empty($idempotencyKey)) {
            throw new \InvalidArgumentException('Idempotency key is required.');
        }

        // Validate hold presence before entering transaction
        $holdId = $data['hold_id'] ?? null;
        $holdToken = $data['hold_token'] ?? null;
        $visitorToken = $data['visitor_token'] ?? null;
        $sessionToken = $data['session_token'] ?? null;

        if (! $holdId || ! is_numeric($holdId)) {
            throw new SlotUnavailableException('A valid reservation hold is required.');
        }
        if (! $holdToken || ! is_string($holdToken) || trim($holdToken) === '') {
            throw new SlotUnavailableException('Reservation hold authentication token is required.');
        }
        if (! $visitorToken || ! is_string($visitorToken) || trim($visitorToken) === '') {
            throw new SlotUnavailableException('Visitor authentication token is required.');
        }
        if (! $sessionToken || ! is_string($sessionToken) || trim($sessionToken) === '') {
            throw new SlotUnavailableException('Session authentication token is required.');
        }

        $sessionType = SessionType::query()->findOrFail($data['session_type_id']);
        $holdSnapshot = BookingHold::query()->find((int) $holdId);
        if (! $holdSnapshot) {
            throw new SlotUnavailableException('Invalid reservation hold authentication.');
        }

        if (! hash_equals((string) $holdSnapshot->hold_token, (string) $holdToken)) {
            throw new SlotUnavailableException('Invalid reservation hold authentication.');
        }
        if (! hash_equals((string) $holdSnapshot->visitor_token, (string) $visitorToken)) {
            throw new SlotUnavailableException('Reservation hold ownership mismatch.');
        }
        if (! hash_equals((string) $holdSnapshot->session_token, (string) $sessionToken)) {
            throw new SlotUnavailableException('Reservation hold session mismatch.');
        }
        if ((int) $holdSnapshot->session_type_id !== (int) $sessionType->id) {
            throw new SlotUnavailableException('Reservation hold session type mismatch.');
        }

        // The authenticated server-side hold is the only source of slot timestamps.
        // Any start/end values supplied by a caller are deliberately ignored.
        $startUtc = $this->timezoneService->toUtc($holdSnapshot->slot_start_utc);
        $endUtc = $this->timezoneService->toUtc($holdSnapshot->slot_end_utc);
        $customerTimezone = $this->timezoneService->validate($data['customer_timezone']);
        $businessTimezone = $this->timezoneService->getBusinessTimezone();

        $analyticsVisitorToken = $data['analytics_visitor_token']
            ?? (session()->isStarted() ? session('analytics_visitor_token') : null)
            ?? request()->cookie('_va_visitor');
        $analyticsSessionToken = $data['analytics_session_token']
            ?? (session()->isStarted() ? session('analytics_session_token') : null)
            ?? request()->cookie('_va_session');
        $expectedVisitorToken = $analyticsVisitorToken ?? $visitorToken;
        $existingBooking = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existingBooking) {
            if (! hash_equals((string) $existingBooking->visitor_token, (string) $expectedVisitorToken)) {
                throw new SlotUnavailableException('This booking request cannot be verified.');
            }

            return $existingBooking;
        }

        if (! $sessionType->active) {
            throw new SlotUnavailableException('This session type is not currently active.');
        }
        if ($startUtc <= CarbonImmutable::now('UTC')) {
            throw new SlotUnavailableException('Cannot book an appointment in the past.');
        }
        $nowUtc = CarbonImmutable::now('UTC');

        $heldSessionType = SessionType::query()->whereKey($holdSnapshot->session_type_id)->first();
        if (! $heldSessionType) {
            throw new SlotUnavailableException('This reservation hold is no longer valid.');
        }
        $initialConfig = $this->availabilityService->resolveSlotConfiguration($heldSessionType, $startUtc, $endUtc);

        $bookingCreated = false;
        $booking = $this->databaseCapability->transaction(function () use (
            $data,
            $idempotencyKey,
            $sessionType,
            $startUtc,
            $endUtc,
            $customerTimezone,
            $businessTimezone,
            $visitorToken,
            $sessionToken,
            $holdId,
            $holdToken,
            $holdSnapshot,
            $initialConfig,
            $analyticsVisitorToken,
            $analyticsSessionToken,
            $nowUtc,
            &$bookingCreated,
        ) {
            // Acquire the canonical scheduling mutex before any booking-row lock.
            $this->availabilityService->acquireCalendarDateLocks(
                $startUtc,
                $initialConfig['end_utc'],
                $initialConfig['buffer_minutes'],
            );

            // 3. Verify hold with strict authentication and ownership
            $hold = BookingHold::query()
                ->where('id', (int) $holdId)
                ->where('hold_token', (string) $holdToken)
                ->lockForUpdate()
                ->first();

            if (! $hold) {
                throw new SlotUnavailableException('Invalid reservation hold authentication.');
            }

            if ((int) $hold->session_type_id !== (int) $sessionType->id) {
                throw new SlotUnavailableException('Reservation hold session type mismatch.');
            }

            if (! $hold->slot_start_utc->equalTo($holdSnapshot->slot_start_utc)
                || ! $hold->slot_end_utc->equalTo($holdSnapshot->slot_end_utc)) {
                throw new SlotUnavailableException('The held slot changed while it was being confirmed. Please select a slot again.');
            }

            if (! hash_equals((string) $hold->visitor_token, (string) $visitorToken)) {
                throw new SlotUnavailableException('Reservation hold ownership mismatch.');
            }

            if (! hash_equals((string) $hold->session_token, (string) $sessionToken)) {
                throw new SlotUnavailableException('Reservation hold session mismatch.');
            }

            // Check idempotency only after the scheduling mutex and authenticated hold are locked.
            $expectedVisitorToken = $analyticsVisitorToken ?? $visitorToken;
            $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals((string) $existing->visitor_token, (string) $expectedVisitorToken)) {
                    throw new SlotUnavailableException('This booking request cannot be verified.');
                }

                return $existing;
            }

            if ($hold->status !== 'active') {
                throw new SlotUnavailableException('Your reservation hold is no longer active.');
            }

            if ($hold->expires_at <= now()) {
                throw new SlotUnavailableException('Your reservation hold has expired. Please choose a slot again.');
            }

            $config = $this->availabilityService->resolveSlotConfiguration(
                sessionType: $sessionType,
                startUtc: $hold->slot_start_utc,
                endUtc: $hold->slot_end_utc,
            );

            // 4. Authoritative slot validation
            $this->availabilityService->validateSlotForBooking(
                sessionType: $sessionType,
                startUtc: $startUtc,
                endUtc: $config['end_utc'],
                currentVisitorToken: $visitorToken,
                currentHoldToken: $hold->hold_token,
                excludeHoldId: $hold->id,
                resolvedConfig: $config
            );

            // Authoritative Last Non-Direct Touch conversion attribution within exact 30-day lookback (EDITS V1 §18B)
            $conversionAttr = $this->analyticsService->getBookingConversionAttribution(
                visitorToken: $analyticsVisitorToken ?? $visitorToken,
                bookingTime: $nowUtc
            );

            if ($conversionAttr['utm_source'] !== 'Direct / None') {
                $utmSource = $conversionAttr['utm_source'];
                $utmMedium = $conversionAttr['utm_medium'];
                $utmCampaign = $conversionAttr['utm_campaign'];
                $utmContent = $conversionAttr['utm_content'];
                $utmTerm = $conversionAttr['utm_term'];
                $referrer = $conversionAttr['referrer'];
                $touchAt = $conversionAttr['touch_at'];
            } else {
                $utmSource = 'Direct / None';
                $utmMedium = null;
                $utmCampaign = null;
                $utmContent = null;
                $utmTerm = null;
                $referrer = null;
                $touchAt = null;
            }

            // Resolve or create canonical Contact
            $contact = $this->contactService->resolveOrCreate(
                email: $data['customer_email'] ?? $data['email'],
                name: $data['customer_name'] ?? $data['name'] ?? null,
                phone: $data['customer_phone'] ?? $data['phone'] ?? null,
                attribution: [
                    'utm_source' => $utmSource,
                    'utm_medium' => $utmMedium,
                    'utm_campaign' => $utmCampaign,
                    'utm_content' => $utmContent,
                    'utm_term' => $utmTerm,
                ]
            );

            // Generate timezone snapshot
            $snapshot = $this->timezoneService->createBookingSnapshot(
                startUtc: $startUtc,
                endUtc: $endUtc,
                customerTimezone: $customerTimezone,
                businessTimezone: $businessTimezone
            );

            // Generate secure non-guessable confirmation token
            $confirmationToken = Str::random(64);

            // Country is a server-derived snapshot. Never trust a form field
            // or a fresh request lookup at finalization time: the booking must
            // use the verified analytics session that owns the authenticated
            // hold. If that session cannot be verified, preserve NULL rather
            // than manufacturing an attribution value.
            $detectedCountry = null;
            $verifiedAnalyticsSession = null;
            if ($analyticsSessionToken) {
                $verifiedAnalyticsSession = VisitorSession::query()
                    ->where(function ($query) use ($analyticsSessionToken): void {
                        $query->where('session_token', $analyticsSessionToken)
                            ->orWhere('session_id', $analyticsSessionToken);
                    })
                    ->lockForUpdate()
                    ->first();
            }

            $verifiedVisitorToken = $verifiedAnalyticsSession?->visitor?->visitor_token;
            $expectedVisitorToken = $analyticsVisitorToken ?? $visitorToken;
            if ($verifiedAnalyticsSession && $verifiedVisitorToken && hash_equals((string) $verifiedVisitorToken, (string) $expectedVisitorToken)) {
                $detectedCountry = $verifiedAnalyticsSession->detected_country_code;
            }

            // Create booking with unique idempotency recovery
            try {
                $booking = Booking::create(array_merge($snapshot, [
                    'contact_id' => $contact->id,
                    'visitor_token' => $analyticsVisitorToken ?? $visitorToken,
                    'detected_country_code' => $detectedCountry,
                    'session_type_id' => $sessionType->id,
                    'status' => 'confirmed',
                    'idempotency_key' => $idempotencyKey,
                    'confirmation_token' => $confirmationToken,
                    'notes' => $data['notes'] ?? null,
                    'source' => $utmSource,
                    'medium' => $utmMedium,
                    'campaign' => $utmCampaign,
                    'content' => $utmContent,
                    'term' => $utmTerm,
                    'referrer' => $referrer,
                    'touch_at' => $touchAt,
                ]));
                $bookingCreated = true;
            } catch (QueryException $e) {
                // If duplicate key error (1062), fetch and return the winning concurrent booking
                if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                    $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
                    if ($existing && hash_equals((string) $existing->visitor_token, (string) $expectedVisitorToken)) {
                        return $existing;
                    }
                }
                throw $e;
            }

            // Convert hold atomically
            $hold->update([
                'status' => 'converted',
                'released_at' => now(),
            ]);

            // Record booking event
            BookingEvent::create([
                'booking_id' => $booking->id,
                'event_type' => 'created',
                'performed_by' => 'customer',
                'previous_data' => null,
                'new_data' => [
                    'status' => 'confirmed',
                    'start_at_utc' => $startUtc->toDateTimeString(),
                    'end_at_utc' => $endUtc->toDateTimeString(),
                    'customer_timezone' => $customerTimezone,
                ],
                'created_at' => now(),
            ]);

            // Track authoritative server-side analytics event post-commit
            DB::afterCommit(function () use ($booking, $sessionType, $startUtc, $endUtc, $customerTimezone, $analyticsVisitorToken, $visitorToken, $analyticsSessionToken, $sessionToken) {
                $this->analyticsService->trackEvent(
                    eventType: 'booking_completed',
                    page: '/booking/confirmed',
                    visitorToken: $analyticsVisitorToken ?? $visitorToken,
                    sessionToken: $analyticsSessionToken ?? $sessionToken,
                    metadata: [
                        'booking_id' => $booking->id,
                        'session_type_id' => $sessionType->id,
                        'start_at_utc' => $startUtc->toDateTimeString(),
                        'end_at_utc' => $endUtc->toDateTimeString(),
                        'customer_timezone' => $customerTimezone,
                    ]
                );
            });

            return $booking;
        }, 5);

        if ($bookingCreated) {
            try {
                app(AdminNotificationService::class)->notifyBookingCreated($booking);
            } catch (\Throwable) {
                // Notification failures must never roll back or prevent valid bookings.
            }
        }

        return $booking;
    }

    /**
     * Create an admin-initiated booking through an explicitly trusted path.
     *
     * @param  array{
     *     session_type_id: int,
     *     start_at_utc: CarbonInterface|string,
     *     end_at_utc: CarbonInterface|string,
     *     customer_timezone?: string|null,
     *     business_timezone?: string|null,
     *     customer_name: string,
     *     customer_email: string,
     *     customer_phone?: string|null,
     *     notes?: string|null,
     *     idempotency_key?: string|null,
     *     source?: string|null,
     *     medium?: string|null,
     *     campaign?: string|null,
     *     content?: string|null,
     *     term?: string|null
     * }  $data
     *
     * @throws SlotUnavailableException
     */
    public function createAdminBooking(array $data, ?int $adminId = null): Booking
    {
        $idempotencyKey = trim($data['idempotency_key'] ?? (string) Str::uuid());

        $sessionType = SessionType::findOrFail($data['session_type_id']);
        $startUtc = $this->timezoneService->toUtc($data['start_at_utc']);
        $endUtc = $this->timezoneService->toUtc($data['end_at_utc']);
        $nowUtc = CarbonImmutable::now('UTC');

        if ($startUtc <= $nowUtc) {
            throw new SlotUnavailableException('Cannot book an appointment in the past.');
        }

        $customerTimezone = $this->timezoneService->validate($data['customer_timezone'] ?? $this->timezoneService->getBusinessTimezone());
        $businessTimezone = $this->timezoneService->getBusinessTimezone();

        $bookingCreated = false;
        $booking = $this->databaseCapability->transaction(function () use (
            $data,
            $idempotencyKey,
            $sessionType,
            $startUtc,
            $endUtc,
            $customerTimezone,
            $businessTimezone,
            $adminId,
            &$bookingCreated,
        ) {
            // Acquire deterministic calendar locks on buffer-expanded business date(s)
            $buffer = $this->availabilityService->resolveEffectiveBuffer($startUtc);
            $this->availabilityService->acquireCalendarDateLocks($startUtc, $endUtc, $buffer);

            // Booking locks follow the canonical calendar mutex in the global lock order.
            $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            // Authoritative slot validation without hold requirement (trusted admin override)
            $this->availabilityService->validateSlotForBooking(
                sessionType: $sessionType,
                startUtc: $startUtc,
                endUtc: $endUtc,
                isTrustedAdmin: true
            );

            // Resolve or create canonical Contact
            $contact = $this->contactService->resolveOrCreate(
                email: $data['customer_email'] ?? $data['email'],
                name: $data['customer_name'] ?? $data['name'] ?? null,
                phone: $data['customer_phone'] ?? $data['phone'] ?? null,
                attribution: [
                    'utm_source' => $data['source'] ?? null,
                    'utm_medium' => $data['medium'] ?? null,
                    'utm_campaign' => $data['campaign'] ?? null,
                    'utm_content' => $data['content'] ?? null,
                    'utm_term' => $data['term'] ?? null,
                ]
            );

            // Generate timezone snapshot
            $snapshot = $this->timezoneService->createBookingSnapshot(
                startUtc: $startUtc,
                endUtc: $endUtc,
                customerTimezone: $customerTimezone,
                businessTimezone: $businessTimezone
            );

            $confirmationToken = Str::random(64);

            try {
                $booking = Booking::create(array_merge($snapshot, [
                    'contact_id' => $contact->id,
                    'session_type_id' => $sessionType->id,
                    // Administrator-created bookings have no verified public
                    // analytics session; do not accept a caller-supplied
                    // country claim.
                    'detected_country_code' => null,
                    'status' => 'confirmed',
                    'idempotency_key' => $idempotencyKey,
                    'confirmation_token' => $confirmationToken,
                    'notes' => $data['notes'] ?? 'Booked manually by administrator',
                    'source' => $data['source'] ?? null,
                    'medium' => $data['medium'] ?? null,
                    'campaign' => $data['campaign'] ?? null,
                    'content' => $data['content'] ?? null,
                    'term' => $data['term'] ?? null,
                    'referrer' => $data['referrer'] ?? null,
                    'touch_at' => isset($data['touch_at']) ? CarbonImmutable::parse($data['touch_at']) : null,
                ]));
                $bookingCreated = true;
            } catch (QueryException $e) {
                if ($e->getCode() === '23000' || str_contains($e->getMessage(), '1062')) {
                    $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
                    if ($existing) {
                        return $existing;
                    }
                }
                throw $e;
            }

            // Record booking event
            BookingEvent::create([
                'booking_id' => $booking->id,
                'event_type' => 'created',
                'performed_by' => $adminId ? "admin_{$adminId}" : 'admin',
                'previous_data' => null,
                'new_data' => [
                    'status' => 'confirmed',
                    'start_at_utc' => $startUtc->toDateTimeString(),
                    'end_at_utc' => $endUtc->toDateTimeString(),
                    'customer_timezone' => $customerTimezone,
                    'created_by' => 'administrator',
                ],
                'created_at' => now(),
            ]);

            return $booking;
        }, 5);

        if ($bookingCreated) {
            try {
                app(AdminNotificationService::class)->notifyBookingCreated($booking);
            } catch (\Throwable) {
                // Notification failures must never roll back or prevent valid bookings.
            }
        }

        return $booking;
    }

    /**
     * Dispatch booking creation: public booking requires hold, trusted admin path bypasses hold.
     *
     * @throws SlotUnavailableException
     */
    public function createBooking(array $data, bool $isTrustedAdmin = false): Booking
    {
        return $isTrustedAdmin
            ? $this->createAdminBooking($data)
            : $this->createPublicBooking($data);
    }

    /**
     * Retrieve booking by secure confirmation token.
     */
    public function findByConfirmationToken(string $token): ?Booking
    {
        return Booking::query()
            ->with(['contact', 'sessionType', 'events'])
            ->where('confirmation_token', $token)
            ->first();
    }
}

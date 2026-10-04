<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Database\Services\DatabaseCapability;
use App\Domains\Students\Models\Student;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use InvalidArgumentException;

class StudentBookingService
{
    public function __construct(
        private AvailabilityService $availability,
        private SlotResolver $slots,
        private TimezoneService $timezones,
        private ContactService $contacts,
        private StudentLedgerService $ledger,
        private DatabaseCapability $database,
    ) {}

    public function create(
        Student $student,
        string $slotId,
        int $sessionTypeId,
        string $customerTimezone,
        string $idempotencyKey,
        string $slotOwnerToken,
    ): Booking {
        $fingerprint = hash('sha256', json_encode([$slotId, $sessionTypeId, $customerTimezone, $slotOwnerToken], JSON_THROW_ON_ERROR));
        $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $this->assertOwnedReplay($existing, $student, $fingerprint);
        }

        $sessionType = SessionType::query()->whereKey($sessionTypeId)->where('active', true)->firstOrFail();
        $customerTimezone = $this->timezones->validate($customerTimezone);
        $initialSlot = $this->slots->resolve($slotId, $sessionType, $customerTimezone, $slotOwnerToken);
        $startUtc = CarbonImmutable::parse($initialSlot['slot_start_utc'], 'UTC');
        $endUtc = CarbonImmutable::parse($initialSlot['slot_end_utc'], 'UTC');
        $bufferMinutes = (int) $initialSlot['buffer_minutes'];
        $bookingCreated = false;

        try {
            $booking = $this->database->transaction(function () use ($student, $slotId, $sessionType, $customerTimezone, $idempotencyKey, $slotOwnerToken, $startUtc, $endUtc, $bufferMinutes, $fingerprint, &$bookingCreated): Booking {
                // Lock the canonical scheduling resource before contact, student, and booking rows.
                $this->availability->acquireCalendarDateLocks($startUtc, $endUtc, $bufferMinutes);

                $studentSnapshot = Student::query()
                    ->whereKey($student->id)
                    ->where('identity_status', 'verified')
                    ->firstOrFail();

                // Resolve and lock the Contact before locking the Student row.
                $email = $studentSnapshot->email_normalized ?: 'student-'.$studentSnapshot->id.'@internal.invalid';
                $contact = $this->contacts->resolveOrCreate(
                    email: $email,
                    name: trim($studentSnapshot->first_name.' '.$studentSnapshot->last_name),
                    phone: $studentSnapshot->phone,
                    attribution: ['utm_source' => 'Student Portal'],
                );

                $lockedStudent = Student::query()
                    ->whereKey($student->id)
                    ->where('identity_status', 'verified')
                    ->whereNull('suspended_at')
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedStudent->email_normalized !== $studentSnapshot->email_normalized
                    || $lockedStudent->first_name !== $studentSnapshot->first_name
                    || $lockedStudent->last_name !== $studentSnapshot->last_name
                    || $lockedStudent->phone !== $studentSnapshot->phone) {
                    throw new SlotUnavailableException('Student identity changed during booking confirmation. Please try again.');
                }

                $replay = Booking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($replay) {
                    return $this->assertOwnedReplay($replay, $lockedStudent, $fingerprint);
                }

                $sessionType = SessionType::query()->whereKey($sessionType->id)->where('active', true)->lockForUpdate()->firstOrFail();
                app(EntitlementService::class)->requirement($sessionType);
                // Re-resolve only after the mutex is held; the client never supplies booking timestamps.
                $slot = $this->slots->resolve($slotId, $sessionType, $customerTimezone, $slotOwnerToken);
                $authoritativeStart = CarbonImmutable::parse($slot['slot_start_utc'], 'UTC');
                $authoritativeEnd = CarbonImmutable::parse($slot['slot_end_utc'], 'UTC');
                if (! $authoritativeStart->equalTo($startUtc) || ! $authoritativeEnd->equalTo($endUtc)) {
                    throw new SlotUnavailableException('This time slot changed while it was being booked. Select it again.');
                }

                $snapshot = $this->timezones->createBookingSnapshot(
                    startUtc: $authoritativeStart,
                    endUtc: $authoritativeEnd,
                    customerTimezone: $customerTimezone,
                    businessTimezone: $this->timezones->getBusinessTimezone(),
                );
                $booking = Booking::query()->create(array_merge($snapshot, [
                    'contact_id' => $contact->id,
                    'student_id' => $lockedStudent->id,
                    'session_type_id' => $sessionType->id,
                    'status' => 'confirmed',
                    'idempotency_key' => $idempotencyKey,
                    'confirmation_token' => Str::random(64),
                    'source' => 'student_portal',
                    'request_fingerprint' => $fingerprint,
                    'notes' => null,
                ]));
                $booking = app(MeetingLinkService::class)->assign($booking);
                $bookingCreated = true;

                BookingEvent::query()->create([
                    'booking_id' => $booking->id,
                    'event_type' => 'created',
                    'performed_by' => 'student',
                    'performed_by_id' => $lockedStudent->id,
                    'new_data' => [
                        'status' => 'confirmed',
                        'start_at_utc' => $authoritativeStart->toDateTimeString(),
                        'end_at_utc' => $authoritativeEnd->toDateTimeString(),
                        'customer_timezone' => $customerTimezone,
                        'source' => 'student_portal',
                    ],
                    'created_at' => now('UTC'),
                ]);

                // FIFO package selection and the ledger debit share this transaction.
                $this->ledger->consumeForBooking($booking, 'booking:'.$booking->id.':session-consumed');

                return $booking;
            }, 3);
        } catch (QueryException $exception) {
            $replay = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($replay) {
                return $this->assertOwnedReplay($replay, $student, $fingerprint);
            }

            throw $exception;
        }

        if ($bookingCreated) {
            try {
                app(AdminNotificationService::class)->notifyBookingCreated($booking);
            } catch (\Throwable) {
                // A notification outage must not undo a committed student booking.
            }
        }

        return $booking;
    }

    private function assertOwnedReplay(Booking $booking, Student $student, string $fingerprint): Booking
    {
        if ((int) $booking->student_id !== (int) $student->id) {
            throw new InvalidArgumentException('This idempotency key belongs to another student.');
        }

        if ($booking->request_fingerprint !== $fingerprint) {
            throw new InvalidArgumentException('Idempotency payload or session type changed.');
        }

        return $booking;
    }
}

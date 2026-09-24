<?php

namespace App\Domains\Students\Services;

use App\Domains\Administration\Services\AdminNotificationService;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\SessionType;
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
        $existing = Booking::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $this->assertOwnedReplay($existing, $student);
        }

        $sessionType = SessionType::query()->whereKey($sessionTypeId)->where('active', true)->firstOrFail();
        $customerTimezone = $this->timezones->validate($customerTimezone);
        $initialSlot = $this->slots->resolve($slotId, $sessionType, $customerTimezone, $slotOwnerToken);
        $startUtc = CarbonImmutable::parse($initialSlot['slot_start_utc'], 'UTC');
        $endUtc = CarbonImmutable::parse($initialSlot['slot_end_utc'], 'UTC');
        $bufferMinutes = (int) $initialSlot['buffer_minutes'];
        $bookingCreated = false;

        try {
            $booking = $this->database->transaction(function () use ($student, $slotId, $sessionType, $customerTimezone, $idempotencyKey, $slotOwnerToken, $startUtc, $endUtc, $bufferMinutes, &$bookingCreated): Booking {
                // Lock the canonical scheduling resource before student and booking rows.
                $this->availability->acquireCalendarDateLocks($startUtc, $endUtc, $bufferMinutes);

                $lockedStudent = Student::query()
                    ->whereKey($student->id)
                    ->where('identity_status', 'verified')
                    ->lockForUpdate()
                    ->firstOrFail();

                $replay = Booking::query()->where('idempotency_key', $idempotencyKey)->lockForUpdate()->first();
                if ($replay) {
                    return $this->assertOwnedReplay($replay, $lockedStudent);
                }

                // Re-resolve only after the mutex is held; the client never supplies booking timestamps.
                $slot = $this->slots->resolve($slotId, $sessionType, $customerTimezone, $slotOwnerToken);
                $authoritativeStart = CarbonImmutable::parse($slot['slot_start_utc'], 'UTC');
                $authoritativeEnd = CarbonImmutable::parse($slot['slot_end_utc'], 'UTC');
                if (! $authoritativeStart->equalTo($startUtc) || ! $authoritativeEnd->equalTo($endUtc)) {
                    throw new SlotUnavailableException('This time slot changed while it was being booked. Select it again.');
                }

                $email = $lockedStudent->email_normalized ?: 'student-'.$lockedStudent->id.'@internal.invalid';
                $contact = $this->contacts->resolveOrCreate(
                    email: $email,
                    name: trim($lockedStudent->first_name.' '.$lockedStudent->last_name),
                    phone: $lockedStudent->phone,
                    attribution: ['utm_source' => 'Student Portal'],
                );
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
                    'notes' => null,
                ]));
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
                return $this->assertOwnedReplay($replay, $student);
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

    private function assertOwnedReplay(Booking $booking, Student $student): Booking
    {
        if ((int) $booking->student_id !== (int) $student->id) {
            throw new InvalidArgumentException('This idempotency key belongs to another student.');
        }

        return $booking;
    }
}

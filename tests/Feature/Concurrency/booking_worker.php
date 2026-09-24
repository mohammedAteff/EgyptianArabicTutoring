<?php

use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../../vendor/autoload.php';
$app = require_once __DIR__.'/../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$raw = $argv[1] ?? '{}';
$data = json_decode($raw, true);

if (! is_array($data) || empty($data['action'])) {
    echo "RESULT:ERROR:Invalid input data\n";
    exit(1);
}

$action = $data['action'];

if (isset($data['start_gate'], $data['worker_id'])) {
    $gate = (string) $data['start_gate'];
    $ready = $gate.'.ready.'.preg_replace('/[^A-Za-z0-9_-]/', '', (string) $data['worker_id']);
    file_put_contents($ready, 'ready', LOCK_EX);
    $deadline = microtime(true) + 10;
    while (! is_file($gate) && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (! is_file($gate)) {
        echo "RESULT:ERROR:Concurrency start gate timed out.\n";
        exit(1);
    }
}

try {
    if ($action === 'book_admin') {
        $service = app(BookingService::class);
        $booking = $service->createBooking([
            'session_type_id' => $data['session_type_id'],
            'start_at_utc' => $data['start_at_utc'],
            'end_at_utc' => $data['end_at_utc'],
            'customer_name' => $data['name'] ?? 'Concurrent Student',
            'customer_email' => $data['email'] ?? 'concurrent@example.com',
            'customer_timezone' => $data['customer_timezone'] ?? 'Africa/Cairo',
            'idempotency_key' => $data['idempotency_key'],
        ], isTrustedAdmin: true);

        echo 'RESULT:SUCCESS:'.$booking->id."\n";
        exit(0);
    } elseif ($action === 'hold') {
        $service = app(BookingHoldService::class);
        $sessionType = SessionType::findOrFail($data['session_type_id']);
        $hold = $service->acquireHold(
            visitorToken: $data['visitor_token'],
            sessionToken: $data['session_token'] ?? null,
            sessionType: $sessionType,
            startUtc: CarbonImmutable::parse($data['start_at_utc']),
            endUtc: CarbonImmutable::parse($data['end_at_utc'])
        );

        echo 'RESULT:SUCCESS:'.$hold->id."\n";
        exit(0);
    } elseif ($action === 'reschedule') {
        $service = app(RescheduleService::class);
        $booking = Booking::findOrFail($data['booking_id']);
        $rescheduled = $service->reschedule(
            booking: $booking,
            newStartUtc: CarbonImmutable::parse($data['new_start_utc']),
            newEndUtc: CarbonImmutable::parse($data['new_end_utc']),
            performedBy: 'admin'
        );

        echo 'RESULT:SUCCESS:'.$rescheduled->id."\n";
        exit(0);
    } elseif ($action === 'cancel') {
        $service = app(CancellationService::class);
        $booking = Booking::findOrFail($data['booking_id']);
        $cancelled = $service->cancel(
            booking: $booking,
            performedBy: $data['performed_by'] ?? 'customer',
            reason: $data['reason'] ?? 'Concurrent test cancellation'
        );

        echo 'RESULT:SUCCESS:'.$cancelled->id."\n";
        exit(0);
    } elseif ($action === 'merge_students') {
        $student = app(StudentMergeService::class)->merge(
            (int) $data['primary_student_id'],
            (int) $data['secondary_student_id'],
        );

        echo 'RESULT:SUCCESS:'.$student->id."\n";
        exit(0);
    } elseif ($action === 'merge_contacts') {
        app(ContactService::class)->merge(
            Contact::query()->findOrFail((int) $data['canonical_contact_id']),
            Contact::query()->findOrFail((int) $data['duplicate_contact_id']),
        );

        echo "RESULT:SUCCESS:contact-merge\n";
        exit(0);
    } elseif ($action === 'book_student') {
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $booking = app(StudentBookingService::class)->create(
            student: $student,
            slotId: $data['slot_id'],
            sessionTypeId: (int) $data['session_type_id'],
            customerTimezone: $data['customer_timezone'],
            idempotencyKey: $data['idempotency_key'],
            slotOwnerToken: $data['slot_owner_token'],
        );

        echo 'RESULT:SUCCESS:'.$booking->id."\n";
        exit(0);
    } elseif ($action === 'refund_payment') {
        $refund = app(StudentLedgerService::class)->refund(
            payment: PaymentRecord::query()->findOrFail((int) $data['payment_id']),
            amount: (string) $data['amount'],
            idempotencyKey: (string) $data['idempotency_key'],
            administratorId: null,
            reason: 'Concurrent package-ceiling test',
        );

        echo 'RESULT:SUCCESS:'.$refund->id."\n";
        exit(0);
    }

    echo "RESULT:ERROR:Unknown action {$action}\n";
    exit(1);
} catch (SlotUnavailableException|InvalidBookingStatusTransitionException|BookingPolicyViolationException $e) {
    echo 'RESULT:CONFLICT:'.$e->getMessage()."\n";
    exit(2);
} catch (InvalidArgumentException $e) {
    echo 'RESULT:CONFLICT:'.$e->getMessage()."\n";
    exit(2);
} catch (ModelNotFoundException $e) {
    echo "RESULT:CONFLICT:Student is no longer active.\n";
    exit(2);
} catch (ValidationException $e) {
    echo 'RESULT:CONFLICT:'.$e->getMessage()."\n";
    exit(2);
} catch (Throwable $e) {
    echo 'RESULT:EXCEPTION:'.get_class($e).':'.$e->getMessage()."\n";
    exit(3);
}

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
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;

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
    }

    echo "RESULT:ERROR:Unknown action {$action}\n";
    exit(1);
} catch (SlotUnavailableException|InvalidBookingStatusTransitionException|BookingPolicyViolationException $e) {
    echo 'RESULT:CONFLICT:'.$e->getMessage()."\n";
    exit(2);
} catch (Throwable $e) {
    echo 'RESULT:EXCEPTION:'.get_class($e).':'.$e->getMessage()."\n";
    exit(3);
}

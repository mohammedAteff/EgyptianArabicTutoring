<?php

use App\Domains\Administration\Models\Administrator;
use App\Domains\Administration\Services\AdministratorTwoFactorService;
use App\Domains\Audit\Services\AuditLogService;
use App\Domains\Booking\Exceptions\BookingPolicyViolationException;
use App\Domains\Booking\Exceptions\InvalidBookingStatusTransitionException;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\RecurringLessonPlan;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\MeetingLinkService;
use App\Domains\Booking\Services\NoShowService;
use App\Domains\Booking\Services\RecurringLessonService;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Contacts\Services\ContactService;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Forms\Services\FormSubmissionService;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\PackageRenewalService;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Http\Controllers\Admin\AccountSuspensionController;
use App\Http\Controllers\Student\FormController;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
    if ($action === 'recurring_generate') {
        $results = app(RecurringLessonService::class)->generate(
            RecurringLessonPlan::query()->findOrFail((int) $data['plan_id']),
            (int) $data['administrator_id'], 1, 1,
        );
        echo 'RESULT:SUCCESS:'.implode(',', array_map(fn ($row): string => $row->status, $results))."\n";
        exit(0);
    } elseif ($action === 'mark_no_show') {
        app(NoShowService::class)->mark(Booking::query()->findOrFail((int) $data['booking_id']), (int) $data['administrator_id']);
        echo "RESULT:SUCCESS:no_show\n";
        exit(0);
    } elseif ($action === 'renew_package') {
        $renewal = app(PackageRenewalService::class)->renew(
            StudentPackage::query()->findOrFail((int) $data['package_id']),
            ['renewal_date' => $data['renewal_date'], 'idempotency_key' => $data['idempotency_key']], (int) $data['administrator_id'],
        );
        echo 'RESULT:SUCCESS:'.$renewal->new_package_id."\n";
        exit(0);
    } elseif ($action === 'record_payment') {
        $payment = app(StudentLedgerService::class)->recordPayment(StudentPackage::query()->findOrFail((int) $data['package_id']), (string) $data['amount'], (string) $data['idempotency_key'], (int) $data['administrator_id']);
        echo 'RESULT:SUCCESS:'.$payment->id."\n";
        exit(0);
    }
    if (in_array($action, ['telegram_emit', 'telegram_deliver'], true)) {
        Queue::fake();
        Http::preventStrayRequests();
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => $data['worker_id']]])]);
        if ($action === 'telegram_emit') {
            app(TelegramAutomationService::class)->emit('booking_created', $data['identity'], ['booking_id' => 0], null, $data['rule_id']);
        } else {
            app(TelegramDeliveryService::class)->deliver($data['delivery_id']);
        }
        echo "RESULT:SUCCESS:telegram\n";
        exit(0);
    } elseif ($action === 'assign_meeting_room') {
        app(MeetingLinkService::class)->assign(Booking::findOrFail((int) $data['booking_id']), roomId: (int) $data['room_id']);
        echo "RESULT:SUCCESS:assigned\n";
        exit(0);
    } elseif ($action === 'two_factor_recovery') {
        app(AdministratorTwoFactorService::class)->authenticate((int) $data['administrator_id'], null, (string) $data['recovery_code']);
        echo "RESULT:SUCCESS:factor verified\n";
        exit(0);
    } elseif ($action === 'suspend_staff') {
        $actor = Administrator::findOrFail((int) $data['actor_id']);
        $request = Request::create('/admin/accounts/administrator/'.$data['target_id'].'/suspension', 'POST', ['suspend' => 1, 'reason' => 'Concurrency QA']);
        $request->setUserResolver(fn () => $actor);
        app(AccountSuspensionController::class)->update($request, 'administrator', (int) $data['target_id'], app(AuditLogService::class));
        echo "RESULT:SUCCESS:suspended\n";
        exit(0);
    } elseif ($action === 'book_admin') {
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
    } elseif ($action === 'book_public') {
        $booking = app(BookingService::class)->createPublicBooking([
            'hold_id' => (int) $data['hold_id'],
            'hold_token' => (string) $data['hold_token'],
            'visitor_token' => (string) $data['visitor_token'],
            'session_token' => (string) $data['session_token'],
            'session_type_id' => (int) $data['session_type_id'],
            'customer_timezone' => (string) $data['customer_timezone'],
            'first_name' => (string) $data['first_name'],
            'last_name' => (string) $data['last_name'],
            'customer_name' => trim($data['first_name'].' '.$data['last_name']),
            'customer_email' => (string) $data['customer_email'],
            'customer_phone' => (string) $data['customer_phone'],
            'date_of_birth' => (string) $data['date_of_birth'],
            'start_at_utc' => (string) $data['start_at_utc'],
            'end_at_utc' => (string) $data['end_at_utc'],
            'idempotency_key' => (string) $data['idempotency_key'],
        ], $data['intake_answers'] ?? [], isset($data['form_version_id']) ? (int) $data['form_version_id'] : null);

        echo 'RESULT:SUCCESS:'.$booking->id."\n";
        exit(0);
    } elseif ($action === 'publish_form') {
        $form = app(FormBuilderService::class)->publish(
            (int) $data['form_id'],
            (int) $data['active_version_id'],
            (int) $data['lock_version'],
        );

        echo 'RESULT:SUCCESS:'.$form->id."\n";
        exit(0);
    } elseif ($action === 'autosave_form') {
        $student = Student::verified()->findOrFail((int) $data['student_id']);
        $request = Request::create('/student/forms/'.$data['slug'].'/autosave', 'POST', [
            'answers' => $data['answers'] ?? [],
        ]);
        $request->attributes->set('student', $student);
        $response = app(FormController::class)->autosave($request, (string) $data['slug'], app(FormSubmissionService::class));

        echo 'RESULT:SUCCESS:'.$response->getData()->draft_id."\n";
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
} catch (InvalidArgumentException|DomainException $e) {
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

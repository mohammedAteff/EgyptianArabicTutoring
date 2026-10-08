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
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\VideoAsset;
use App\Domains\Lms\Services\CourseStudioService;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAssignmentService;
use App\Domains\Lms\Services\LmsProgressService;
use App\Domains\Lms\Services\LmsQuizService;
use App\Domains\Lms\Services\LmsTutoringAssignments;
use App\Domains\Lms\Services\LmsVideoService;
use App\Domains\Lms\Services\ProtectedPlaybackService;
use App\Domains\Lms\Services\StudentLearningStateService;
use App\Domains\Lms\Services\VideoDeviceService;
use App\Domains\Notifications\Services\TelegramAutomationService;
use App\Domains\Notifications\Services\TelegramDeliveryService;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\PackageRenewalService;
use App\Domains\Students\Services\StudentBookingService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\System\Services\DevelopmentToolsService;
use App\Http\Controllers\Admin\AccountSuspensionController;
use App\Http\Controllers\Student\FormController;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
    if (in_array($action, ['tutoring_assign', 'tutoring_private', 'tutoring_bulk'], true)) {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new RuntimeException('Tutoring learning races require the dedicated test database.');
        }
        $actor = Administrator::query()->findOrFail($data['administrator_id']);
        $service = app(LmsTutoringAssignments::class);
        if ($action === 'tutoring_bulk') {
            $rows = $service->bulk($actor, $data['student_ids'], $data['assignment']);
            echo 'RESULT:SUCCESS:'.count($rows)."\n";
        } else {
            $student = Student::query()->findOrFail($data['student_id']);
            $record = $action === 'tutoring_private' ? $service->createPrivate($actor, $student, $data['assignment'])
                : $service->assign($actor, $student, $data['assignment'])['record'];
            echo 'RESULT:SUCCESS:'.$record->id."\n";
        }
        exit(0);
    }
    if (in_array($action, ['evidence_manual', 'evidence_quiz_begin', 'evidence_quiz_submit', 'evidence_assignment_submit', 'evidence_assignment_review', 'evidence_watch'], true)) {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new RuntimeException('Learning evidence races require the dedicated test database.');
        }
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $course = Course::query()->findOrFail((int) $data['course_id']);
        $lesson = Lesson::query()->findOrFail((int) $data['lesson_id']);
        $block = isset($data['block_id']) ? LessonBlock::query()->findOrFail((int) $data['block_id']) : null;
        if ($action === 'evidence_watch') {
            $request = Request::create('/student/synthetic-progress-race', 'POST', $data['sample'], [VideoDeviceService::COOKIE => $data['device_token']]);
            $session = new Store('evidence-race', new ArraySessionHandler(120));
            $session->setId($data['session_id']);
            $session->start();
            $session->put(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
            $request->setLaravelSession($session);
            Auth::guard('student')->setUser($student);
            $result = app(LmsProgressService::class)->sampleWatch($request, $student, $course, $lesson, $block, (int) $data['watch_id']);
            echo 'RESULT:SUCCESS:'.json_encode($result, JSON_THROW_ON_ERROR)."\n";
        } else {
            $result = match ($action) {
                'evidence_manual' => app(LmsProgressService::class)->manual($student, $course, $lesson),
                'evidence_quiz_begin' => app(LmsQuizService::class)->begin($student, $course, $lesson, $block, $data['request_key']),
                'evidence_quiz_submit' => app(LmsQuizService::class)->submit($student, $course, $lesson, $block, (int) $data['attempt_id'], $data['answers']),
                'evidence_assignment_submit' => app(LmsAssignmentService::class)->submit($student, $course, $lesson, $block, $data['submission'], null),
                'evidence_assignment_review' => app(LmsAssignmentService::class)->review(Administrator::query()->findOrFail($data['administrator_id']), (int) $data['submission_id'], (int) $data['version'], $data['status'], $data['feedback']),
            };
            echo 'RESULT:SUCCESS:'.$result->id."\n";
        }
        exit(0);
    }
    if (in_array($action, ['video_issue', 'video_renew', 'video_register', 'video_device_revoke', 'video_grant_revoke', 'video_reconcile'], true)) {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new RuntimeException('Video races require the dedicated test database.');
        }
        config(['app.url' => 'http://example.test']);
        Http::preventStrayRequests();
        $asset = VideoAsset::query()->findOrFail((int) $data['asset_id']);
        Http::fake(function ($httpRequest) use ($asset, $data) {
            if (str_contains($httpRequest->url(), '/videolibrary/')) {
                return Http::response(['Id' => 123, 'PullZoneId' => 456, 'PlayerTokenAuthenticationEnabled' => true, 'BlockNoneReferrer' => true, 'EnableMP4Fallback' => false, 'ExposeOriginals' => false, 'AllowDirectPlay' => false, 'AllowEarlyPlay' => false, 'EnableDRM' => false, 'AllowedReferrers' => ['example.test']]);
            }
            if (str_contains($httpRequest->url(), '/pullzone/')) {
                return Http::response(['Id' => 456, 'Enabled' => true, 'Suspended' => false, 'ZoneSecurityEnabled' => true, 'ZoneSecurityIncludeHashRemoteIP' => false, 'ZoneSecurityKey' => 'fixture-sign-key-123456', 'Hostnames' => [['Value' => 'test-library.b-cdn.net', 'ForceSSL' => true]], 'BlockNoneReferrer' => true, 'AllowedReferrers' => ['example.test'], 'EdgeRules' => [], 'EdgeScriptId' => null, 'MiddlewareScriptId' => null, 'EnableAccessControlOriginHeader' => true, 'AccessControlOriginHeaderExtensions' => ['*']]);
            }
            if ($httpRequest->url() === 'https://video.bunnycdn.com/library/123/videos/'.$asset->provider_video_id) {
                if (! empty($data['read_gate'])) {
                    file_put_contents($data['read_gate'], 'read', LOCK_EX);
                    usleep(500000);
                }

                return Http::response(['guid' => $asset->provider_video_id, 'videoLibraryId' => 123, 'status' => (int) ($data['provider_status'] ?? 4), 'length' => 300, 'hasMP4Fallback' => false]);
            }
            throw new RuntimeException('Unexpected provider endpoint in race.');
        });
        if (! empty($data['wait_read_gate'])) {
            $deadline = microtime(true) + 5;
            while (! is_file($data['wait_read_gate']) && microtime(true) < $deadline) {
                usleep(10000);
            }
            if (! is_file($data['wait_read_gate'])) {
                throw new RuntimeException('Provider read synchronization timed out.');
            }
        }
        if ($action === 'video_reconcile') {
            $result = app(LmsVideoService::class)->reconcile($asset);
            echo 'RESULT:SUCCESS:'.$result->status."\n";
            exit(0);
        }
        $student = Student::query()->findOrFail((int) $data['student_id']);
        $request = Request::create('/student/synthetic-video-race', 'POST', ['request_key' => $data['request_key'] ?? null, 'lease_token' => $data['lease_token'] ?? null], [VideoDeviceService::COOKIE => $data['device_token'] ?? '']);
        $session = new Store('video-race', new ArraySessionHandler(120));
        $session->setId($data['session_id'] ?? str_repeat('a', 40));
        $session->start();
        $session->put(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHour()->toIso8601String()]);
        $request->setLaravelSession($session);
        Auth::guard('student')->setUser($student);
        if ($action === 'video_register') {
            app(VideoDeviceService::class)->register($request, $student);
        } elseif ($action === 'video_device_revoke') {
            app(VideoDeviceService::class)->revoke($student, (int) $data['device_id']);
        } elseif ($action === 'video_grant_revoke') {
            app(LmsAccessOperations::class)->change(Administrator::query()->findOrFail((int) $data['administrator_id']), AccessGrant::query()->findOrFail((int) $data['grant_id']), 'revoke', [], 1, 'race-revoke-'.$data['request_key']);
        } else {
            $result = app(ProtectedPlaybackService::class)->authorize($request, Course::query()->findOrFail((int) $data['course_id']), Lesson::query()->findOrFail((int) $data['lesson_id']), LessonBlock::query()->findOrFail((int) $data['block_id']), $action === 'video_renew' ? (int) $data['lease_id'] : null);
            echo 'RESULT:SUCCESS:'.$result['lease_id']."\n";
            exit(0);
        }
        echo "RESULT:SUCCESS\n";
        exit(0);
    }
    if (in_array($action, ['learning_visit', 'learning_note', 'learning_bookmark', 'learning_privacy'], true)) {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new RuntimeException('Student learning races require the dedicated test database.');
        }
        $student = Student::query()->findOrFail((int) $data['student_id']);
        if ($action === 'learning_privacy') {
            app(StudentPrivacyService::class)->anonymize($student->id, (int) $data['administrator_id']);
        } else {
            $state = app(StudentLearningStateService::class);
            $course = Course::query()->findOrFail((int) $data['course_id']);
            $lesson = Lesson::query()->findOrFail((int) $data['lesson_id']);
            match ($action) {
                'learning_visit' => $state->visit($student, $course, $lesson),
                'learning_note' => $state->saveNote($student, $course, $lesson, (string) $data['body'], $data['note_id'] ?? null, $data['version'] ?? null),
                'learning_bookmark' => $state->bookmark($student, $course, $lesson, (int) $data['block_id'], (float) $data['seconds'], $data['label'] ?? null),
            };
        }
        echo "RESULT:SUCCESS:student-learning\n";
        exit(0);
    }
    if (in_array($action, ['course_studio_create', 'course_studio_write', 'course_studio_publish', 'course_studio_duplicate'], true)) {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new RuntimeException('Course Studio races require the dedicated test database.');
        }
        $actor = Administrator::query()->findOrFail((int) $data['administrator_id']);
        $studio = app(CourseStudioService::class);
        if ($action === 'course_studio_create') {
            $course = $studio->create($actor, $data['values']);
        } else {
            $source = Course::query()->findOrFail((int) $data['course_id']);
            $course = match ($action) {
                'course_studio_write' => $studio->write($actor, $source, 'metadata', $data['values'], (int) $data['version']),
                'course_studio_publish' => $studio->publish($actor, $source, (int) $data['version']),
                'course_studio_duplicate' => $studio->duplicate($actor, $source, (int) $data['version']),
            };
        }
        echo 'RESULT:SUCCESS:'.$course->id."\n";
        exit(0);
    }
    if (in_array($action, ['lms_grant', 'lms_change'], true)) {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new RuntimeException('LMS races require the dedicated test database.');
        }
        $actor = Administrator::query()->findOrFail((int) $data['administrator_id']);
        $operations = app(LmsAccessOperations::class);
        $grant = $action === 'lms_grant'
            ? $operations->grant($actor, Student::query()->findOrFail((int) $data['student_id']), Course::query()->findOrFail((int) $data['course_id']), $data['terms'] ?? [], (string) $data['key'])
            : $operations->change($actor, AccessGrant::query()->findOrFail((int) $data['grant_id']), (string) $data['change'], $data['terms'] ?? [], (int) $data['version'], (string) $data['key']);
        echo 'RESULT:SUCCESS:'.$grant->id."\n";
        exit(0);
    }
    if ($action === 'development_confirm') {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new RuntimeException('Development races require the dedicated test database.');
        }
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false]);
        $summary = app(DevelopmentToolsService::class)->confirm(
            Administrator::query()->findOrFail((int) $data['administrator_id']),
            (string) $data['session_id'], (string) $data['token'], 'Stage5RacePass!', null, null, (string) $data['phrase'],
        );
        echo 'RESULT:SUCCESS:'.$summary['operation_id']."\n";
        exit(0);
    } elseif ($action === 'recurring_generate') {
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
} catch (HttpException $e) {
    if (in_array($e->getStatusCode(), [403, 404, 409], true)) {
        echo 'RESULT:CONFLICT:'.$e->getMessage()."\n";
        exit(2);
    }
    echo 'RESULT:EXCEPTION:'.$e->getMessage()."\n";
    exit(3);
} catch (Throwable $e) {
    echo 'RESULT:EXCEPTION:'.get_class($e).':'.$e->getMessage()."\n";
    exit(3);
}

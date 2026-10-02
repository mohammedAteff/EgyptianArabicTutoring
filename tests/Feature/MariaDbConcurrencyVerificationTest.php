<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Availability\Services\AvailabilityService;
use App\Domains\Availability\Services\SlotResolver;
use App\Domains\Booking\Exceptions\SlotUnavailableException;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\Booking\Services\BookingService;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Students\Models\PaymentRecord;
use App\Domains\Students\Models\PaymentRefund;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Timezone\Services\TimezoneService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\HasPublishedShortForm;
use Tests\TestCase;

class MariaDbConcurrencyVerificationTest extends TestCase
{
    use HasPublishedShortForm;

    protected AvailabilityService $availabilityService;

    protected BookingService $bookingService;

    protected SessionType $sessionType;

    private array $raceStudentIds = [];

    private array $raceStudentEmails = [];

    private array $raceBookingKeys = [];

    private array $raceContactEmails = [];

    private array $raceHoldIds = [];

    private array $raceFormIds = [];

    private array $raceAdministratorIds = [];

    private array $raceCalendarDates = [];

    /** @var list<Process|resource> */
    protected array $workerProcesses = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->availabilityService = app(AvailabilityService::class);
        $this->bookingService = app(BookingService::class);

        $this->sessionType = SessionType::firstOrCreate(
            ['slug' => 'conversational-arabic'],
            [
                'title' => 'Conversational Arabic',
                'duration_minutes' => 60,
                'price' => 40.00,
                'currency' => 'USD',
                'active' => true,
            ]
        );

        for ($w = 0; $w <= 6; $w++) {
            AvailabilityRule::updateOrCreate(
                ['weekday' => $w],
                [
                    'start_time' => '00:00:00',
                    'end_time' => '23:59:00',
                    'session_duration_minutes' => 60,
                    'buffer_minutes' => 0,
                    'min_notice_hours' => 0,
                    'max_horizon_days' => 90,
                    'enabled' => true,
                ]
            );
        }
    }

    protected function tearDown(): void
    {
        // 1. Terminate all child worker processes before touching the database
        if (! empty($this->workerProcesses)) {
            foreach ($this->workerProcesses as $process) {
                if (is_resource($process)) {
                    proc_terminate($process);
                    proc_close($process);
                } elseif ($process instanceof Process && $process->isRunning()) {
                    $process->stop(1);
                }
            }
            $this->workerProcesses = [];
        }

        // 2. Disconnect and purge secondary PDOs
        foreach (['conn_a', 'conn_b', 'secondary'] as $connection) {
            try {
                DB::disconnect($connection);
                DB::purge($connection);
            } catch (\Throwable) {
                // Connection not initialized
            }
        }

        if (DB::connection()->getPdo()) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        }

        if ($this->raceFormIds !== []) {
            $versionIds = DB::table('form_versions')->whereIn('form_id', $this->raceFormIds)->pluck('id');
            $submissionIds = DB::table('form_submissions')->whereIn('form_version_id', $versionIds)->pluck('id');
            DB::table('form_submission_revisions')->whereIn('form_submission_id', $submissionIds)->delete();
            DB::table('form_answers')->whereIn('form_submission_id', $submissionIds)->delete();
            DB::table('form_submissions')->whereIn('id', $submissionIds)->delete();
            $questionIds = DB::table('form_questions')->whereIn('form_version_id', $versionIds)->pluck('id');
            DB::table('form_question_options')->whereIn('form_question_id', $questionIds)->delete();
            DB::table('form_questions')->whereIn('id', $questionIds)->delete();
            DB::table('form_triggers')->whereIn('form_id', $this->raceFormIds)->delete();
            DB::table('form_versions')->whereIn('id', $versionIds)->delete();
            DB::table('forms')->whereIn('id', $this->raceFormIds)->delete();
        }
        if ($this->raceAdministratorIds !== []) {
            DB::table('administrators')->whereIn('id', $this->raceAdministratorIds)->delete();
        }

        if ($this->raceStudentEmails !== []) {
            $this->raceStudentIds = array_values(array_unique(array_merge(
                $this->raceStudentIds,
                DB::table('students')->whereIn('email_normalized', $this->raceStudentEmails)->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            )));
        }

        $bookingIds = DB::table('bookings')
            ->whereIn('idempotency_key', array_merge(
                ['unique-key-concurrent-1', 'unique-key-concurrent-2'],
                $this->raceBookingKeys,
            ))
            ->pluck('id');
        if ($this->raceStudentIds !== []) {
            $bookingIds = $bookingIds->merge(DB::table('bookings')->whereIn('student_id', $this->raceStudentIds)->pluck('id'))->unique();
            DB::table('session_ledger_entries')->whereIn('student_id', $this->raceStudentIds)->delete();
            DB::table('payment_refunds')->whereIn('student_id', $this->raceStudentIds)->delete();
            DB::table('payment_records')->whereIn('student_id', $this->raceStudentIds)->delete();
            DB::table('student_packages')->whereIn('student_id', $this->raceStudentIds)->delete();
            DB::table('session_reschedules')->where('actor_type', 'student')->whereIn('actor_id', $this->raceStudentIds)->delete();
        }
        if ($bookingIds->isNotEmpty()) {
            DB::table('session_ledger_entries')->whereIn('booking_id', $bookingIds)->delete();
            DB::table('admin_notifications')->whereIn('title', $bookingIds->map(fn (int $id): string => "New Booking #{$id}"))->delete();
            DB::table('session_reschedules')->whereIn('booking_id', $bookingIds)->delete();
            DB::table('bookings')->whereIn('id', $bookingIds)->delete();
        }
        if ($this->raceHoldIds !== []) {
            DB::table('booking_holds')->whereIn('id', $this->raceHoldIds)->delete();
        }
        if ($this->raceContactEmails !== []) {
            DB::table('contacts')->whereIn('email', $this->raceContactEmails)->delete();
        }
        if ($this->raceStudentIds !== []) {
            DB::table('students')->whereIn('id', $this->raceStudentIds)->delete();
        }
        if ($this->raceCalendarDates !== []) {
            DB::table('booking_calendar_locks')->whereIn('lock_date', $this->raceCalendarDates)->delete();
        }
        DB::table('bookings')->where('idempotency_key', 'like', 'unique-key-concurrent-%')->delete();
        DB::table('session_types')->where('slug', 'conversational-arabic')->delete();
        DB::table('contacts')->whereIn('email', ['first@boltlanding.test', 'second@boltlanding.test'])->delete();
        AvailabilityRule::query()->delete();

        if (DB::connection()->getPdo()) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        parent::tearDown();
    }

    public function test_calendar_date_mutex_locks_are_deterministically_created(): void
    {
        $startUtc = CarbonImmutable::now('UTC')->addDays(2)->setTime(10, 0);
        $endUtc = $startUtc->addMinutes(60);

        DB::beginTransaction();
        $lockedDates = $this->availabilityService->acquireCalendarDateLocks($startUtc, $endUtc);
        DB::commit();

        $this->assertNotEmpty($lockedDates);
        foreach ($lockedDates as $date) {
            $this->assertDatabaseHas('booking_calendar_locks', [
                'lock_date' => $date,
            ]);
        }
    }

    public function test_two_connections_serialize_via_mariadb_innodb_row_lock(): void
    {
        $startUtc = CarbonImmutable::now('UTC')->addDays(3)->setTime(14, 0);
        $endUtc = $startUtc->addMinutes(60);
        $targetDate = $startUtc->setTimezone('Africa/Cairo')->toDateString();

        // 1. Ensure the row exists in booking_calendar_locks
        DB::table('booking_calendar_locks')->insertOrIgnore([
            'lock_date' => $targetDate,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pdoConfig = config('database.connections.mysql');
        $dsn = "mysql:host={$pdoConfig['host']};port={$pdoConfig['port']};dbname={$pdoConfig['database']};charset=utf8mb4";

        // 2. Open Connection 1 (PDO session 1) and acquire pessimistic lock on target date
        $conn1Pdo = new \PDO($dsn, $pdoConfig['username'], $pdoConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $conn1Pdo->beginTransaction();
        $stmt1 = $conn1Pdo->prepare('SELECT * FROM booking_calendar_locks WHERE lock_date = ? FOR UPDATE');
        $stmt1->execute([$targetDate]);
        $stmt1->fetch();

        // 3. Open Connection 2 (PDO session 2) with 1s lock wait timeout
        $conn2Pdo = new \PDO($dsn, $pdoConfig['username'], $pdoConfig['password'], [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
        ]);
        $conn2Pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
        $conn2Pdo->beginTransaction();

        $timedOut = false;
        try {
            $stmt2 = $conn2Pdo->prepare('SELECT * FROM booking_calendar_locks WHERE lock_date = ? FOR UPDATE');
            $stmt2->execute([$targetDate]);
            $stmt2->fetch();
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), '1205') || str_contains($e->getMessage(), 'Lock wait timeout')) {
                $timedOut = true;
            }
        } finally {
            $conn2Pdo->rollBack();
        }

        $this->assertTrue($timedOut, 'Concurrent Connection 2 must be blocked by Connection 1 row lock on booking_calendar_locks');

        // 4. Release Connection 1 lock by committing
        $conn1Pdo->commit();

        // 5. Now Connection 2 can acquire the lock immediately without timeout
        $conn2Pdo->beginTransaction();
        $stmt2 = $conn2Pdo->prepare('SELECT * FROM booking_calendar_locks WHERE lock_date = ? FOR UPDATE');
        $stmt2->execute([$targetDate]);
        $row = $stmt2->fetch(\PDO::FETCH_ASSOC);
        $conn2Pdo->commit();

        $this->assertNotEmpty($row);
        $this->assertEquals($targetDate, $row['lock_date']);
    }

    public function test_concurrent_booking_collision_is_rejected_authoritatively(): void
    {
        $slotStartUtc = CarbonImmutable::now('UTC')->addDays(5)->setTime(10, 0);
        $slotEndUtc = $slotStartUtc->addMinutes(60);

        // Booking 1 succeeds
        $booking1 = $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'customer_name' => 'First Student',
            'customer_email' => 'first@boltlanding.test',
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'UTC',
            'idempotency_key' => 'unique-key-concurrent-1',
        ], isTrustedAdmin: true);

        $this->assertNotNull($booking1->id);

        // Booking 2 attempting exact same time slot is authoritatively rejected
        $this->expectException(SlotUnavailableException::class);

        $this->bookingService->createBooking([
            'session_type_id' => $this->sessionType->id,
            'customer_name' => 'Second Student',
            'customer_email' => 'second@boltlanding.test',
            'start_at_utc' => $slotStartUtc,
            'end_at_utc' => $slotEndUtc,
            'customer_timezone' => 'UTC',
            'idempotency_key' => 'unique-key-concurrent-2',
        ], isTrustedAdmin: true);
    }

    public function test_parallel_adjacent_slots_both_succeed_without_deadlock(): void
    {
        $date = CarbonImmutable::now('Africa/Cairo')->addDays(4)->startOfDay();
        $firstStart = $date->setTime(10, 0)->setTimezone('UTC');
        $secondStart = $date->setTime(11, 0)->setTimezone('UTC');
        $firstKey = 'lock-order-adjacent-first-'.Str::uuid();
        $secondKey = 'lock-order-adjacent-second-'.Str::uuid();
        $this->raceBookingKeys = [$firstKey, $secondKey];
        $this->raceCalendarDates = [$date->toDateString()];

        $results = $this->runConcurrentWorkers([
            [
                'action' => 'book_admin',
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $firstStart->toDateTimeString(),
                'end_at_utc' => $firstStart->addHour()->toDateTimeString(),
                'name' => 'Adjacent Slot First',
                'email' => 'adjacent-first-'.Str::uuid().'@boltlanding.test',
                'customer_timezone' => 'Africa/Cairo',
                'idempotency_key' => $firstKey,
            ],
            [
                'action' => 'book_admin',
                'session_type_id' => $this->sessionType->id,
                'start_at_utc' => $secondStart->toDateTimeString(),
                'end_at_utc' => $secondStart->addHour()->toDateTimeString(),
                'name' => 'Adjacent Slot Second',
                'email' => 'adjacent-second-'.Str::uuid().'@boltlanding.test',
                'customer_timezone' => 'Africa/Cairo',
                'idempotency_key' => $secondKey,
            ],
        ]);

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertDatabaseHas('bookings', ['idempotency_key' => $firstKey, 'status' => 'confirmed']);
        $this->assertDatabaseHas('bookings', ['idempotency_key' => $secondKey, 'status' => 'confirmed']);
    }

    public function test_parallel_refunds_from_separate_payments_never_exceed_package_paid_total(): void
    {
        $student = Student::factory()->verified()->create();
        $this->raceStudentIds = [$student->id];
        $this->raceContactEmails = [$student->email];

        $ledger = app(StudentLedgerService::class);
        $package = $ledger->createPackage(
            $student,
            'Concurrent refund package',
            2,
            '200.00',
            '0.00',
            'USD',
            null,
            'concurrent-refund-package-'.Str::uuid(),
        );
        $firstPayment = $ledger->recordPayment($package, '100.00', 'concurrent-payment-a-'.Str::uuid(), null);
        $secondPayment = $ledger->recordPayment($package, '100.00', 'concurrent-payment-b-'.Str::uuid(), null);

        $results = $this->runConcurrentWorkers([
            [
                'action' => 'refund_payment',
                'payment_id' => $firstPayment->id,
                'amount' => '100.00',
                'idempotency_key' => 'concurrent-refund-a-'.Str::uuid(),
            ],
            [
                'action' => 'refund_payment',
                'payment_id' => $secondPayment->id,
                'amount' => '100.00',
                'idempotency_key' => 'concurrent-refund-b-'.Str::uuid(),
            ],
        ]);

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertSame(2, PaymentRefund::query()->where('student_package_id', $package->id)->count());
        $this->assertSame('200.00', number_format((float) PaymentRefund::query()->where('student_package_id', $package->id)->sum('amount_refunded'), 2, '.', ''));
        $this->assertSame('200.00', number_format((float) PaymentRecord::query()->where('student_package_id', $package->id)->sum('amount_paid'), 2, '.', ''));
    }

    public function test_parallel_student_booking_and_merge_follow_calendar_student_booking_lock_order(): void
    {
        $date = CarbonImmutable::now('Africa/Cairo')->addDays(6)->startOfDay();
        $this->raceCalendarDates = [$date->toDateString()];
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $this->raceStudentIds = [$primary->id, $secondary->id];
        $this->raceContactEmails = [$primary->email, $secondary->email];

        $contact = Contact::query()->create([
            'name' => trim($secondary->first_name.' '.$secondary->last_name),
            'email' => $secondary->email,
            'phone' => $secondary->phone,
        ]);
        $start = $date->setTime(9, 0)->setTimezone('UTC');
        $snapshot = app(TimezoneService::class)->createBookingSnapshot(
            $start,
            $start->addHour(),
            'Africa/Cairo',
            'Africa/Cairo',
        );
        $seedBookingKey = 'lock-order-race-seed-'.Str::uuid();
        $this->raceBookingKeys[] = $seedBookingKey;
        Booking::query()->create(array_merge($snapshot, [
            'contact_id' => $contact->id,
            'student_id' => $secondary->id,
            'session_type_id' => $this->sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => $seedBookingKey,
            'confirmation_token' => Str::random(64),
        ]));
        app(StudentLedgerService::class)->createPackage(
            $secondary,
            'Concurrency package',
            2,
            '80.00',
            '0.00',
            'USD',
            null,
            'lock-order-race-package-'.Str::uuid(),
        );

        $slotOwnerToken = Str::random(64);
        $slots = app(AvailabilityService::class)->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'Africa/Cairo',
            fromDate: $date,
            toDate: $date,
            currentVisitorToken: $slotOwnerToken,
        );
        $targetStart = $date->setTime(14, 0)->setTimezone('UTC')->toDateTimeString();
        $slot = collect($slots)->flatten(1)->first(fn (array $candidate): bool => $candidate['slot_start_utc'] === $targetStart);
        $this->assertNotNull($slot, 'The student booking worker must receive a server-generated slot identity.');
        $slotId = app(SlotResolver::class)->issue($this->sessionType, $slot, 'Africa/Cairo', $slotOwnerToken);
        $studentBookingKey = 'lock-order-race-student-'.Str::uuid();
        $this->raceBookingKeys[] = $studentBookingKey;

        $results = $this->runConcurrentWorkers([
            [
                'action' => 'merge_students',
                'primary_student_id' => $primary->id,
                'secondary_student_id' => $secondary->id,
            ],
            [
                'action' => 'book_student',
                'student_id' => $secondary->id,
                'slot_id' => $slotId,
                'session_type_id' => $this->sessionType->id,
                'customer_timezone' => 'Africa/Cairo',
                'idempotency_key' => $studentBookingKey,
                'slot_owner_token' => $slotOwnerToken,
            ],
        ]);

        $this->assertContains($results[0]['exit_code'], [0, 2], json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertContains($results[1]['exit_code'], [0, 2], json_encode($results, JSON_THROW_ON_ERROR));
        $newBooking = Booking::query()->where('idempotency_key', $studentBookingKey)->first();

        if ($results[0]['exit_code'] === 0) {
            $this->assertStringStartsWith('RESULT:SUCCESS:', $results[0]['output']);
            $this->assertSame('merged', Student::withTrashed()->findOrFail($secondary->id)->identity_status);
            $this->assertDatabaseHas('bookings', [
                'idempotency_key' => $seedBookingKey,
                'student_id' => $primary->id,
            ]);

            if ($results[1]['exit_code'] === 0) {
                // The booking committed before the merge took its snapshot, so the merge included it.
                $this->assertStringStartsWith('RESULT:SUCCESS:', $results[1]['output']);
                $this->assertNotNull($newBooking);
                $this->assertSame($primary->id, (int) $newBooking->student_id);
                $this->assertDatabaseHas('session_ledger_entries', [
                    'booking_id' => $newBooking->id,
                    'student_id' => $primary->id,
                    'entry_type' => 'session_consumed',
                    'credit_change' => -1,
                ]);
            } else {
                // The merge committed before the booking could use the secondary student.
                $this->assertStringStartsWith('RESULT:CONFLICT:', $results[1]['output']);
                $this->assertNull($newBooking);
            }

            return;
        }

        // The booking committed after the merge's snapshot, so the merge cleanly requests a retry.
        $this->assertSame(2, $results[0]['exit_code'], json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertStringStartsWith('RESULT:CONFLICT:', $results[0]['output']);
        $this->assertStringContainsString('Retry the merge.', $results[0]['output']);
        $this->assertSame(0, $results[1]['exit_code'], json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertStringStartsWith('RESULT:SUCCESS:', $results[1]['output']);
        $this->assertSame('verified', Student::withTrashed()->findOrFail($secondary->id)->identity_status);
        $this->assertDatabaseHas('bookings', [
            'idempotency_key' => $seedBookingKey,
            'student_id' => $secondary->id,
        ]);
        $this->assertNotNull($newBooking);
        $this->assertSame($secondary->id, (int) $newBooking->student_id);
        $this->assertDatabaseHas('session_ledger_entries', [
            'booking_id' => $newBooking->id,
            'student_id' => $secondary->id,
            'entry_type' => 'session_consumed',
            'credit_change' => -1,
        ]);
    }

    public function test_parallel_student_booking_and_contact_merge_leave_no_booking_on_a_merged_contact(): void
    {
        $date = CarbonImmutable::now('Africa/Cairo')->addDays(14)->startOfDay();
        $this->raceCalendarDates[] = $date->toDateString();
        $student = Student::factory()->verified()->create();
        $this->raceStudentIds[] = $student->id;

        $canonicalEmail = 'contact-race-canonical-'.Str::uuid().'@boltlanding.test';
        $duplicateEmail = (string) $student->email_normalized;
        $this->raceContactEmails[] = $canonicalEmail;
        $this->raceContactEmails[] = $duplicateEmail;
        $canonical = Contact::query()->create(['name' => 'Canonical Race Contact', 'email' => $canonicalEmail]);
        $duplicate = Contact::query()->create([
            'name' => trim($student->first_name.' '.$student->last_name),
            'email' => $duplicateEmail,
            'phone' => $student->phone,
        ]);

        $existingStart = $date->setTime(8, 0)->setTimezone('UTC');
        $existingSnapshot = app(TimezoneService::class)->createBookingSnapshot(
            $existingStart,
            $existingStart->addHour(),
            'Africa/Cairo',
            'Africa/Cairo',
        );
        $existingKey = 'contact-merge-race-existing-'.Str::uuid();
        $this->raceBookingKeys[] = $existingKey;
        Booking::query()->create(array_merge($existingSnapshot, [
            'contact_id' => $duplicate->id,
            'student_id' => $student->id,
            'session_type_id' => $this->sessionType->id,
            'status' => 'confirmed',
            'idempotency_key' => $existingKey,
            'confirmation_token' => Str::random(64),
        ]));
        app(StudentLedgerService::class)->createPackage(
            $student,
            'Contact merge race package',
            2,
            '80.00',
            '0.00',
            'USD',
            null,
            'contact-merge-race-package-'.Str::uuid(),
        );

        $slotOwnerToken = Str::random(64);
        $slots = $this->availabilityService->getAvailableSlotsGroupedByDate(
            sessionType: $this->sessionType,
            customerTimezone: 'Africa/Cairo',
            fromDate: $date,
            toDate: $date,
            currentVisitorToken: $slotOwnerToken,
        );
        $targetStart = $date->setTime(14, 0)->setTimezone('UTC')->toDateTimeString();
        $slot = collect($slots)->flatten(1)->first(fn (array $candidate): bool => $candidate['slot_start_utc'] === $targetStart);
        $this->assertNotNull($slot, 'A separate server-generated slot is required for the concurrent student booking.');
        $slotId = app(SlotResolver::class)->issue($this->sessionType, $slot, 'Africa/Cairo', $slotOwnerToken);
        $bookingKey = 'contact-merge-race-booking-'.Str::uuid();
        $this->raceBookingKeys[] = $bookingKey;

        $results = $this->runConcurrentWorkers([
            [
                'action' => 'merge_contacts',
                'canonical_contact_id' => $canonical->id,
                'duplicate_contact_id' => $duplicate->id,
            ],
            [
                'action' => 'book_student',
                'student_id' => $student->id,
                'slot_id' => $slotId,
                'session_type_id' => $this->sessionType->id,
                'customer_timezone' => 'Africa/Cairo',
                'idempotency_key' => $bookingKey,
                'slot_owner_token' => $slotOwnerToken,
            ],
        ]);

        $this->assertContains($results[0]['exit_code'], [0, 2], json_encode($results, JSON_THROW_ON_ERROR));
        $this->assertSame(0, $results[1]['exit_code'], json_encode($results, JSON_THROW_ON_ERROR));
        $newBooking = Booking::query()->where('idempotency_key', $bookingKey)->firstOrFail();
        $duplicateFresh = Contact::withTrashed()->findOrFail($duplicate->id);

        if ($duplicateFresh->merged_into_contact_id !== null) {
            $this->assertSame((int) $canonical->id, (int) $newBooking->contact_id);
            $this->assertDatabaseMissing('bookings', ['contact_id' => $duplicate->id]);
            $this->assertSame('RESULT:SUCCESS:contact-merge', trim($results[0]['output']));
        } else {
            $this->assertSame((int) $duplicate->id, (int) $newBooking->contact_id);
            $this->assertStringStartsWith('RESULT:CONFLICT:', $results[0]['output']);
        }
    }

    public function test_concurrent_public_bookings_for_new_identity_create_one_legacy_student(): void
    {
        $intake = $this->installShortFormFixture();
        $this->raceFormIds[] = $intake->id;
        $this->raceAdministratorIds[] = $intake->created_by;

        $date = CarbonImmutable::now('Africa/Cairo')->addDays(24)->startOfDay();
        $email = 'public-identity-race-'.Str::uuid().'@boltlanding.test';
        $phone = '+201088776655';
        $visitorTokens = [Str::random(64), Str::random(64)];
        $sessionTokens = [Str::random(64), Str::random(64)];
        $keys = ['public-identity-race-'.Str::uuid(), 'public-identity-race-'.Str::uuid()];
        $payloads = [];
        $this->raceStudentEmails[] = $email;
        $this->raceContactEmails[] = $email;
        $this->raceBookingKeys = array_merge($this->raceBookingKeys, $keys);

        foreach ([0, 1] as $index) {
            $slotDate = $date->addDays($index);
            $start = $slotDate->setTime(14, 0)->setTimezone('UTC');
            $end = $start->addHour();
            $this->raceCalendarDates[] = $slotDate->toDateString();
            $hold = app(BookingHoldService::class)->acquireHold(
                visitorToken: $visitorTokens[$index],
                sessionToken: $sessionTokens[$index],
                sessionType: $this->sessionType,
                startUtc: $start,
                endUtc: $end,
            );
            $this->raceHoldIds[] = $hold->id;

            $payloads[] = [
                'action' => 'book_public',
                'hold_id' => $hold->id,
                'hold_token' => $hold->hold_token,
                'visitor_token' => $visitorTokens[$index],
                'session_token' => $sessionTokens[$index],
                'session_type_id' => $this->sessionType->id,
                'customer_timezone' => 'Africa/Cairo',
                'first_name' => 'Shared',
                'last_name' => 'Identity',
                'customer_email' => $email,
                'customer_phone' => $phone,
                'date_of_birth' => '1990-06-01',
                'start_at_utc' => $start->toDateTimeString(),
                'end_at_utc' => $end->toDateTimeString(),
                'idempotency_key' => $keys[$index],
            ];
        }

        $results = $this->runConcurrentWorkers($payloads);

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results, JSON_THROW_ON_ERROR));
        $bookings = Booking::query()->whereIn('idempotency_key', $keys)->get();
        $this->assertCount(2, $bookings);
        $this->assertCount(1, $bookings->pluck('student_id')->unique());
        $studentId = (int) $bookings->first()->student_id;
        $this->assertSame('legacy_unverified', Student::query()->findOrFail($studentId)->identity_status);
        $this->assertSame(1, Student::query()->where('email_normalized', mb_strtolower($email, 'UTF-8'))->count());
        $this->assertSame(1, Contact::query()->where('email', mb_strtolower($email, 'UTF-8'))->count());
        $this->assertSame(2, DB::table('booking_holds')->whereIn('id', $this->raceHoldIds)->where('status', 'converted')->count());
    }

    public function test_concurrent_pre_booking_publications_leave_one_published_trigger(): void
    {
        $administrator = Administrator::query()->create([
            'name' => 'Publication Race Admin',
            'email' => 'publication-race-'.Str::uuid().'@boltlanding.test',
            'password' => Str::random(48),
            'role' => 'admin',
        ]);
        $this->raceAdministratorIds[] = $administrator->id;
        $builder = app(FormBuilderService::class);
        $forms = [];
        $questions = [[
            'question_key' => 'race_question',
            'label' => 'Race question',
            'question_type' => 'short_text',
            'is_required' => false,
            'assistant_visible' => true,
        ]];

        foreach (['first', 'second'] as $label) {
            $form = $builder->create([
                'title' => "Publication Race {$label}",
                'slug' => 'publication-race-'.$label.'-'.Str::uuid(),
                'trigger' => 'pre_booking',
                'is_mandatory' => false,
                'can_edit_after_submission' => false,
            ], $questions, $administrator);
            $forms[] = $form;
            $this->raceFormIds[] = $form->id;
        }

        $results = $this->runConcurrentWorkers(array_map(fn (Form $form): array => [
            'action' => 'publish_form',
            'form_id' => $form->id,
            'active_version_id' => $form->active_version_id,
            'lock_version' => $form->lock_version,
        ], $forms));

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results, JSON_THROW_ON_ERROR));
        foreach (Form::query()->whereIn('id', $this->raceFormIds)->get() as $form) {
            $this->assertSame('published', $form->status);
            $this->assertSame((int) $form->active_version_id, (int) $form->published_version_id);
        }
        $this->assertSame(1, DB::table('form_triggers')->where('trigger_name', 'pre_booking')->count());
        $this->assertSame(1, DB::table('form_triggers')->whereIn('form_id', $this->raceFormIds)->where('trigger_name', 'pre_booking')->count());
    }

    public function test_concurrent_student_form_autosaves_keep_one_draft_for_published_version(): void
    {
        $administrator = Administrator::query()->create([
            'name' => 'Autosave Race Admin',
            'email' => 'autosave-race-'.Str::uuid().'@boltlanding.test',
            'password' => Str::random(48),
            'role' => 'admin',
        ]);
        $this->raceAdministratorIds[] = $administrator->id;
        $form = app(FormBuilderService::class)->create([
            'title' => 'Autosave Race Form',
            'slug' => 'autosave-race-'.Str::uuid(),
            'trigger' => 'none',
            'is_mandatory' => false,
            'can_edit_after_submission' => false,
        ], [[
            'question_key' => 'notes',
            'label' => 'Notes',
            'question_type' => 'short_text',
            'is_required' => false,
            'assistant_visible' => true,
        ]], $administrator);
        $form = app(FormBuilderService::class)->publish($form->id, $form->active_version_id, $form->lock_version);
        $this->raceFormIds[] = $form->id;

        $student = Student::factory()->verified()->create();
        $this->raceStudentIds[] = $student->id;
        $this->raceStudentEmails[] = $student->email;
        $this->raceContactEmails[] = $student->email;
        $values = ['First concurrent draft', 'Second concurrent draft'];
        $results = $this->runConcurrentWorkers(array_map(fn (string $value): array => [
            'action' => 'autosave_form',
            'student_id' => $student->id,
            'slug' => $form->slug,
            'answers' => ['notes' => $value],
        ], $values));

        $this->assertSame([0, 0], array_column($results, 'exit_code'), json_encode($results, JSON_THROW_ON_ERROR));
        $drafts = DB::table('form_submissions')
            ->where('form_version_id', $form->published_version_id)
            ->where('student_id', $student->id)
            ->where('status', 'draft')
            ->get();
        $this->assertCount(1, $drafts);
        $questionId = DB::table('form_questions')->where('form_version_id', $form->published_version_id)->where('question_key', 'notes')->value('id');
        $savedAnswer = DB::table('form_answers')->where('form_submission_id', $drafts->first()->id)->where('form_question_id', $questionId)->value('value_text');
        $this->assertContains($savedAnswer, $values);
    }

    /** @param array<int, array<string, mixed>> $payloads
     * @return array<int, array{exit_code: int|null, output: string}>
     */
    private function runConcurrentWorkers(array $payloads): array
    {
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'boltlanding-v3-'.Str::uuid();
        $workerPath = base_path('tests/Feature/Concurrency/booking_worker.php');
        $processes = [];
        $readyFiles = [];

        try {
            foreach ($payloads as $index => $payload) {
                $workerId = 'worker-'.$index;
                $readyFiles[] = $gate.'.ready.'.$workerId;
                $payload['start_gate'] = $gate;
                $payload['worker_id'] = $workerId;
                $process = new Process([PHP_BINARY, $workerPath, json_encode($payload, JSON_THROW_ON_ERROR)]);
                $process->setTimeout(20);
                $process->start();
                $processes[] = $process;
                $this->workerProcesses[] = $process;
            }

            $deadline = microtime(true) + 10;
            while (microtime(true) < $deadline && count(array_filter($readyFiles, 'is_file')) !== count($readyFiles)) {
                usleep(10000);
            }
            $readyCount = count(array_filter($readyFiles, 'is_file'));
            if ($readyCount !== count($readyFiles)) {
                $outputs = array_map(fn (Process $process): string => $process->getOutput().$process->getErrorOutput(), $processes);
                $this->fail('Concurrent workers did not reach their start gate: '.json_encode($outputs, JSON_THROW_ON_ERROR));
            }

            file_put_contents($gate, 'start', LOCK_EX);
            foreach ($processes as $process) {
                $process->wait();
            }

            return array_map(fn (Process $process): array => [
                'exit_code' => $process->getExitCode(),
                'output' => trim($process->getOutput()),
            ], $processes);
        } finally {
            if (is_file($gate)) {
                unlink($gate);
            }
            foreach ($readyFiles as $readyFile) {
                if (is_file($readyFile)) {
                    unlink($readyFile);
                }
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }
}

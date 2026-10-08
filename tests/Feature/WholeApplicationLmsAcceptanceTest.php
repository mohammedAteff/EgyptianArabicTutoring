<?php

namespace Tests\Feature;

use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\LessonMaterial;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\CancellationService;
use App\Domains\Booking\Services\RescheduleService;
use App\Domains\Lms\Models\AssignmentSubmission;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\LessonBookmark;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Models\QuizAttempt;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsLearningDefinition;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\SessionLedgerEntry;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\BillingReconciliationService;
use App\Domains\Students\Services\EntitlementService;
use App\Domains\Students\Services\InstallmentScheduleService;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\TeachingRecordService;
use Carbon\CarbonImmutable;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class WholeApplicationLmsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name): Student
    {
        return Student::factory()->verified()->create(['first_name' => $name, 'last_name' => 'Acceptance',
            'name_normalized' => strtolower($name).' acceptance', 'email' => strtolower($name).'@example.test',
            'email_normalized' => strtolower($name).'@example.test', 'date_of_birth' => '1990-01-01']);
    }

    private function login(Student $student): void
    {
        $this->post(route('student.login.submit'), ['name' => $student->first_name.' '.$student->last_name,
            'email' => $student->email, 'date_of_birth' => '1990-01-01'])->assertRedirect(route('student.dashboard'));
        $this->assertAuthenticatedAs($student, 'student');
        $this->assertSame($student->id, session('student_id'));
    }

    private function lesson(?Student $owner = null): Lesson
    {
        $course = Course::factory()->published()->create($owner ? ['kind' => 'private', 'owner_student_id' => $owner->id] : []);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>Private Arabic learning</p>']]);

        return $lesson;
    }

    /** @return array<string,string> */
    private function snapshot(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    public function test_normal_identity_switch_returns_404_for_foreign_private_learning_and_preserves_owned_facts(): void
    {
        Storage::fake('local');
        $lucy = $this->student('Lucy');
        $sarah = $this->student('Sarah');
        $lesson = $this->lesson($lucy);
        $course = $lesson->course;
        $assignment = app(LmsAccessOperations::class)->assign(AdministratorFactory::new()->create(), $lucy, $course, ['instructions' => 'Lucy private instructions'], 'whole-private');
        $quiz = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'quiz', 'status' => 'ready',
            'payload' => app(LmsLearningDefinition::class)->assessment('quiz', ['title' => 'Private quiz', 'passing_score' => 100, 'attempt_limit' => 1, 'review_policy' => 'never',
                'questions' => [['type' => 'blank', 'prompt' => 'Greeting', 'points' => 1, 'accepted' => ['PRIVATE ANSWER KEY']]]])]);
        $submissionBlock = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'assignment', 'status' => 'ready',
            'payload' => app(LmsLearningDefinition::class)->assessment('assignment', ['title' => 'Private file response', 'instructions' => 'Upload your response', 'types' => ['file']])]);
        $video = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'external_video', 'status' => 'ready', 'payload' => ['url' => 'https://example.test/learning.mp4']]);
        $this->login($lucy);
        $this->get(route('student.learning.lessons.show', [$course, $lesson]))->assertOk()->assertDontSee('PRIVATE ANSWER KEY');
        $this->post(route('student.learning.notes.store', [$course, $lesson]), ['body' => 'Lucy private note'])->assertSessionHasNoErrors();
        $this->post(route('student.learning.bookmarks.store', [$course, $lesson]), ['block_id' => $video->id, 'seconds' => 12, 'label' => 'Lucy private bookmark'])->assertSessionHasNoErrors();
        $this->post(route('student.evidence.quiz.begin', [$course, $lesson, $quiz]), ['request_key' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $attempt = QuizAttempt::query()->sole();
        $this->get(route('student.evidence.show', [$course, $lesson, $quiz]))->assertOk()->assertDontSee('PRIVATE ANSWER KEY');
        $this->post(route('student.evidence.assignment.submit', [$course, $lesson, $submissionBlock]), ['request_key' => (string) Str::uuid(), 'kind' => 'file',
            'file' => UploadedFile::fake()->createWithContent('lucy.txt', 'Lucy private submission bytes')])->assertSessionHasNoErrors();
        $submission = AssignmentSubmission::query()->sole();
        $download = $this->get(route('student.evidence.file', $submission))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame('Lucy private submission bytes', file_get_contents($download->baseResponse->getFile()->getPathname()));
        $note = LessonNote::query()->sole();
        $bookmark = LessonBookmark::query()->sole();
        $tables = ['lms_lesson_notes', 'lms_lesson_bookmarks', 'lms_lesson_progress', 'lms_quiz_attempts', 'lms_assignment_submissions', 'lms_learning_visits', 'lms_access_grants'];
        $before = $this->snapshot($tables);
        $this->post(route('student.logout'))->assertRedirect();
        $this->login($sarah);

        foreach ([route('student.learning.courses.show', $course), route('student.learning.lessons.show', [$course, $lesson]),
            route('student.learning.assignments.show', $assignment), route('student.lms.courses.show', $course), route('student.lms.lessons.show', [$course, $lesson]),
            route('student.lms.assignments.show', $assignment), route('student.evidence.show', [$course, $lesson, $quiz]),
            route('student.evidence.file', $submission)] as $url) {
            $this->get($url)->assertNotFound()->assertDontSee('Lucy private')->assertDontSee('PRIVATE ANSWER KEY');
        }
        $this->post(route('student.evidence.quiz.submit', [$course, $lesson, $quiz, $attempt]), ['answers' => ['PRIVATE ANSWER KEY'], 'student_id' => $lucy->id, 'score' => 100])->assertNotFound();
        $this->post(route('student.evidence.manual', [$course, $lesson]), ['student_id' => $lucy->id, 'percent' => 100])->assertNotFound();
        $this->patch(route('student.learning.notes.update', [$course, $lesson, $note]), ['body' => 'Changed', 'version' => $note->lock_version, 'student_id' => $lucy->id])->assertNotFound();
        $this->delete(route('student.learning.notes.destroy', $note), ['version' => $note->lock_version])->assertNotFound();
        $this->delete(route('student.learning.bookmarks.destroy', [$course, $lesson, $bookmark]))->assertNotFound();
        $this->get(route('student.learning.notes.index'))->assertOk()->assertDontSee('Lucy private note');
        $this->assertSame($before, $this->snapshot($tables));
    }

    public function test_shared_course_enrollment_does_not_share_tutoring_preparation_or_private_session_materials(): void
    {
        $lucy = $this->student('Lucy');
        $sarah = $this->student('Sarah');
        $actor = AdministratorFactory::new()->create();
        $lesson = $this->lesson();
        foreach ([$lucy, $sarah] as $student) {
            app(LmsAccessOperations::class)->grant($actor, $student, $lesson->course, [], 'whole-shared-'.$student->id);
        }
        $booking = Booking::factory()->create(['student_id' => $lucy->id, 'status' => 'completed']);
        $material = LessonMaterial::factory()->for($booking)->create(['title' => 'Lucy private session material', 'student_visible' => false]);
        app(TeachingRecordService::class)->save($lucy, 'preparation', ['body' => 'Lucy private tutor preparation', 'booking_id' => $booking->id], $actor->id);
        $this->login($sarah);

        $this->get(route('student.learning.lessons.show', [$lesson->course, $lesson]))->assertOk()->assertDontSee('Lucy private');
        $this->get(route('student.teaching.index'))->assertOk()->assertDontSee('Lucy private tutor preparation');
        $this->get(route('student.lessons.show', $booking))->assertNotFound()->assertDontSee('Lucy private');
        $this->get(route('student.lessons.materials.open', [$booking, $material]))->assertNotFound();
        $this->post(route('student.logout'))->assertRedirect();
        $this->login($lucy);
        $this->get(route('student.learning.index'))->assertOk()->assertDontSee('Lucy private tutor preparation')->assertDontSee('Lucy private session material');
        $this->get(route('student.lessons.materials.open', [$booking, $material]))->assertNotFound();
        $this->assertFalse($material->fresh()->student_visible);
    }

    public function test_learning_then_reschedule_and_cancel_keeps_one_original_typed_debit_and_one_restoration(): void
    {
        $this->freezeTime();
        $student = $this->student('Lucy');
        $actor = AdministratorFactory::new()->create();
        $ledger = app(StudentLedgerService::class);
        $one = $ledger->createPackage($student, 'One hour', 3, '120.00', '0.00', 'USD', null, 'whole-one', entitlementCode: 'one_hour');
        $ledger->createPackage($student, 'Two hour', 2, '160.00', '0.00', 'USD', null, 'whole-two', entitlementCode: 'two_hour');
        $payment = $ledger->recordPayment($one, '40.00', 'whole-payment', $actor->id);
        $ledger->refund($payment, '5.00', 'whole-refund', $actor->id);
        app(InstallmentScheduleService::class)->create($one, [['expected_amount' => '120.00', 'due_date' => now('UTC')->addMonth()->toDateString()]], 'whole-schedule', $actor->id);
        $type = SessionType::query()->create(['title' => 'One-hour tutoring', 'slug' => 'whole-one-hour', 'duration_minutes' => 60, 'price' => '40.00', 'currency' => 'USD', 'active' => true,
            'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::query()->where('code', 'one_hour')->value('id'), 'required_entitlement_units' => 1]);
        $booking = DB::transaction(function () use ($student, $type, $ledger): Booking {
            Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            $booking = Booking::factory()->create(['student_id' => $student->id, 'session_type_id' => $type->id]);
            $ledger->consumeForBooking($booking, 'whole-debit');

            return $booking;
        });
        $tables = ['student_packages', 'student_package_entitlements', 'session_ledger_entries', 'payment_records', 'payment_refunds', 'package_installments', 'bookings'];
        $before = $this->snapshot($tables);
        $lesson = $this->lesson();
        app(LmsAccessOperations::class)->grant($actor, $student, $lesson->course, [], 'whole-learning');
        $this->login($student);
        $this->post(route('student.evidence.manual', [$lesson->course, $lesson]))->assertRedirect();
        $this->get(route('student.learning.index'))->assertOk()->assertSee('Completed');
        $this->assertSame($before, $this->snapshot($tables));
        $money = $this->snapshot(['student_packages', 'payment_records', 'payment_refunds', 'package_installments']);
        $target = CarbonImmutable::now('Africa/Cairo')->addDays(12)->setTime(12, 0);
        AvailabilityRule::query()->create(['weekday' => $target->dayOfWeek, 'start_time' => '00:00:00', 'end_time' => '23:59:00', 'session_duration_minutes' => 60,
            'buffer_minutes' => 0, 'min_notice_hours' => 0, 'max_horizon_days' => 90, 'enabled' => true]);

        $changed = app(RescheduleService::class)->reschedule($booking, $target->utc(), performedBy: 'admin', performedById: $actor->id);
        $this->assertSame($booking->consumed_ledger_entry_id, $changed->consumed_ledger_entry_id);
        $this->assertSame($booking->student_package_entitlement_id, $changed->student_package_entitlement_id);
        $cancelled = app(CancellationService::class)->cancel($changed, 'admin', $actor->id);
        app(CancellationService::class)->cancel($cancelled, 'admin', $actor->id);
        $this->assertSame(1, SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'session_consumed')->count());
        $restore = SessionLedgerEntry::query()->where('booking_id', $booking->id)->where('entry_type', 'cancellation_restore')->sole();
        $this->assertSame(1, $restore->credit_change);
        $this->assertSame($booking->student_package_entitlement_id, $restore->student_package_entitlement_id);
        $totals = app(EntitlementService::class)->forStudent($student->id);
        $this->assertSame(3, $totals['one_hour']['available']);
        $this->assertSame(2, $totals['two_hour']['available']);
        $this->assertSame($money, $this->snapshot(array_keys($money)));
        $this->assertSame([], app(BillingReconciliationService::class)->checkTypedEntitlements());
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id, 'status' => 'completed']);
    }
}

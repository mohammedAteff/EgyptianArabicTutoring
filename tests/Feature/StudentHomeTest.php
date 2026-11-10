<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Availability\Models\AvailabilityRule;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\MeetingRoom;
use App\Domains\Booking\Models\SessionType;
use App\Domains\Booking\Services\BookingHoldService;
use App\Domains\CMS\Models\Setting;
use App\Domains\Forms\Models\Form;
use App\Domains\Forms\Services\FormBuilderService;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\LearningVisit;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\StudentLearningService;
use App\Domains\Students\Models\Homework;
use App\Domains\Students\Models\ResourceAssignment;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentBin;
use App\Domains\Students\Models\StudentNotification;
use App\Domains\Students\Models\StudentPackage;
use App\Domains\Students\Services\StudentLedgerService;
use Carbon\CarbonImmutable;
use Database\Factories\ResourceFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class StudentHomeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-26 09:00:00 UTC');
        Http::preventStrayRequests();
        $this->withHeader('X-Analytics-Synthetic', '1');
        URL::forceRootUrl('http://arabicwabdallah.test');
    }

    #[TestWith(['join'])]
    #[TestWith(['soon'])]
    #[TestWith(['due_homework'])]
    #[TestWith(['mandatory_form'])]
    #[TestWith(['homework'])]
    #[TestWith(['expiring'])]
    #[TestWith(['continuation'])]
    #[TestWith(['learning'])]
    #[TestWith(['book'])]
    #[TestWith(['later'])]
    #[TestWith(['resource'])]
    #[TestWith(['diagnostic'])]
    #[TestWith(['feedback'])]
    #[TestWith(['fallback'])]
    public function test_home_selects_each_supported_next_action_from_current_owned_facts(string $kind): void
    {
        $student = $this->student();
        $this->signIn($student);
        switch ($kind) {
            case 'join':
                $this->booking($student, -10, true);
                break;
            case 'soon':
                $this->booking($student, 60);
                break;
            case 'due_homework':
                $this->homework($student, '2026-10-25');
                break;
            case 'mandatory_form':
                $this->form();
                break;
            case 'homework':
                $this->homework($student);
                break;
            case 'expiring':
                $this->package($student)->update(['expires_at' => now('UTC')->addDays(7)]);
                break;
            case 'continuation':
                [$course, $lesson] = $this->learning($student);
                LearningVisit::factory()->create(['student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id]);
                break;
            case 'learning':
                $this->learning($student);
                break;
            case 'book':
                $this->package($student);
                break;
            case 'later':
                $this->booking($student, 60 * 72);
                break;
            case 'resource':
                ResourceAssignment::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'resource_id' => ResourceFactory::new()->create()->id]);
                break;
            case 'diagnostic':
                SessionType::factory()->create(['slug' => 'diagnostic-session', 'public_bookable' => true]);
                break;
            case 'feedback':
                $this->homework($student)->update(['status' => 'reviewed', 'feedback' => 'Keep the final vowel clear.']);
                break;
        }
        $tables = ['lms_learning_visits', 'lms_lesson_progress', 'booking_holds', 'session_ledger_entries', 'homework_submissions', 'form_submissions', 'student_notifications'];
        $before = collect($tables)->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all();
        $response = $this->page('home-'.$kind)->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->assertSame($kind, $response->viewData('nextAction')['kind']);
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        $this->assertSame(1, substr_count($response->getContent(), 'data-home-primary'));
        $this->assertDatabaseCount('lms_lesson_progress', 0);
    }

    public function test_competing_priorities_and_ties_refresh_on_consecutive_requests(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $later = $this->booking($student, 60 * 72);
        $second = $this->booking($student, 60 * 72);
        $homework = $this->homework($student, '2026-10-26');
        $other = $this->homework($student, '2026-10-26');
        $form = $this->form();
        $this->assertSame('homework:'.$homework->id, $this->page('home-competing')->viewData('nextAction')['source']);
        $homework->update(['status' => 'submitted']);
        $this->assertSame('homework:'.$other->id, $this->page()->viewData('nextAction')['source']);
        $other->update(['status' => 'reviewed']);
        $this->assertSame('mandatory_form', $this->page()->viewData('nextAction')['kind']);
        $this->post(route('student.forms.save', $form->slug), ['intent' => 'submit', 'answers' => ['goal' => 'Travel']])->assertRedirect();
        $this->assertSame('lesson:'.$later->id, $this->page()->viewData('nextAction')['source']);
        $later->update(['status' => 'cancelled']);
        $this->assertSame($second->id, $this->page()->viewData('nextLesson')->id);
        $second->update(['status' => 'completed']);
        $this->assertNull($this->page()->viewData('nextLesson'));
    }

    public function test_join_uses_meeting_policy_and_ongoing_confirmed_lessons_do_not_disappear(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $booking = $this->booking($student, -10, true);
        $this->page('home-ongoing')->assertSee('In progress')->assertSee('Join Lesson')->assertSee($booking->meeting_url_snapshot);
        $booking->update(['status' => 'held']);
        $this->page()->assertDontSee('Join Lesson')->assertViewHas('nextLesson', null);
        $booking->update(['status' => 'confirmed', 'end_at_utc' => now('UTC')->subMinute()]);
        $this->page()->assertDontSee('Join Lesson')->assertViewHas('nextLesson', null);
    }

    #[TestWith(['held'])]
    #[TestWith(['pending_confirmation'])]
    #[TestWith(['completed'])]
    public function test_existing_appointment_context_never_invents_a_diagnostic_invitation(string $status): void
    {
        $student = $this->student();
        $this->signIn($student);
        $type = SessionType::factory()->create(['slug' => 'diagnostic-session', 'public_bookable' => true]);
        $booking = $this->booking($student, 60 * 72);
        $booking->update(['status' => $status, 'session_type_id' => $type->id]);
        $this->page('home-diagnostic-'.$status)->assertDontSee('Book Your Diagnostic')->assertDontSee('written roadmap is ready');
    }

    #[TestWith(['paused'])]
    #[TestWith(['expired'])]
    #[TestWith(['unfunded'])]
    #[TestWith(['unclassified'])]
    #[TestWith(['incompatible'])]
    public function test_ineligible_or_incompatible_credits_never_offer_booking(string $state): void
    {
        $student = $this->student();
        $this->signIn($student);
        $package = in_array($state, ['unfunded', 'unclassified'], true)
            ? StudentPackage::factory()->create(['student_id' => $student->id, 'validity_days' => $state === 'unfunded' ? 90 : null, 'identity_state' => $state === 'unclassified' ? 'legacy_unclassified' : 'classified'])
            : $this->package($student);
        match ($state) {
            'paused' => $package->update(['paused_at' => now('UTC')]),
            'expired' => $package->update(['expires_at' => now('UTC')]),
            'unfunded' => true,
            'unclassified' => true,
            'incompatible' => SessionType::query()->where('funding_mode', 'package')->update(['required_entitlement_units' => 8]),
        };
        $this->page()->assertDontSee('Book Your Next Lesson')->assertDontSee('Choose a Lesson Time');
    }

    public function test_owned_unread_updates_are_limited_and_unsafe_destinations_fall_back_without_marking_read(): void
    {
        $student = $this->student();
        $this->signIn($student);
        foreach (['/student/learning#homework-1', 'https://external.example/private', '/student/logout', '/student/notes'] as $i => $link) {
            StudentNotification::factory()->create(['student_id' => $student->id, 'title' => 'Owned update '.$i, 'link' => $link, 'read_at' => null]);
        }
        StudentNotification::factory()->create(['title' => 'Other learner secret']);
        StudentNotification::factory()->create(['student_id' => $student->id, 'title' => 'Already read', 'read_at' => now()]);
        $response = $this->page('home-updates')->assertDontSee('Other learner secret')->assertDontSee('Already read')->assertDontSee('Owned update 0')->assertDontSee('https://external.example/private');
        $this->assertCount(3, $response->viewData('updates'));
        $this->assertSame(route('student.notifications.index'), $response->viewData('updates')[1]['url']);
        $this->assertSame(4, StudentNotification::query()->where('student_id', $student->id)->whereNull('read_at')->count());
    }

    public function test_home_omits_foreign_hidden_and_revoked_learning_and_never_claims_a_visit_is_completion(): void
    {
        $student = $this->student();
        $other = $this->student('Other');
        $this->homework($other)->update(['title' => 'Other student private homework']);
        $this->homework($student)->update(['title' => 'Hidden homework', 'student_visible' => false]);
        [$course, $lesson] = $this->learning($student);
        LearningVisit::factory()->create(['student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id]);
        $this->signIn($student);
        $this->page()->assertDontSee('Other student private homework')->assertDontSee('Hidden homework')->assertSee('Continue Learning');
        $this->assertDatabaseCount('lms_lesson_progress', 0);
        $course->forceFill(['status' => 'draft'])->save();
        $this->page()->assertDontSee('Continue Learning')->assertDontSee('Start Learning');
    }

    public function test_questionnaire_draft_completion_and_publication_changes_are_current_and_return_home(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $form = $this->form();
        $this->post(route('student.forms.save', $form->slug), ['intent' => 'draft', 'answers' => ['goal' => 'Travel']])->assertRedirect();
        $this->page()->assertSee('Continue Questionnaire');
        $this->post(route('student.forms.save', $form->slug), ['intent' => 'submit', 'answers' => ['goal' => 'Travel']])->assertRedirect();
        $this->page()->assertSee('View Answers')->assertViewHas('nextAction', fn (array $action): bool => $action['kind'] !== 'mandatory_form');
        $builder = app(FormBuilderService::class);
        $updated = $builder->update($form->id, ['title' => $form->title], [['question_key' => 'goal', 'label' => 'Updated goal', 'question_type' => 'short_text']], $form->active_version_id, $form->lock_version);
        $builder->publish($updated->id, $updated->active_version_id, $updated->lock_version);
        $this->page('home-questionnaire-update')->assertSee('Update Questionnaire')->assertSee('View Answers');
        $this->page('questionnaire', route('student.forms.show', $form->slug))->assertSee('Back to Home')->assertSee(route('student.home').'#questionnaires');
    }

    public function test_representative_home_states_are_captured_with_fresh_final_assets(): void
    {
        $student = $this->student();
        $this->signIn($student);
        Setting::set('whatsapp_portal', true);
        Setting::set('whatsapp_url', 'https://wa.me/12025550142');
        $this->page('home-new');
        StudentBin::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'title' => 'Shared greeting note']);
        $this->page('home-note-only');
        $this->package($student);
        $this->page('home-credits-only');
        $this->booking($student, 60 * 72);
        $this->homework($student, '2026-10-26');
        $this->form();
        $this->learning($student);
        $this->page('home-active');
        $student->update(['first_name' => str_repeat('Aminaأمينة', 10)]);
        $this->page('home-long-name');
        $student->update(['first_name' => 'أمينة Amina']);
        $this->page('home-mixed');
        $this->page('sessions-active', route('student.dashboard'))->assertDontSee('Your next step')->assertDontSee('Your Learning Progress')->assertSee('id="forms-title"', false)->assertSee('id="credits-title"', false)->assertSee('id="upcoming-title"', false)->assertSee('id="history-title"', false);
        $this->page('profile', route('student.profile'));
    }

    public function test_joinable_lesson_wins_over_earlier_unjoinable_lesson_and_due_homework(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $earlier = $this->booking($student, -20);
        $joinable = $this->booking($student, -10, true);
        $this->homework($student, '2026-10-25');
        $page = $this->page();
        $this->assertSame('lesson:'.$joinable->id, $page->viewData('nextAction')['source']);
        $this->assertSame($earlier->id, $page->viewData('nextLesson')->id);
        $joinable->update(['meeting_url_snapshot' => null]);
        $this->assertSame('due_homework', $this->page()->viewData('nextAction')['kind']);
    }

    public function test_due_dates_use_the_display_calendar_and_do_not_invent_times_for_undated_work(): void
    {
        $this->travelTo('2026-10-26 23:30:00 UTC');
        $student = $this->student();
        $this->signIn($student);
        $this->withSession(['student_display_timezone' => 'Asia/Tokyo']);
        $item = $this->homework($student, '2026-10-27');
        $this->assertSame('due_homework', $this->page()->viewData('nextAction')['kind']);
        $item->update(['due_date' => null]);
        $this->page()->assertViewHas('nextAction', fn (array $action): bool => $action['kind'] === 'homework' && ! str_contains($action['detail'], 'Due'));
    }

    public function test_expiring_credits_precede_continuation_and_ledger_changes_are_visible_next_request(): void
    {
        $student = $this->student();
        $this->signIn($student);
        [$course, $lesson] = $this->learning($student);
        LearningVisit::factory()->create(['student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id]);
        $package = $this->package($student);
        $package->update(['expires_at' => now('UTC')->addDays(7)]);
        $this->assertSame('expiring', $this->page()->viewData('nextAction')['kind']);
        $package->update(['paused_at' => now('UTC')]);
        $this->assertSame('continuation', $this->page()->viewData('nextAction')['kind']);
        $package->update(['paused_at' => null]);
        app(StudentLedgerService::class)->adjustCredits($package, -4, 'Synthetic correction', 'home-balance-correction', allocationId: $package->entitlements()->firstOrFail()->id);
        $this->page()->assertViewHas('formats', fn ($formats): bool => $formats->isEmpty());
    }

    public function test_mandatory_forms_and_resources_use_stable_order_and_reviewed_resources_leave_the_action(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $zeta = $this->form();
        $zeta->update(['title' => 'Zeta questionnaire']);
        $alpha = $this->form();
        $alpha->update(['title' => 'Alpha questionnaire']);
        $this->assertSame('form:'.$alpha->id, $this->page()->viewData('nextAction')['source']);
        $alpha->update(['status' => 'draft']);
        $zeta->update(['status' => 'draft']);
        $first = ResourceAssignment::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'resource_id' => ResourceFactory::new()->create()->id]);
        $second = ResourceAssignment::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'resource_id' => ResourceFactory::new()->create()->id]);
        $this->assertSame('resource:'.$first->id, $this->page()->viewData('nextAction')['source']);
        $first->update(['reviewed_at' => now()]);
        $this->assertSame('resource:'.$second->id, $this->page()->viewData('nextAction')['source']);
    }

    public function test_recent_visit_order_and_current_grant_changes_control_continuation(): void
    {
        $student = $this->student();
        $this->signIn($student);
        [$firstCourse, $firstLesson] = $this->learning($student);
        [$secondCourse, $secondLesson] = $this->learning($student);
        LearningVisit::factory()->create(['student_id' => $student->id, 'course_id' => $firstCourse->id, 'lesson_id' => $firstLesson->id]);
        LearningVisit::factory()->create(['student_id' => $student->id, 'course_id' => $secondCourse->id, 'lesson_id' => $secondLesson->id]);
        $this->assertSame(route('student.learning.lessons.show', [$secondCourse, $secondLesson]), $this->page()->viewData('nextAction')['url']);
        $grant = AccessGrant::query()->where('student_id', $student->id)->where('course_id', $secondCourse->id)->firstOrFail();
        app(LmsAccessOperations::class)->change(Administrator::factory()->create(), $grant, 'revoke', [], $grant->lock_version, 'home-revoke');
        $this->assertSame(route('student.learning.lessons.show', [$firstCourse, $firstLesson]), $this->page()->viewData('nextAction')['url']);
        $this->assertDatabaseCount('lms_lesson_progress', 0);
    }

    public function test_home_never_queries_payment_or_refund_histories(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $this->package($student);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        $this->page();
        $this->assertSame([], array_values(array_filter($queries, fn (string $sql): bool => str_contains($sql, 'payment_records') || str_contains($sql, 'payment_refunds'))));
    }

    public function test_recoverable_learning_timeout_is_labeled_unavailable_and_never_treated_as_empty_onboarding(): void
    {
        $student = $this->student();
        $this->signIn($student);
        SessionType::factory()->create(['slug' => 'diagnostic-session', 'public_bookable' => true]);
        $previous = new \PDOException('Synthetic lock wait timeout');
        $previous->errorInfo = ['HY000', 1205, 'Lock wait timeout exceeded'];
        $exception = new QueryException('mysql', 'select synthetic', [], $previous);
        $this->mock(StudentLearningService::class, function ($mock) use ($exception): void {
            $mock->shouldReceive('overview')->once()->andThrow($exception);
        });
        $this->page('home-learning-unavailable')->assertSee('Learning is temporarily unavailable')->assertSee('Open My Learning')->assertDontSee('Book Your Diagnostic');
    }

    #[TestWith(['scheduled'])]
    #[TestWith(['expired'])]
    public function test_inactive_learning_windows_do_not_offer_an_action(string $state): void
    {
        $student = $this->student();
        $this->signIn($student);
        [$course] = $this->learning($student);
        $grant = AccessGrant::query()->where('student_id', $student->id)->firstOrFail();
        $admin = Administrator::factory()->create();
        if ($state === 'scheduled') {
            app(LmsAccessOperations::class)->change($admin, $grant, 'set_start', ['starts_at' => '2026-10-28T09:00:00+00:00'], $grant->lock_version, 'home-schedule');
        } else {
            app(LmsAccessOperations::class)->change($admin, $grant, 'set_expiration', ['expires_at' => '2026-10-26T09:01:00+00:00'], $grant->lock_version, 'home-expire');
            $this->travelTo('2026-10-26 09:02:00 UTC');
        }
        $this->page()->assertDontSee('Start Learning')->assertDontSee('Continue Learning');
    }

    public function test_available_private_learning_assignment_uses_its_authorized_destination(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $course = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $student->id, 'title' => 'Personal pronunciation practice']);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>Practise carefully.</p>']]);
        $assignment = app(LmsAccessOperations::class)->assign(Administrator::factory()->create(), $student, $course, [], 'home-assignment');
        $this->page('home-assignment')->assertViewHas('nextAction', fn (array $action): bool => $action['kind'] === 'learning' && $action['url'] === route('student.learning.assignments.show', $assignment));
    }

    public function test_expiring_allocations_use_deadline_then_creation_then_id_without_combining_types(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $first = $this->package($student);
        $first->update(['expires_at' => now('UTC')->addDays(7)]);
        $second = app(StudentLedgerService::class)->createPackage($student, 'Second personal purchase', 2, '60.00', '0.00', 'USD', null, 'home-second-package', entitlementCode: 'one_hour');
        $second->update(['expires_at' => now('UTC')->addDays(3)]);
        $this->assertStringContainsString('29 Oct 2026', $this->page()->viewData('nextAction')['detail']);
        $second->update(['expires_at' => now('UTC')->addDays(7)]);
        $this->assertStringContainsString('Personal Arabic lessons', $this->page()->viewData('nextAction')['detail']);
        $twoHour = app(StudentLedgerService::class)->createPackage($student, 'Two hour purchase', 3, '150.00', '0.00', 'USD', null, 'home-two-hour-package', entitlementCode: 'two_hour');
        $credits = $this->page()->viewData('credits');
        $this->assertSame(6, $credits['one_hour']['remaining']);
        $this->assertSame(3, $credits['two_hour']['remaining']);
    }

    public function test_current_public_hold_suppresses_duplicate_diagnostic_invitation_without_changing_the_hold(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $this->withSession(['_visitor_token' => 'home-hold-visitor']);
        $type = SessionType::factory()->create(['slug' => 'diagnostic-session', 'public_bookable' => true]);
        AvailabilityRule::query()->create(['weekday' => 4, 'start_time' => '10:00', 'end_time' => '16:00', 'buffer_minutes' => 10, 'min_notice_hours' => 0, 'max_horizon_days' => 60, 'enabled' => true]);
        $start = CarbonImmutable::parse('2026-11-05 08:00:00 UTC');
        $hold = app(BookingHoldService::class)->acquireHold('home-hold-visitor', session()->getId(), $type, $start, $start->addHour());
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->page('home-active-hold')->assertDontSee('Book Your Diagnostic')->assertViewHas('nextLesson', null);
        $this->assertSame('active', $hold->fresh()->status);
        $this->assertDatabaseCount('booking_holds', 1);
    }

    public function test_future_diagnostic_is_a_confirmed_lesson_and_never_a_second_invitation(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $type = SessionType::factory()->create(['slug' => 'diagnostic-session', 'title' => 'Diagnostic & Learning Roadmap', 'public_bookable' => true]);
        $booking = $this->booking($student, 72 * 60);
        $booking->update(['session_type_id' => $type->id]);
        $this->page('home-future-diagnostic')->assertSee('Diagnostic &amp; Learning Roadmap', false)->assertDontSee('Book Your Diagnostic')->assertViewHas('nextLesson', fn (Booking $next): bool => $next->id === $booking->id);
    }

    public function test_existing_eligible_package_does_not_make_a_learner_a_diagnostic_prospect_when_formats_are_unavailable(): void
    {
        $student = $this->student();
        $this->signIn($student);
        $this->package($student);
        SessionType::query()->where('funding_mode', 'package')->update(['student_discoverable' => false]);
        SessionType::factory()->create(['slug' => 'diagnostic-session', 'public_bookable' => true]);
        $this->page()->assertDontSee('Book Your Diagnostic')->assertDontSee('Book Your Next Lesson');
    }

    private function student(string $first = 'Amina'): Student
    {
        return Student::factory()->verified()->create(['first_name' => $first, 'preferred_timezone' => 'Africa/Cairo']);
    }

    private function signIn(Student $student): void
    {
        $this->actingAs($student, 'student')->withSession(['student_id' => $student->id, 'student_auth_expires_at' => now('UTC')->addHours(3)->toIso8601String(), 'student_display_timezone' => 'Africa/Cairo']);
    }

    private function booking(Student $student, int $minutes, bool $joinable = false): Booking
    {
        return Booking::factory()->create(['student_id' => $student->id, 'start_at_utc' => now('UTC')->addMinutes($minutes), 'end_at_utc' => now('UTC')->addMinutes($minutes + 60),
            'meeting_room_id' => $joinable ? MeetingRoom::factory()->create()->id : null, 'meeting_url_snapshot' => $joinable ? 'https://example.org/synthetic-lesson' : null]);
    }

    private function homework(Student $student, ?string $due = null): Homework
    {
        return Homework::factory()->create(['student_id' => $student->id, 'student_visible' => true, 'due_date' => $due, 'title' => 'Practise a short introduction', 'instructions' => 'Read your greeting aloud, then write two sentences.']);
    }

    private function package(Student $student): StudentPackage
    {
        $type = $this->oneHourPackageLesson();
        $type->update(['student_discoverable' => true, 'title' => 'Personal Arabic lesson']);

        return app(StudentLedgerService::class)->createPackage($student, 'Personal Arabic lessons', 4, '100.00', '0.00', 'USD', null, 'home-package-'.$student->id, entitlementCode: 'one_hour');
    }

    /** @return array{Course,Lesson} */
    private function learning(Student $student): array
    {
        $course = Course::factory()->published()->create(['title' => 'Arabic foundations · أُسُس العَرَبِيَّة']);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id, 'title' => 'Everyday greetings · مَرْحَبًا']);
        LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'rich_text', 'status' => 'ready', 'payload' => ['html' => '<p>Practise a warm greeting.</p>']]);
        app(LmsAccessOperations::class)->grant(Administrator::factory()->create(), $student, $course, [], 'home-learning-'.$course->id);

        return [$course, $lesson];
    }

    private function form(): Form
    {
        $builder = app(FormBuilderService::class);
        $form = $builder->create(['title' => 'Your Learning Goals', 'slug' => 'home-goals-'.Form::query()->count(), 'trigger' => 'none', 'is_mandatory' => true, 'can_edit_after_submission' => false], [['question_key' => 'goal', 'label' => 'Your goal', 'question_type' => 'short_text']], Administrator::factory()->create());

        return $builder->publish($form->id, $form->active_version_id, $form->lock_version);
    }

    private function page(string $name = '', ?string $url = null): TestResponse
    {
        Livewire::flushState();
        $response = $this->get($url ?? route('student.home'))->assertOk();
        $path = getenv('STUDENT_HOME_EVIDENCE_PATH');
        if ($name !== '' && is_string($path) && $path !== '') {
            $base = realpath(storage_path(str_contains($path, 'student-portal-phase03-evidence') ? 'app/private/student-portal-phase03-evidence' : 'app/private/student-portal-phase02-evidence'));
            $resolved = realpath($path);
            if (! $base || ! $resolved || ! str_starts_with($resolved, $base.DIRECTORY_SEPARATOR)) {
                throw new \RuntimeException('Evidence must stay in the authorized private Phase 02 run.');
            }
            $html = preg_replace('/(data-csrf=")[^"]*(")/i', '$1REDACTED$2', $response->getContent());
            file_put_contents($resolved.'/'.$name.'.html', preg_replace('/(<(?:input|meta)\b[^>]*\bname="(?:_token|csrf-token)"[^>]*?\b(?:value|content)=")[^"]*(")/i', '$1REDACTED$2', $html));
            file_put_contents($resolved.'/responses.jsonl', json_encode(['file' => $name.'.html', 'url' => $url ?? route('student.home'), 'status' => $response->getStatusCode(), 'method' => 'Laravel authenticated request test']).PHP_EOL, FILE_APPEND);
        }

        return $response;
    }
}

<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Booking\Models\BookingEvent;
use App\Domains\Booking\Models\BookingPolicyDecision;
use App\Domains\Booking\Models\SessionReschedule;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonBlock;
use App\Domains\Lms\Models\Section;
use App\Domains\Reporting\Services\ExportService;
use App\Domains\Reporting\Services\StudentInsightsContext;
use App\Domains\Reporting\Services\StudentInsightsReadModel;
use App\Domains\Reporting\Services\TutorInsightsContext;
use App\Domains\Reporting\Services\TutorInsightsReadModel;
use App\Domains\Students\Models\EntitlementType;
use App\Domains\Students\Models\FeedbackConversation;
use App\Domains\Students\Models\FeedbackEvent;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentLedgerService;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TutorInsightsTest extends TestCase
{
    use RefreshDatabase;

    private function prepare(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-03 12:00:00', 'UTC'));
        Http::preventStrayRequests();
        Mail::fake();
        Notification::fake();
    }

    private function context(array $filters = [], ?Student $student = null): TutorInsightsContext
    {
        return TutorInsightsContext::fromRequest(request()->duplicate(array_replace(['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'timezone' => 'UTC'], $filters)), $student);
    }

    private function booking(?Student $student, string $start = '2026-11-01 09:00:00', string $end = '2026-11-01 10:00:00', string $status = 'completed', array $attributes = []): Booking
    {
        return Booking::factory()->create(array_replace(['student_id' => $student?->id, 'start_at_utc' => $start, 'end_at_utc' => $end, 'status' => $status], $attributes));
    }

    private function read(): TutorInsightsReadModel
    {
        return app(TutorInsightsReadModel::class);
    }

    private function admin(string $role = 'admin'): Administrator
    {
        $admin = Administrator::factory()->create(['role' => $role]);
        $this->actingAs($admin, 'web');

        return $admin;
    }

    public function test_teaching_uses_bookings_once_independent_of_completing_staff_and_reconciles_with_student_insights(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student);
        $this->booking($student);
        $this->booking(null);
        foreach (['cancelled', 'no_show', 'pending', 'held', 'confirmed'] as $status) {
            $this->booking($student, status: $status);
        }

        $metrics = $this->read()->metrics($this->context());

        $this->assertSame(3, $metrics['appointments_completed']['value']);
        $this->assertSame(10800, $metrics['recorded_teaching_seconds']['value']);
        $this->assertSame(1, $metrics['identified_students_taught']['value']);
        $this->assertSame(2, $metrics['linked_completed_appointments']['value']);
        $this->assertSame(1, $metrics['unlinked_completed_appointments']['value']);
        $this->assertSame(2, $metrics['sessions_per_identified_student']['value']);
        $this->assertSame(1, $metrics['teaching_days']['value']);
        $this->admin();
        $first = $this->get(route('admin.tutor-insights.index'))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->viewData('metrics');
        $this->admin('super_admin');
        $this->assertSame($first, $this->get(route('admin.tutor-insights.index'))->assertOk()->viewData('metrics'));
        $individual = $this->read()->metrics($this->context(student: $student));
        $source = app(StudentInsightsReadModel::class)->metrics(StudentInsightsContext::fromRequest(request()->duplicate(['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'timezone' => 'UTC']), $student, true));
        $this->assertSame($source['sessions_completed']['value'], $individual['appointments_completed']['value']);
        $this->assertSame($source['session_duration_seconds']['value'], $individual['recorded_teaching_seconds']['value']);
        $this->assertSame($source['recorded_learning_days']['value'], $individual['teaching_days']['value']);
    }

    public function test_duration_is_unavailable_then_partial_and_sums_past_24_hours_without_nominal_or_buffer_time(): void
    {
        $this->prepare();
        $this->booking(null, end: '2026-11-01 09:00:00');
        $this->booking(null, end: '2026-11-01 08:00:00');
        $context = $this->context();

        $unknown = $this->read()->metrics($context);

        $this->assertSame(2, $unknown['appointments_completed']['value']);
        $this->assertNull($unknown['recorded_teaching_seconds']['value']);
        $this->assertSame('unavailable', $unknown['recorded_teaching_seconds']['state']);
        $this->assertSame('not_applicable', $unknown['sessions_per_identified_student']['state']);
        $this->booking(null, '2026-11-02 00:00:00', '2026-11-03 02:01:02');
        $partial = $this->read()->metrics($context)['recorded_teaching_seconds'];
        $this->assertSame(93662, $partial['value']);
        $this->assertSame(2, $partial['invalid']);
        $this->assertSame('partial', $partial['state']);
        $this->assertSame('26:01:02', TutorInsightsReadModel::display($partial));
    }

    public function test_operational_actions_use_action_dates_and_feedback_units_stay_separate(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student, '2026-12-01 09:00:00', '2026-12-01 10:00:00', 'cancelled');
        BookingPolicyDecision::factory()->create(['booking_id' => $booking->id, 'actor_type' => 'student', 'created_at' => '2026-11-02 09:00:00']);
        SessionReschedule::create(['booking_id' => $booking->id, 'actor_type' => 'system', 'old_start_at_utc' => '2026-12-01 08:00:00', 'new_start_at_utc' => '2026-12-01 09:00:00', 'old_timezone' => 'UTC', 'new_timezone' => 'UTC', 'idempotency_key' => (string) Str::uuid(), 'created_at' => '2026-11-02 09:00:00']);
        $conversation = FeedbackConversation::factory()->create(['student_id' => $student->id, 'created_at' => '2026-11-01 08:00:00']);
        foreach ([['message', null, null], ['status', 'open', 'resolved'], ['status', 'resolved', 'open'], ['status', 'open', 'resolved'], ['status', 'resolved', 'resolved']] as $index => [$kind, $from, $to]) {
            FeedbackEvent::factory()->create(['conversation_id' => $conversation->id, 'actor_type' => 'administrator', 'kind' => $kind, 'from_status' => $from, 'to_status' => $to, 'sequence' => $index + 1, 'body' => 'PRIVATE-MESSAGE', 'created_at' => '2026-11-02 10:00:00']);
        }

        $metrics = $this->read()->metrics($this->context(['view' => 'operations']));

        $this->assertSame(1, $metrics['cancellation_actions']['value']);
        $this->assertSame(1, $metrics['reschedule_actions']['value']);
        $this->assertSame(1, $metrics['feedback_conversations_received']['value']);
        $this->assertSame(1, $metrics['feedback_administrator_messages']['value']);
        $this->assertSame(2, $metrics['feedback_resolution_transitions']['value']);
        $this->assertSame(0, $this->read()->outcomes($this->context())['cancelled']);
        $this->admin();
        $this->get(route('admin.tutor-insights.index', ['view' => 'operations']))->assertSeeText('Operational contributing records')->assertDontSee('PRIVATE-MESSAGE');
        $student->update(['suspended_at' => now()]);
        $this->assertSame(0, $this->read()->metrics($this->context(['view' => 'operations']))['feedback_administrator_messages']['value']);
        $this->assertSame(1, $this->read()->metrics($this->context(['view' => 'operations']))['cancellation_actions']['value']);
    }

    public function test_full_intervals_use_start_attribution_and_half_open_day_month_and_year_boundaries(): void
    {
        $this->prepare();
        $this->booking(null, '2026-10-31 23:30:00', '2026-11-01 02:00:00');
        $this->booking(null, '2026-11-01 00:00:00', '2026-11-01 01:00:00');
        $this->booking(null, '2026-12-31 23:30:00', '2027-01-01 02:30:00');

        $october = $this->read()->metrics($this->context(['start_date' => '2026-10-31', 'end_date' => '2026-10-31']));

        $this->assertSame(1, $october['appointments_completed']['value']);
        $this->assertSame(9000, $october['recorded_teaching_seconds']['value']);
        $this->assertSame(1, $this->read()->metrics($this->context())['appointments_completed']['value']);
        $this->travelTo(CarbonImmutable::parse('2027-01-03', 'UTC'));
        $this->assertSame(10800, $this->read()->metrics($this->context(['start_date' => '2026-12-31', 'end_date' => '2026-12-31']))['recorded_teaching_seconds']['value']);
    }

    public function test_dst_uses_utc_elapsed_seconds_and_equal_local_date_previous_windows(): void
    {
        $this->prepare();
        $this->booking(null, '2026-03-29 00:30:00', '2026-03-29 02:30:00');
        $spring = $this->context(['start_date' => '2026-03-29', 'end_date' => '2026-03-29', 'timezone' => 'Europe/Berlin']);

        $this->assertSame(23 * 3600, (int) $spring->period->from->diffInSeconds($spring->period->until));
        $this->assertSame(7200, $this->read()->metrics($spring)['recorded_teaching_seconds']['value']);
        $this->booking(null, '2026-10-25 00:30:00', '2026-10-25 02:30:00');
        $autumn = $this->context(['start_date' => '2026-10-25', 'end_date' => '2026-10-25', 'timezone' => 'Europe/Berlin']);
        $this->assertSame(25 * 3600, (int) $autumn->period->from->diffInSeconds($autumn->period->until));
        $this->assertSame('2026-10-24', $autumn->previous()->period->startDate);
        $this->assertSame($autumn->period->generatedAt, $autumn->previous()->period->generatedAt);
        $this->assertSame(7200, $this->read()->metrics($autumn)['recorded_teaching_seconds']['value']);
    }

    public function test_upcoming_bounds_and_outcome_scope_are_independent_of_historical_dates(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $type = $this->booking($student, '2026-11-03 12:00:00', '2026-11-03 13:00:00', 'confirmed')->session_type_id;
        $this->booking($student, '2026-12-03 00:00:00', '2026-12-03 01:00:00', 'confirmed', ['session_type_id' => $type]);
        $this->booking($student, '2026-11-03 11:59:59', '2026-11-03 13:00:00', 'confirmed', ['session_type_id' => $type]);
        $this->booking(null, status: 'cancelled');
        $context = $this->context(['start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'session_type_id' => $type], $student);

        $this->assertSame(1, $this->read()->appointments($context, true)->count());
        $this->assertSame(0, $this->read()->appointments($context)->count());
        $canceled = $this->context(['outcome' => 'cancelled']);
        $this->assertSame('not_applicable', $this->read()->metrics($canceled)['appointments_completed']['state']);
        $this->assertSame(0, $this->read()->appointments($canceled, true)->count());
        $this->admin();
        $this->get(route('admin.tutor-insights.index', ['outcome' => 'cancelled']))->assertSeeText('Teaching metrics are not applicable')->assertSeeText('outside the selected appointment outcome scope');
    }

    public function test_inactive_diagnostic_types_are_source_classification_and_breakdown_totals_reconcile(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $diagnostic = $this->booking($student);
        $type = $diagnostic->sessionType;
        $type->update(['slug' => 'diagnostic-session', 'active' => false, 'funding_mode' => 'package', 'required_entitlement_type_id' => EntitlementType::where('code', 'one_hour')->sole()->id, 'required_entitlement_units' => 1]);
        $ledger = app(StudentLedgerService::class);
        $ledger->createPackage($student, 'Synthetic instructional package', 2, '80.00', '0.00', 'USD', null, 'tutor-insights-package', entitlementCode: 'one_hour');
        DB::transaction(fn () => $ledger->consumeForBooking($diagnostic->fresh(), 'tutor-insights-package-booking'));
        $this->assertSame('package', $diagnostic->fresh()->funding_mode);
        $this->booking(null);

        $metrics = $this->read()->metrics($this->context());

        $this->assertSame(2, $metrics['appointments_completed']['value']);
        $this->assertSame(1, $metrics['diagnostic_type_completed']['value']);
        $this->assertSame(2, array_sum(array_map(fn ($row): int => (int) $row->appointments, $this->read()->types($this->context()))));
        $this->assertArrayHasKey($type->id, $this->read()->sessionTypes());
    }

    public static function roleCases(): array
    {
        return [['admin', true], ['super_admin', true], ['assistant', false]];
    }

    #[DataProvider('roleCases')]
    public function test_roles_are_enforced_on_report_selector_and_every_export_dataset(string $role, bool $allowed): void
    {
        $this->prepare();
        $this->admin($role);

        foreach (['index', 'students'] as $endpoint) {
            $response = $this->getJson(route('admin.tutor-insights.'.$endpoint));
            $allowed ? $response->assertOk() : $response->assertForbidden();
        }
        foreach (self::exportCases() as [$view, $dataset, $format]) {
            $response = $this->getJson(route('admin.tutor-insights.export', compact('view', 'dataset', 'format')));
            $allowed ? $response->assertOk() : $response->assertForbidden();
            if ($allowed && $format === 'xlsx') {
                ob_start();
                $response->baseResponse->sendContent();
                ob_end_clean();
            }
        }
    }

    public static function invalidFilters(): array
    {
        return [
            [['view' => 'operations', 'outcome' => 'completed'], 'outcome'],
            [['view' => 'operations', 'session_type_id' => 1], 'session_type_id'],
            [['view' => 'operations', 'grouping' => 'weekly'], 'grouping'],
            [['activity' => 'feedback'], 'activity'],
            [['view' => 'operations', 'dataset' => 'appointments'], 'dataset'],
            [['dataset' => 'operations'], 'dataset'],
            [['timezone' => 'Invalid/zone'], 'timezone'],
            [['range' => 'custom'], 'start_date'],
            [['range' => 'custom', 'start_date' => '2026-11-04', 'end_date' => '2026-11-05'], 'start_date'],
            [['range' => 'custom', 'start_date' => '2020-01-01', 'end_date' => '2026-11-01'], 'end_date'],
            [['range' => 'custom', 'start_date' => '2026-11-02', 'end_date' => '2026-11-01'], 'end_date'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_and_incompatible_filters_return_422_on_screen_and_export(array $filters, string $field): void
    {
        $this->prepare();
        $this->admin();

        foreach (['index', 'export'] as $endpoint) {
            $this->getJson(route('admin.tutor-insights.'.$endpoint, $filters))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public static function exportCases(): array
    {
        $cases = [];
        foreach (['teaching' => ['summary', 'appointments', 'upcoming'], 'operations' => ['summary', 'operations']] as $view => $datasets) {
            foreach ($datasets as $dataset) {
                foreach (['csv', 'xlsx'] as $format) {
                    $cases[$view.' '.$dataset.' '.$format] = [$view, $dataset, $format];
                }
            }
        }

        return $cases;
    }

    #[DataProvider('exportCases')]
    public function test_authenticated_exports_use_real_sources_numeric_values_and_safe_columns(string $view, string $dataset, string $format): void
    {
        $this->prepare();
        $this->admin();
        $student = Student::factory()->verified()->create();
        $booking = $this->booking($student, attributes: ['notes' => 'PRIVATE-NOTES', 'cancellation_reason' => 'PRIVATE-REASON']);
        $this->booking($student, '2026-11-04 09:00:00', '2026-11-04 10:00:00', 'confirmed');
        BookingPolicyDecision::factory()->create(['booking_id' => $booking->id, 'created_at' => '2026-11-02 08:00:00']);

        $response = $this->get(route('admin.tutor-insights.export', compact('view', 'dataset', 'format') + ['student_id' => $student->id]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        if ($format === 'csv') {
            $content = $response->streamedContent();
            $rows = array_map('str_getcsv', array_filter(explode("\n", trim($content))));
        } else {
            $file = $response->baseResponse->getFile()->getPathname();
            $sheet = IOFactory::load($file);
            $rows = $sheet->getActiveSheet()->toArray(null, true, false);
            $content = json_encode($rows);
            $sheet->disconnectWorksheets();
            ob_start();
            $response->baseResponse->sendContent();
            ob_end_clean();
            $this->assertFileDoesNotExist($file);
        }
        $this->assertGreaterThan(1, count($rows));
        $this->assertStringNotContainsString('PRIVATE-', $content);
        $this->assertStringNotContainsString($student->email, $content);
        $this->assertStringContainsString('tutor-insights-v1', $content);
        $rangeColumn = array_search('range', $rows[0], true);
        $this->assertNotFalse($rangeColumn);
        $this->assertSame('month', $rows[1][$rangeColumn]);
        if (in_array($dataset, ['appointments', 'upcoming'], true)) {
            $this->assertSame($format === 'xlsx' ? 3600 : '3600', $rows[1][8]);
        }
        if ($dataset === 'summary') {
            $this->assertContains('previous_period', array_column($rows, 5));
        }
    }

    public function test_merge_and_erasure_exclude_old_ownership_but_retain_suspended_and_unverified_teaching(): void
    {
        $this->prepare();
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $deleted = Student::factory()->verified()->create();
        $suspended = Student::factory()->verified()->create(['suspended_at' => now()]);
        $unverified = Student::factory()->create(['operational_status' => 'withdrawn']);
        foreach ([$primary, $secondary, $deleted, $suspended, $unverified] as $student) {
            $this->booking($student);
        }
        $this->booking($primary)->delete();
        $deleted->delete();
        $admin = $this->admin();

        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $admin->id);

        $metrics = $this->read()->metrics($this->context());
        $this->assertSame(4, $metrics['appointments_completed']['value']);
        $this->assertSame(3, $metrics['identified_students_taught']['value']);
        $this->assertSame(0, $metrics['unlinked_completed_appointments']['value']);
        $this->getJson(route('admin.tutor-insights.index', ['student_id' => $secondary->id]))->assertNotFound();
        $this->getJson(route('admin.tutor-insights.export', ['student_id' => $deleted->id]))->assertNotFound();
        app(StudentPrivacyService::class)->anonymize($primary->id, $admin->id);
        $this->assertSame(2, $this->read()->metrics($this->context())['appointments_completed']['value']);
    }

    public function test_lms_final_reviews_use_explicit_latest_review_dates_and_consistent_private_ownership(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id]);
        $block = LessonBlock::factory()->create(['lesson_id' => $lesson->id, 'kind' => 'assignment', 'status' => 'placeholder']);
        foreach ([['approved', '2026-11-02 09:00:00'], ['needs_revision', '2026-11-02 09:00:00'], ['under_review', '2026-11-02 09:00:00'], ['erased', '2026-11-02 09:00:00'], ['approved', null], ['approved', '2026-11-04 09:00:00'], ['approved', '2026-10-01 09:00:00']] as $index => [$status, $reviewed]) {
            DB::table('lms_assignment_submissions')->insert(['student_id' => $student->id, 'block_id' => $block->id, 'definition_hash' => str_repeat('a', 64), 'request_key' => (string) Str::uuid(), 'number' => $index + 1, 'original_number' => $index + 1, 'definition' => '{}', 'status' => $status, 'kind' => 'text', 'body' => 'PRIVATE-ANSWER', 'feedback' => 'PRIVATE-REVIEW', 'reviewed_at' => $reviewed, 'created_at' => '2026-11-01 09:00:00', 'updated_at' => '2026-11-02 09:00:00']);
        }
        $context = $this->context(['view' => 'operations', 'activity' => 'lms_reviews']);

        $metrics = $this->read()->metrics($context);

        $this->assertSame(2, $metrics['lms_final_review_records']['value']);
        $this->assertCount(1, $metrics);
        DB::table('lms_assignment_submissions')->where('status', 'approved')->where('reviewed_at', '2026-11-02 09:00:00')->update(['reviewed_at' => '2026-10-02 09:00:00']);
        $this->assertSame(1, $this->read()->metrics($context)['lms_final_review_records']['value']);
        DB::table('lms_courses')->where('id', $course->id)->update(['kind' => 'private', 'owner_student_id' => Student::factory()->verified()->create()->id]);
        $this->assertSame(0, $this->read()->metrics($context)['lms_final_review_records']['value']);
    }

    public function test_counts_ignore_mirrored_events_and_do_not_depend_on_staff_actor_identity(): void
    {
        $this->prepare();
        $booking = $this->booking(null);
        foreach (['completed', 'completed', 'cancelled', 'rescheduled'] as $event) {
            BookingEvent::create(['booking_id' => $booking->id, 'event_type' => $event, 'performed_by' => 'admin', 'performed_by_id' => $this->admin()->id, 'created_at' => '2026-11-02 09:00:00']);
        }
        BookingPolicyDecision::factory()->create(['booking_id' => $booking->id, 'created_at' => '2026-11-02 09:00:00']);

        $this->assertSame(1, $this->read()->metrics($this->context())['appointments_completed']['value']);
        $operations = $this->read()->metrics($this->context(['view' => 'operations']));
        $this->assertSame(1, $operations['cancellation_actions']['value']);
        $this->assertSame(0, $operations['reschedule_actions']['value']);
    }

    public function test_trend_and_start_distributions_preserve_whole_period_distinct_students(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $this->booking($student, '2026-10-05 05:59:59', '2026-10-05 07:00:00');
        $this->booking($student, '2026-11-02 18:00:00', '2026-11-02 19:00:00');
        $context = $this->context(['start_date' => '2026-10-01', 'grouping' => 'monthly']);

        $metrics = $this->read()->metrics($context);
        $trends = $this->read()->trends($context);

        $this->assertSame(1, $metrics['identified_students_taught']['value']);
        $this->assertSame(2, array_sum(array_map(fn ($bucket): int => $bucket['metrics']['identified_students_taught']['value'], $trends)));
        $this->assertSame(7201, array_sum(array_map(fn ($bucket): int => $bucket['metrics']['recorded_teaching_seconds']['value'], $trends)));
        $distribution = $this->read()->distributions($context);
        $this->assertSame(2, $distribution['weekday'][0]['metrics']['appointments_completed']['value']);
        $this->assertSame(1, $distribution['time_band'][0]['metrics']['appointments_completed']['value']);
        $this->assertSame(1, $distribution['time_band'][3]['metrics']['appointments_completed']['value']);
        $this->assertTrue($trends[1]['partial']);
    }

    public function test_guests_students_and_suspended_or_deleted_admins_cannot_access_direct_routes(): void
    {
        $this->prepare();
        foreach (['index', 'students', 'export'] as $endpoint) {
            $this->getJson(route('admin.tutor-insights.'.$endpoint))->assertUnauthorized();
        }
        $this->actingAs(Student::factory()->verified()->create(), 'student');
        $this->getJson(route('admin.tutor-insights.export'))->assertUnauthorized();
        $admin = $this->admin();
        $admin->update(['suspended_at' => now()]);
        $this->get(route('admin.tutor-insights.index'))->assertRedirect(route('admin.login'));
        $deleted = $this->admin();
        $deleted->delete();
        $this->get(route('admin.tutor-insights.export'))->assertRedirect(route('admin.login'));
    }

    public function test_viewing_and_exporting_make_no_business_tracking_or_feedback_read_writes(): void
    {
        $this->prepare();
        $this->admin();
        $student = Student::factory()->verified()->create(['preferred_timezone' => 'Europe/Paris']);
        $this->booking($student);
        FeedbackConversation::factory()->create(['student_id' => $student->id]);
        $writes = [];
        DB::listen(function ($query) use (&$writes): void {
            if (preg_match('/^\s*(insert|update|delete|replace)\b/i', $query->sql) && ! preg_match('/(?:sessions|cache|cache_locks)`/i', $query->sql)) {
                $writes[] = $query->sql;
            }
        });

        foreach (['teaching', 'operations'] as $view) {
            $this->get(route('admin.tutor-insights.index', ['view' => $view, 'timezone' => 'Europe/Berlin']))->assertOk();
            $this->get(route('admin.tutor-insights.export', ['view' => $view]))->assertOk()->streamedContent();
        }

        $this->assertSame([], $writes);
        $this->assertSame('Europe/Paris', $student->fresh()->preferred_timezone);
        $this->assertDatabaseCount('feedback_read_states', 0);
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        Http::assertNothingSent();
    }

    public function test_bounded_student_search_and_view_switching_preserve_only_compatible_context(): void
    {
        $this->prepare();
        $this->admin();
        $student = Student::factory()->verified()->create(['first_name' => 'SyntheticMatch']);
        Student::factory()->verified()->count(22)->create(['first_name' => 'SyntheticMatch']);
        $this->get(route('admin.tutor-insights.students'))->assertSeeText('Enter a name or student ID to search.');

        $response = $this->get(route('admin.tutor-insights.students', ['q' => 'SyntheticMatch']))->assertOk();

        $this->assertCount(20, $response->viewData('students'));
        $context = $this->context(['session_type_id' => $this->booking($student)->session_type_id, 'outcome' => 'completed', 'grouping' => 'monthly'], $student);
        $this->assertSame(['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-03', 'timezone' => 'UTC', 'student_id' => $student->id, 'view' => 'operations'], $context->filters('operations'));
    }

    public function test_exports_include_rows_beyond_page_one_and_capacity_failure_keeps_filters(): void
    {
        $this->prepare();
        $this->admin();
        $student = Student::factory()->verified()->create();
        for ($index = 0; $index < 24; $index++) {
            $this->booking($student);
        }
        $filters = ['student_id' => $student->id, 'range' => '7d', 'timezone' => 'Europe/Berlin'];
        $page = $this->get(route('admin.tutor-insights.index', $filters))->assertOk();
        $this->assertCount(20, $page->viewData('appointments'));
        $csv = $this->get(route('admin.tutor-insights.export', $filters + ['dataset' => 'appointments', 'appointments_page' => 2]))->assertOk()->streamedContent();
        $this->assertCount(25, array_filter(explode("\n", trim($csv))));
        $this->mock(ExportService::class)->shouldReceive('export')->once()->andThrow(ValidationException::withMessages(['format' => 'Export CSV with the same filters, or narrow the filters.']));
        $this->get(route('admin.tutor-insights.export', $filters + ['format' => 'xlsx']))->assertRedirect(route('admin.tutor-insights.index', ['range' => '7d', 'timezone' => 'Europe/Berlin', 'student_id' => $student->id, 'view' => 'teaching', 'grouping' => 'weekly']))->assertSessionHasErrors('format');
    }

    public function test_xlsx_neutralizes_formula_like_source_labels_and_keeps_numeric_seconds(): void
    {
        $this->prepare();
        $this->admin();
        $booking = $this->booking(null);
        $booking->sessionType->update(['title' => '=2+3 <script>alert(1)</script>']);

        $this->get(route('admin.tutor-insights.index'))->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $response = $this->get(route('admin.tutor-insights.export', ['dataset' => 'appointments', 'format' => 'xlsx']))->assertOk();

        $file = $response->baseResponse->getFile()->getPathname();
        $workbook = IOFactory::load($file);
        $sheet = $workbook->getActiveSheet();
        $this->assertSame("'=2+3 <script>alert(1)</script>", $sheet->getCell('D2')->getValue());
        $this->assertSame('s', $sheet->getCell('D2')->getDataType());
        $this->assertSame('n', $sheet->getCell('I2')->getDataType());
        $this->assertSame(3600, $sheet->getCell('I2')->getValue());
        $workbook->disconnectWorksheets();
        ob_start();
        $response->baseResponse->sendContent();
        ob_end_clean();
        $this->assertFileDoesNotExist($file);
    }

    public function test_erased_feedback_and_future_events_are_excluded_without_changing_read_state(): void
    {
        $this->prepare();
        $student = Student::factory()->verified()->create();
        $conversation = FeedbackConversation::factory()->create(['student_id' => $student->id, 'created_at' => '2026-11-01 08:00:00']);
        FeedbackEvent::factory()->create(['conversation_id' => $conversation->id, 'actor_type' => 'administrator', 'created_at' => '2026-11-04 09:00:00']);
        $context = $this->context(['view' => 'operations', 'activity' => 'feedback']);

        $this->assertSame(0, $this->read()->metrics($context)['feedback_administrator_messages']['value']);
        $this->assertSame(1, $this->read()->metrics($context)['feedback_conversations_received']['value']);
        $conversation->update(['erased_at' => now()]);
        $this->assertSame(0, $this->read()->metrics($context)['feedback_conversations_received']['value']);
        $this->assertDatabaseCount('feedback_read_states', 0);
    }

    public function test_report_query_count_does_not_grow_per_student_or_appointment(): void
    {
        $this->prepare();
        $this->admin();
        $this->booking(Student::factory()->verified()->create());
        $this->get(route('admin.tutor-insights.index'))->assertOk();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get(route('admin.tutor-insights.index'))->assertOk();
        $oneStudent = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (Student::factory()->verified()->count(15)->create() as $student) {
            $this->booking($student);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get(route('admin.tutor-insights.index'))->assertOk();
        $sixteenStudents = count(DB::getQueryLog());

        DB::disableQueryLog();
        $this->assertSame($oneStudent, $sixteenStudents);
        $this->assertLessThan(40, $sixteenStudents);
    }

    public function test_timezone_selector_does_not_offer_legacy_aliases_rejected_by_period_validation(): void
    {
        $this->prepare();
        $this->admin();
        $this->booking(null);

        $response = $this->get(route('admin.tutor-insights.index', ['timezone' => 'UTC']))->assertOk();

        $this->assertSame(1, $response->viewData('metrics')['appointments_completed']['value']);
        $this->assertSame(1, $response->viewData('metrics')['teaching_days']['value']);
        $this->assertSame('2026-11-01', $response->viewData('appointments')->first()->local_date);
        $response->assertDontSee('<option value="GMT"', false)->assertSee('<option value="UTC"', false);
        $this->getJson(route('admin.tutor-insights.index', ['timezone' => 'GMT']))->assertUnprocessable()->assertJsonValidationErrors('timezone');
    }
}

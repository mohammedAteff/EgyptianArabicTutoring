<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\LessonNote;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsStructureService;
use App\Domains\Reporting\Services\EstimatedActivityDefinition;
use App\Domains\Reporting\Services\EstimatedActivityGate;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityCollectionTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    public function test_anchor_earns_zero_and_completed_windows_use_server_receipts_and_idempotent_sequences(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $anchor = $this->activityAnchor($block);
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
        $this->travel(15)->seconds();
        $body = ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000];
        $answer = $this->postJson(route('student.estimated-activity.checkpoint'), $body);
        $probe = DB::table('estimated_activity_streams')->first();
        $access = app(EstimatedActivityGate::class)->allowed(request(), 'student');
        $this->assertSame(['found' => true, 'session' => true, 'generation' => true, 'definition' => true, 'expiry' => true], [
            'found' => $probe !== null,
            'session' => $access && hash_equals($probe->session_hash, $access['binding']),
            'generation' => $access && $probe->generation === (int) $access['run']->generation,
            'definition' => $probe->definition === EstimatedActivityDefinition::VERSION,
            'expiry' => CarbonImmutable::parse($probe->expires_at, 'UTC')->gt(CarbonImmutable::now('UTC')),
        ]);
        $this->assertSame(200, $answer->status(), $answer->getContent());
        $answer->assertJsonPath('seconds', 15)->assertJsonPath('duplicate', false);
        $this->postJson(route('student.estimated-activity.checkpoint'), $body)->assertOk()->assertJsonPath('duplicate', true);
        $this->postJson(route('student.estimated-activity.checkpoint'), array_replace($body, ['window_ms' => 15001]))->assertConflict();
        $this->postJson(route('student.estimated-activity.checkpoint'), array_replace($body, ['sequence' => 3]))->assertConflict();
        $this->assertDatabaseCount('estimated_activity_intervals', 1);
        $this->assertDatabaseCount('estimated_activity_receipts', 1);
        $row = DB::table('estimated_activity_intervals')->first();
        $this->assertSame('2026-11-04 12:00:00.000000', $row->started_at);
        $this->assertSame('2026-11-04 12:00:15.000000', $row->ended_at);
        $this->assertSame(EstimatedActivityDefinition::VERSION, $row->definition);
        $this->assertDatabaseCount('lms_learning_visits', 0);
        $this->assertDatabaseCount('lms_lesson_progress', 0);
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_server_gap_caps_credit_and_stale_or_cross_session_permits_require_a_new_anchor(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $anchor = $this->activityAnchor($block);
        $this->travel(5)->seconds();
        $body = ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000];
        $this->postJson(route('student.estimated-activity.checkpoint'), $body)->assertOk()->assertJsonPath('seconds', 5);
        $this->travel(21)->seconds();
        $this->postJson(route('student.estimated-activity.checkpoint'), array_replace($body, ['sequence' => 2]))->assertConflict();
        $new = $this->activityAnchor($block);
        $this->travel(15)->seconds();
        $this->activitySession(['student_auth_expires_at' => now('UTC')->addMinutes(45)->toIso8601String()])
            ->postJson(route('student.estimated-activity.checkpoint'), ['permit' => $new['permit'], 'sequence' => 1, 'window_ms' => 15000])->assertConflict();
        $this->assertDatabaseCount('estimated_activity_intervals', 1);
    }

    public static function malformed(): array
    {
        return ['negative' => [-1], 'incomplete' => [14999], 'excess' => [16001], 'numeric text' => ['15000'], 'fractional' => [15000.5], 'null' => [null]];
    }

    #[DataProvider('malformed')]
    public function test_invalid_monotonic_windows_do_not_create_evidence(mixed $window): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $anchor = $this->activityAnchor($block);
        $this->travel(15)->seconds();
        $this->postJson(route('student.estimated-activity.checkpoint'), ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => $window])->assertUnprocessable();
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
        $this->assertDatabaseCount('estimated_activity_receipts', 0);
    }

    public function test_unknown_oversized_and_client_identity_fields_fail_with_minimal_errors(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $body = ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()];
        $this->postJson(route('student.estimated-activity.anchor'), $body + ['student_id' => $student->id])->assertUnprocessable()->assertDontSee('SQLSTATE');
        $this->postJson(route('student.estimated-activity.anchor'), $body + ['content' => str_repeat('private', 100)])->assertUnprocessable()->assertDontSee('private');
        $this->assertDatabaseCount('estimated_activity_streams', 0);
    }

    public function test_cross_student_access_and_revoked_enrollment_are_denied(): void
    {
        $student = Student::factory()->verified()->create();
        $other = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student, $other);
        $this->activityStudent($other)->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()])->assertNotFound();
        $this->activityStudent($student);
        $anchor = $this->activityAnchor($block);
        $grant = AccessGrant::where('student_id', $student->id)->firstOrFail();
        app(LmsAccessOperations::class)->change(Administrator::factory()->create(), $grant, 'revoke', [], $grant->lock_version, 'activity-revoke');
        $this->travel(15)->seconds();
        $this->postJson(route('student.estimated-activity.checkpoint'), ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000])->assertForbidden();
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
    }

    public function test_learning_locks_and_unsupported_media_prevent_new_anchors(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $block->lesson->forceFill(['learning_rules' => ['required' => true, 'methods' => ['manual'], 'video_threshold' => 95,
            'prerequisite_key' => null, 'drip_mode' => 'fixed', 'drip_days' => null, 'drip_at' => now('UTC')->addDay()->toIso8601String()]])->save();
        $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()])->assertNotFound();
        $block->lesson->forceFill(['learning_rules' => null])->save();
        $block->forceFill(['kind' => 'youtube_video'])->save();
        $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('estimated_activity_streams', 0);
        $this->assertDatabaseCount('estimated_activity_context_versions', 0);
    }

    public function test_an_owned_accessible_lesson_never_authorizes_another_students_note(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $note = LessonNote::factory()->create(['student_id' => Student::factory()->verified()->create()->id, 'lesson_id' => $block->lesson_id]);
        $this->activityRun($student);
        $this->activityStudent($student)->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_note', 'record' => $note->id, 'stream' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('estimated_activity_streams', 0);
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
    }

    public function test_stream_and_request_limits_bound_state_without_blocking_learning(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        for ($tab = 0; $tab < 6; $tab++) {
            $this->activityAnchor($block);
        }
        $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()])->assertTooManyRequests();
        $this->get(route('student.learning.lessons.show', [$block->lesson->course_id, $block->lesson_id]))->assertOk();
        $this->assertDatabaseCount('estimated_activity_streams', 6);
    }

    public function test_super_admin_context_is_server_classified_in_the_shared_workspace(): void
    {
        $admin = Administrator::factory()->create();
        $this->activityRun($admin);
        $this->activityAdmin($admin);
        $anchor = $this->postJson(route('admin.estimated-activity.anchor'), ['kind' => 'admin_course_create', 'record' => 0, 'stream' => (string) Str::uuid()])->assertOk()->json();
        $this->travel(15)->seconds();
        $this->postJson(route('admin.estimated-activity.checkpoint'), ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000])->assertOk();
        $this->assertDatabaseHas('estimated_activity_intervals', ['administrator_id' => $admin->id, 'student_id' => null, 'category' => 'learning_materials']);
        $this->assertDatabaseCount('tutor_work_entries', 0);
    }

    public function test_withdrawal_and_restoration_of_course_audience_never_revives_an_old_window(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $anchor = $this->activityAnchor($block);
        $actor = Administrator::factory()->create();
        $structure = app(LmsStructureService::class);
        $course = $block->lesson->course;
        $structure->setRule($actor, $course, ['audience' => 'selected_students', 'access_mode' => 'fixed', 'starts_at' => '2026-11-05T00:00:00Z', 'expires_at' => '2026-11-06T00:00:00Z']);
        $structure->setRule($actor, $course, ['audience' => 'selected_students', 'access_mode' => 'permanent']);
        $this->travel(15)->seconds();
        $this->postJson(route('student.estimated-activity.checkpoint'), ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000])->assertConflict();
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
    }

    public function test_a_fresh_anchor_records_only_a_bounded_client_interruption_reason(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $stream = (string) Str::uuid();
        $this->activityAnchor($block, $stream);
        $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => $stream, 'interruption' => 'hidden'])->assertOk()->assertJsonPath('seconds', 0);
        $this->assertDatabaseHas('estimated_activity_coverage', ['end_reason' => 'hidden']);
        $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => $stream, 'interruption' => 'arbitrary private text'])->assertUnprocessable();
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
    }
}

<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Lms\Models\AccessGrant;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsStructureService;
use App\Domains\Students\Models\Student;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class LmsAccessOperationsTest extends TestCase
{
    use RefreshDatabase;

    private Administrator $actor;

    private Student $student;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now('UTC')->setDate(2026, 10, 7)->setTime(12, 0));

    }

    private function arrange(): void
    {
        $this->actor = AdministratorFactory::new()->create(['role' => 'admin']);
        $this->student = Student::factory()->verified()->create();
        $this->course = Course::factory()->published()->create();
    }

    private function grant(array $terms = [], string $key = 'grant'): AccessGrant
    {
        return app(LmsAccessOperations::class)->grant($this->actor, $this->student, $this->course, $terms, $key);
    }

    private function allowed(): bool
    {
        return app(LmsAccessService::class)->canAccess($this->student, $this->course);
    }

    public function test_selected_student_rule_does_not_grant_access_without_explicit_enrollment(): void
    {
        $this->arrange();
        app(LmsStructureService::class)->setRule($this->actor, $this->course, ['audience' => 'selected_students']);
        $this->assertFalse($this->allowed());
        $grant = app(LmsAccessOperations::class)->enroll($this->actor, $this->student, $this->course, [], 'enrollment');
        $this->assertTrue($this->allowed());
        $this->assertFalse(app(LmsAccessService::class)->canAccess(Student::factory()->verified()->create(), $this->course));
        $this->assertDatabaseHas('lms_enrollments', ['student_id' => $this->student->id, 'course_id' => $this->course->id, 'status' => 'enrolled']);
        $this->assertSame('manual', $grant->source_kind);
    }

    public function test_replays_remain_idempotent_after_time_moves_and_do_not_repeat_audit(): void
    {
        $this->arrange();
        $first = $this->grant(['access_mode' => 'relative', 'relative_days' => 2]);
        $expiry = $first->expires_at->toIso8601String();
        $this->travel(1)->days();
        $second = $this->grant(['access_mode' => 'relative', 'relative_days' => 2]);
        $this->assertSame($first->id, $second->id);
        $this->assertSame($expiry, $second->expires_at->toIso8601String());
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_enrollments', 1);
        $this->assertDatabaseCount('lms_access_events', 1);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'lms_access_grant')->count());
    }

    public function test_reusing_operation_key_with_changed_original_terms_is_conflict(): void
    {
        $this->arrange();
        $this->grant();
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('different original terms');
        $this->grant(['starts_at' => '2026-10-08T12:00:00Z']);
    }

    public function test_duplicate_source_with_different_operation_key_has_one_access_fact(): void
    {
        $this->arrange();
        $one = $this->grant(['source_key' => 'owner.manual.reference'], 'one');
        $two = $this->grant(['source_key' => 'owner.manual.reference'], 'two');
        $this->assertSame($one->id, $two->id);
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertDatabaseCount('lms_enrollments', 1);
        $this->assertDatabaseCount('lms_access_events', 2);
        $this->assertSame(hash('sha256', 'owner.manual.reference'), $one->source_key);
    }

    public function test_future_start_and_fixed_expiration_are_start_inclusive_end_exclusive(): void
    {
        $this->arrange();
        $this->grant(['access_mode' => 'fixed', 'starts_at' => '2026-10-08T15:00:00+03:00', 'expires_at' => '2026-10-09T12:00:00Z']);
        $this->assertFalse($this->allowed());
        $this->travelTo(now('UTC')->setDate(2026, 10, 8)->setTime(12, 0));
        $this->assertTrue($this->allowed());
        $this->travelTo(now('UTC')->setDate(2026, 10, 9)->setTime(11, 59, 59));
        $this->assertTrue($this->allowed());
        $this->travel(1)->seconds();
        $this->assertFalse($this->allowed());
    }

    public function test_relative_expiration_is_elapsed_24_hour_days_across_dst(): void
    {
        $this->arrange();
        $grant = $this->grant(['access_mode' => 'relative', 'starts_at' => '2026-10-29T23:30:00+03:00', 'relative_days' => 2]);
        $this->assertSame('2026-10-31 20:30:00', $grant->expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(172800.0, $grant->starts_at->diffInSeconds($grant->expires_at));
        $this->travelTo($grant->expires_at);
        $this->assertFalse($this->allowed());
    }

    public function test_one_revoked_or_expired_source_does_not_deny_other_sources_or_duplicate_course(): void
    {
        $this->arrange();
        app(LmsStructureService::class)->setRule($this->actor, $this->course, ['audience' => 'all_students']);
        $grant = $this->grant(['access_mode' => 'relative', 'relative_days' => 1]);
        $this->travel(2)->days();
        $this->assertTrue($this->allowed());
        app(LmsAccessOperations::class)->change($this->actor, $grant, 'revoke', [], 1, 'revoke');
        $this->assertTrue($this->allowed());
        $this->assertCount(1, app(LmsAccessService::class)->activeForStudent($this->student));
        $this->assertCount(1, app(LmsAccessService::class)->studentsWithCourseAccess($this->course));
    }

    public function test_manual_sources_are_independent(): void
    {
        $this->arrange();
        $first = $this->grant([], 'first');
        $second = $this->grant([], 'second');
        app(LmsAccessOperations::class)->change($this->actor, $first, 'revoke', [], 1, 'revoke-first');
        $this->assertTrue($this->allowed());
        app(LmsAccessOperations::class)->change($this->actor, $second, 'revoke', [], 1, 'revoke-second');
        $this->assertFalse($this->allowed());
    }

    public function test_change_operations_validate_versions_and_explicit_regrant_retains_history(): void
    {
        $this->arrange();
        $operations = app(LmsAccessOperations::class);
        $grant = $this->grant();
        $grant = $operations->change($this->actor, $grant, 'set_expiration', ['expires_at' => '2026-10-08T12:00:00Z'], 1, 'expiry');
        $grant = $operations->change($this->actor, $grant, 'extend', ['expires_at' => '2026-10-09T12:00:00Z'], 2, 'extend');
        $grant = $operations->change($this->actor, $grant, 'remove_expiration', [], 3, 'permanent');
        $this->assertNull($grant->expires_at);
        $this->assertSame('permanent', $grant->access_mode);
        $grant = $operations->change($this->actor, $grant, 'set_start', ['starts_at' => '2026-10-08T12:00:00Z'], 4, 'future');
        $this->assertFalse($this->allowed());
        $grant = $operations->change($this->actor, $grant, 'revoke', [], 5, 'revoke');
        $replay = $operations->change($this->actor, $grant, 'revoke', [], 5, 'revoke');
        $this->assertSame(6, $replay->lock_version);
        $new = $operations->regrant($this->actor, $grant, [], 'regrant');
        $this->assertSame($grant->id, $new->regranted_from_id);
        $this->assertSame('revoked', $grant->fresh()->status);
        $this->assertTrue($this->allowed());
        $this->assertSame($new->id, $operations->regrant($this->actor, $grant, [], 'regrant')->id);
    }

    public function test_stale_extend_cannot_overwrite_revocation(): void
    {
        $this->arrange();
        $grant = $this->grant();
        $operations = app(LmsAccessOperations::class);
        $operations->change($this->actor, $grant, 'revoke', [], 1, 'revoke');
        try {
            $operations->change($this->actor, $grant, 'set_expiration', ['expires_at' => '2026-10-08T12:00:00Z'], 1, 'stale');
            $this->fail('Stale mutation must fail.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertFalse($this->allowed());
        $this->assertSame('revoked', $grant->fresh()->status);
    }

    public function test_invalid_timestamps_windows_and_metadata_fail_before_writing(): void
    {
        $this->arrange();
        foreach ([['access_mode' => 'fixed', 'starts_at' => '2026-10-08T12:00:00Z', 'expires_at' => '2026-10-07T12:00:00Z'],
            ['starts_at' => '2026-02-30T12:00:00Z'], ['starts_at' => '2026-10-08 12:00:00'],
            ['access_mode' => 'relative', 'relative_days' => 0], ['metadata' => ['email' => 'private@example.test']]] as $index => $terms) {
            try {
                $this->grant($terms, 'invalid-'.$index);
                $this->fail('Invalid terms must be rejected.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('lms_access_grants', 0);
            }
        }
    }

    public function test_lesson_scoped_grant_does_not_expose_sibling_lessons(): void
    {
        $this->arrange();
        $section = Section::factory()->published()->create(['course_id' => $this->course->id]);
        $one = Lesson::factory()->published()->create(['course_id' => $this->course->id, 'section_id' => $section->id]);
        $two = Lesson::factory()->published()->create(['course_id' => $this->course->id, 'section_id' => $section->id]);
        app(LmsAccessOperations::class)->grant($this->actor, $this->student, $one, [], 'lesson');
        $access = app(LmsAccessService::class);
        $this->assertTrue($access->canAccess($this->student, $this->course));
        $this->assertTrue($access->canAccess($this->student, $section));
        $this->assertTrue($access->canAccess($this->student, $one));
        $this->assertFalse($access->canAccess($this->student, $two));
    }

    public function test_database_rejects_cross_course_grant_and_duplicate_live_enrollment(): void
    {
        $this->arrange();
        $grant = $this->grant();
        $section = Section::factory()->create();
        try {
            DB::table('lms_access_grants')->where('id', $grant->id)->update(['section_id' => $section->id]);
            $this->fail('Cross-course grant must fail.');
        } catch (QueryException) {
            $this->assertNull($grant->fresh()->section_id);
        }
        $this->expectException(QueryException::class);
        DB::table('lms_enrollments')->insert(['student_id' => $this->student->id, 'course_id' => $this->course->id, 'enrolled_at' => now('UTC')]);
    }

    public function test_private_assignment_is_owned_windowed_and_does_not_return_tutoring_context(): void
    {
        $this->arrange();
        $course = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $this->student->id]);
        $booking = Booking::factory()->create(['student_id' => $this->student->id, 'notes' => 'Tutor only secret']);
        $assignment = app(LmsAccessOperations::class)->assign($this->actor, $this->student, $course, ['booking_id' => $booking->id, 'instructions' => 'Read the alphabet', 'access_mode' => 'relative', 'relative_days' => 1], 'assignment');
        $this->actingAs($this->student, 'student')->withSession(['student_id' => $this->student->id, 'student_auth_expires_at' => now('UTC')->addDays(3)->toIso8601String()]);
        $url = route('student.lms.assignments.show', $assignment);
        $this->get($url)->assertOk()->assertJsonPath('assignment.instructions', 'Read the alphabet')->assertDontSee('Tutor only secret')->assertDontSee('booking_id');
        $other = Student::factory()->verified()->create();
        $this->actingAs($other, 'student')->withSession(['student_id' => $other->id])->get($url)->assertNotFound();
        $this->assertFalse(app(LmsAccessService::class)->canAccess($other, $course));
        $this->travel(1)->days();
        $this->assertFalse(app(LmsAccessService::class)->canAccess($this->student, $assignment));
        $this->assertFalse($this->allowed());
    }

    public function test_foreign_booking_link_is_rejected_and_corrupt_link_fails_closed_on_read(): void
    {
        $this->arrange();
        $booking = Booking::factory()->create(['student_id' => Student::factory()->verified()->create()->id]);
        $operations = app(LmsAccessOperations::class);
        try {
            $operations->assign($this->actor, $this->student, $this->course, ['booking_id' => $booking->id], 'foreign');
            $this->fail('Foreign session link must fail.');
        } catch (ModelNotFoundException) {
            $this->assertDatabaseCount('lms_access_grants', 0);
        }
        $assignment = $operations->assign($this->actor, $this->student, $this->course, [], 'valid');
        DB::table('lms_learning_assignments')->where('id', $assignment->id)->update(['booking_id' => $booking->id]);
        $this->assertFalse(app(LmsAccessService::class)->canAccess($this->student, $assignment));
    }

    public function test_private_owner_is_enforced_even_if_a_foreign_grant_is_injected(): void
    {
        $this->arrange();
        $owner = Student::factory()->verified()->create();
        $private = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $owner->id]);
        $grant = $this->grant();
        DB::table('lms_access_grants')->where('id', $grant->id)->update(['course_id' => $private->id]);
        $this->assertFalse(app(LmsAccessService::class)->canAccess($this->student, $private));
    }

    public function test_events_and_audit_exclude_private_bodies_and_stay_separate_from_finance(): void
    {
        $this->arrange();
        app(LmsAccessOperations::class)->assign($this->actor, $this->student, $this->course, ['reason' => 'PRIVATE REASON', 'instructions' => 'PRIVATE INSTRUCTIONS', 'metadata' => ['reason_code' => 'support']], 'private-key');
        $events = DB::table('lms_access_events')->get()->toJson();
        $audit = DB::table('audit_logs')->get()->toJson();
        foreach ([$events, $audit] as $json) {
            $this->assertStringNotContainsString('PRIVATE REASON', $json);
            $this->assertStringNotContainsString('PRIVATE INSTRUCTIONS', $json);
        }
        foreach (['student_packages', 'payment_records', 'session_ledger_entries', 'student_notifications'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_http_access_operations_validate_nested_scope_and_roles(): void
    {
        $this->arrange();
        $this->actingAs($this->actor, 'web');
        $url = route('admin.lms.access.issue', [$this->student, $this->course]);
        $foreign = Lesson::factory()->create();
        $this->postJson($url, ['operation' => 'grant', 'operation_key' => 'foreign-scope', 'lesson_id' => $foreign->id])->assertNotFound();
        $response = $this->postJson($url, ['operation' => 'enroll', 'operation_key' => 'http'])->assertOk();
        $grant = AccessGrant::query()->findOrFail($response->json('id'));
        $this->patchJson(route('admin.lms.access.change', $grant), ['action' => 'revoke', 'expected_version' => 1, 'operation_key' => 'http-revoke'])->assertOk()->assertJsonPath('status', 'revoked');
    }
}

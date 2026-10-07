<?php

namespace Tests\Feature;

use App\Domains\Lms\Models\AccessEvent;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Enrollment;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsAccessService;
use App\Domains\Lms\Services\LmsStructureService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use App\Domains\System\Services\DevelopmentToolsService;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LmsLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_canonical_merge_preserves_independent_grants_private_owner_and_enrollment_history(): void
    {
        $actor = AdministratorFactory::new()->create();
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $private = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $secondary->id]);
        $operations = app(LmsAccessOperations::class);
        $one = $operations->grant($actor, $primary, $course, [], 'primary');
        $two = $operations->grant($actor, $secondary, $course, [], 'secondary');
        $assignment = $operations->assign($actor, $secondary, $private, ['instructions' => 'Keep owned learning'], 'private');
        $fields = ['source_key', 'starts_at', 'expires_at', 'status', 'created_at'];
        $facts = $two->fresh()->getRawOriginal();
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $actor->id);
        $after = $two->fresh()->getRawOriginal();
        foreach ($fields as $field) {
            $this->assertSame($facts[$field], $after[$field]);
        }
        $this->assertSame($primary->id, $two->fresh()->student_id);
        $this->assertSame($primary->id, $private->fresh()->owner_student_id);
        $this->assertTrue(app(LmsAccessService::class)->canAccess($primary, $assignment));
        $this->assertFalse(app(LmsAccessService::class)->canAccess($secondary, $private));
        $this->assertSame(1, Enrollment::query()->where('student_id', $primary->id)->where('course_id', $course->id)->where('status', 'enrolled')->count());
        $this->assertSame(1, Enrollment::query()->where('student_id', $primary->id)->where('status', 'superseded')->count());
        $this->assertSame(0, AccessEvent::query()->where('student_id', $secondary->id)->count());
        $this->assertDatabaseCount('lms_access_grants', 3);
    }

    public function test_relative_rule_does_not_restart_when_students_are_merged(): void
    {
        $this->travelTo(now('UTC')->setDate(2026, 10, 7)->setTime(12, 0));
        $actor = AdministratorFactory::new()->create();
        $primary = Student::factory()->verified()->create();
        $secondary = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create();
        $operations = app(LmsAccessOperations::class);
        $old = $operations->grant($actor, $secondary, $course, [], 'old');
        $operations->change($actor, $old, 'revoke', [], 1, 'old-revoke');
        $this->travel(3)->days();
        $new = $operations->grant($actor, $primary, $course, [], 'new');
        $operations->change($actor, $new, 'revoke', [], 1, 'new-revoke');
        app(LmsStructureService::class)->setRule($actor, $course, ['audience' => 'all_students', 'access_mode' => 'relative', 'relative_days' => 2]);
        $this->assertTrue(app(LmsAccessService::class)->canAccess($primary, $course));
        app(StudentMergeService::class)->merge($primary->id, $secondary->id, $actor->id);
        $this->assertFalse(app(LmsAccessService::class)->canAccess($primary, $course));
        $this->assertCount(0, app(LmsAccessService::class)->activeForStudent($primary));
    }

    public function test_canonical_privacy_erases_private_learning_bodies_and_retains_structural_history(): void
    {
        $actor = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->published()->create(['kind' => 'private', 'owner_student_id' => $student->id, 'title' => 'PRIVATE TITLE']);
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['section_id' => $section->id, 'course_id' => $course->id]);
        $block = app(LmsStructureService::class)->addBlock($actor, $lesson, ['kind' => 'rich_text', 'payload' => ['html' => '<p>PRIVATE BODY</p>']]);
        $assignment = app(LmsAccessOperations::class)->assign($actor, $student, $lesson, ['instructions' => 'PRIVATE INSTRUCTIONS', 'reason' => 'PRIVATE REASON', 'metadata' => ['reason_code' => 'support']], 'private');
        $grantId = $assignment->access_grant_id;
        $eventCount = AccessEvent::query()->count();
        app(StudentPrivacyService::class)->anonymize($student->id, $actor->id);
        $this->assertSame('archived', $course->fresh()->status);
        $this->assertSame('Redacted private learning', $course->fresh()->title);
        $this->assertNull($block->fresh()->payload);
        $this->assertSame('withdrawn', $block->fresh()->status);
        $this->assertNull($assignment->fresh()->instructions);
        $this->assertDatabaseHas('lms_access_grants', ['id' => $grantId, 'status' => 'revoked', 'reason' => null, 'metadata' => null]);
        $this->assertSame($eventCount, AccessEvent::query()->count());
        $this->assertFalse(app(LmsAccessService::class)->canAccess($student, $assignment));
        $this->assertDatabaseCount('student_packages', 0);
    }

    public function test_development_reset_fails_closed_when_lms_ownership_exists(): void
    {
        config(['development_tools.enabled' => true, 'development_tools.require_snapshot' => false]);
        $actor = AdministratorFactory::new()->create();
        $student = Student::factory()->verified()->create();
        $course = Course::factory()->create();
        app(LmsAccessOperations::class)->grant($actor, $student, $course, [], 'retain');
        try {
            app(DevelopmentToolsService::class)->preview($actor, 'lms-reset', 'reset', 'students', []);
            $this->fail('LMS dependencies need explicit reset review.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('unreviewed dependent', $exception->errors()['scope'][0]);
        }
        $this->assertModelExists($student);
        $this->assertDatabaseCount('lms_access_grants', 1);
        $this->assertSame(1, (int) DB::selectOne('SELECT @@FOREIGN_KEY_CHECKS AS enabled')->enabled);
    }
}

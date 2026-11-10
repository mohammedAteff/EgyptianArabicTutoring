<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Services\EstimatedActivityReport;
use App\Domains\Reporting\Services\EstimatedActivityRetention;
use App\Domains\Reporting\Services\EstimatedActivitySynthetic;
use App\Domains\Reporting\Services\TutorWorkService;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Services\StudentMergeService;
use App\Domains\Students\Services\StudentPrivacyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityLifecycleTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    public function test_merge_moves_retained_evidence_to_canonical_union_and_invalidates_both_grants(): void
    {
        $first = Student::factory()->verified()->create();
        $second = Student::factory()->verified()->create();
        $this->activityRun($first, $second);
        $this->activityInterval($first, '2026-11-04 10:00:00', '2026-11-04 10:10:00');
        $this->activityInterval($second, '2026-11-04 10:00:00', '2026-11-04 10:10:00');
        app(StudentMergeService::class)->merge($first->id, $second->id);
        $this->assertDatabaseCount('estimated_activity_intervals', 2);
        $this->assertSame(2, DB::table('estimated_activity_intervals')->where('student_id', $first->id)->count());
        $this->assertSame(0, DB::table('estimated_activity_grants')->whereNull('revoked_at')->count());
        $this->assertSame(600, app(EstimatedActivityReport::class)->student($this->activityPeriod($first))['seconds']);
    }

    public function test_erasure_removes_identifiable_evidence_and_permits_and_replay_cannot_recreate_it(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $anchor = $this->activityAnchor($block);
        $this->travel(15)->seconds();
        $body = ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000];
        $this->postJson(route('student.estimated-activity.checkpoint'), $body)->assertOk();
        app(StudentPrivacyService::class)->anonymize($student->id);
        foreach (['intervals', 'coverage', 'grants', 'streams', 'receipts'] as $table) {
            $this->assertDatabaseCount('estimated_activity_'.$table, 0);
        }
        $this->postJson(route('student.estimated-activity.checkpoint'), $body)->assertForbidden();
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
    }

    public function test_suspension_then_restoration_and_session_version_changes_do_not_revive_a_grant(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $this->activityAnchor($block);
        $student->update(['suspended_at' => now('UTC')]);
        $student->update(['suspended_at' => null]);
        $this->activityStudent($student)->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('estimated_activity_streams', 0);
    }

    public function test_collector_kill_switch_does_not_stop_or_modify_the_shared_explicit_timer(): void
    {
        $admin = Administrator::factory()->create();
        $run = $this->activityRun($admin);
        $state = app(TutorWorkService::class)->mutate($admin->id, 'start', null, ['operation_id' => (string) Str::uuid(), 'category' => 'feedback', 'timezone' => 'UTC', 'description' => 'Synthetic explicit timer']);
        $before = DB::table('tutor_work_entries')->find($state['result']['entry_id']);
        app(EstimatedActivitySynthetic::class)->setEnabled($run, false);
        app(EstimatedActivitySynthetic::class)->setEnabled($run, true);
        $after = DB::table('tutor_work_entries')->find($state['result']['entry_id']);
        $this->assertEquals($before, $after);
        $this->assertSame('running', $after->lifecycle);
        $this->assertDatabaseCount('tutor_work_segments', 1);
    }

    public function test_admin_role_revocation_preserves_retained_actor_evidence_but_invalidates_collection(): void
    {
        $admin = Administrator::factory()->create();
        $this->activityRun($admin);
        $this->activityInterval($admin, '2026-11-04 10:00:00', '2026-11-04 10:10:00', 'feedback');
        $this->activityAdmin($admin);
        $this->postJson(route('admin.estimated-activity.anchor'), ['kind' => 'admin_course_create', 'record' => 0, 'stream' => (string) Str::uuid()])->assertOk();
        $admin->update(['role' => 'admin']);
        $this->assertDatabaseCount('estimated_activity_streams', 0);
        $this->assertDatabaseHas('estimated_activity_intervals', ['administrator_id' => $admin->id]);
        $this->assertSame(1, DB::table('estimated_activity_grants')->whereNotNull('revoked_at')->count());
    }

    public function test_explicit_synthetic_retention_separates_permits_intervals_and_coverage_without_replay(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $run = $this->activityRun($student);
        $this->activityStudent($student);
        $anchor = $this->activityAnchor($block);
        $this->travel(15)->seconds();
        $body = ['permit' => $anchor['permit'], 'sequence' => 1, 'window_ms' => 15000];
        $this->postJson(route('student.estimated-activity.checkpoint'), $body)->assertOk();
        $retention = app(EstimatedActivityRetention::class);
        $this->assertSame(['permits' => 0, 'intervals' => 0, 'coverage' => 0], $retention->prune($run, config('estimated_activity.retention')));
        $this->travel(16)->minutes();
        $this->assertSame(['permits' => 1, 'intervals' => 0, 'coverage' => 0], $retention->prune($run, ['permits_seconds' => 0, 'intervals_seconds' => null, 'coverage_seconds' => null]));
        $this->postJson(route('student.estimated-activity.checkpoint'), $body)->assertConflict();
        $this->assertDatabaseCount('estimated_activity_receipts', 0);
        $this->assertSame(15, app(EstimatedActivityReport::class)->student($this->activityPeriod($student))['seconds']);
        $retention->prune($run, ['permits_seconds' => null, 'intervals_seconds' => 0, 'coverage_seconds' => 0]);
        $this->assertNull(app(EstimatedActivityReport::class)->student($this->activityPeriod($student))['seconds']);
        $this->assertDatabaseCount('estimated_activity_coverage', 0);
    }
}

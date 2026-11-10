<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Services\EstimatedActivityGate;
use App\Domains\Reporting\Services\EstimatedActivitySynthetic;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityGateTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    public function test_default_off_forged_flags_and_ungranted_accounts_create_no_collector_state(): void
    {
        $student = Student::factory()->verified()->create(['email' => 'ordinary@example.test']);
        $this->activityStudent($student)->withHeaders(['X-Synthetic' => 'true'])->postJson(route('student.estimated-activity.anchor'), [
            'kind' => 'student_lesson', 'record' => 1, 'stream' => (string) Str::uuid(), 'synthetic' => true,
        ])->assertForbidden();
        $this->assertSame('off', config('estimated_activity.mode'));
        $this->assertDatabaseCount('estimated_activity_grants', 0);
        $this->assertDatabaseCount('estimated_activity_streams', 0);
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
        $this->assertSame([], array_filter(array_keys(app('cache')->getStore()->all()), fn (string $key): bool => str_contains($key, 'estimated-activity')));
        $this->activityRun(Student::factory()->verified()->create());
        $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => 1, 'stream' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('estimated_activity_streams', 0);
    }

    public function test_runtime_seal_database_and_expiry_are_required_even_with_a_grant(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $run = $this->activityRun($student);
        $this->activityStudent($student);
        $this->activityAnchor($block);
        file_put_contents(storage_path('estimated-activity-runtime.json'), '{}');
        $this->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()])->assertForbidden();
        $this->assertDatabaseCount('estimated_activity_streams', 1);
        $this->assertTrue(app(EstimatedActivityGate::class)->isolated());
        config(['database.connections.mysql.database' => 'bolt_landing']);
        $this->assertSame('bolt_landing_test', DB::connection()->getDatabaseName());
        // The live connection, not a forged configuration value, determines the selected database.
        DB::table('estimated_activity_runs')->where('id', $run)->update(['expires_at' => '2026-11-04 11:59:00']);
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
    }

    public function test_generation_cycle_invalidates_permits_without_touching_explicit_timers(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $run = $this->activityRun($student);
        $this->activityStudent($student);
        $permit = $this->activityAnchor($block);
        $synthetic = app(EstimatedActivitySynthetic::class);
        $synthetic->setEnabled($run, false);
        $synthetic->setEnabled($run, true);
        $this->travel(15)->seconds();
        $this->postJson(route('student.estimated-activity.checkpoint'), ['permit' => $permit['permit'], 'sequence' => 1, 'window_ms' => 15000])->assertConflict();
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
        $this->assertDatabaseCount('tutor_work_entries', 0);
        $this->assertSame(3, DB::table('estimated_activity_runs')->where('id', $run)->value('generation'));
    }

    public function test_ordinary_admin_and_preview_or_invalid_student_sessions_are_denied(): void
    {
        $admin = Administrator::factory()->create(['role' => 'admin']);
        $student = Student::factory()->verified()->create();
        $this->activityRun($admin, $student);
        $this->activityAdmin($admin)->postJson(route('admin.estimated-activity.anchor'), ['kind' => 'admin_course_create', 'record' => 0, 'stream' => (string) Str::uuid()])->assertForbidden();
        $this->activityStudent($student)->activitySession(['lms_preview' => ['actor_id' => $admin->id]])
            ->postJson(route('student.estimated-activity.anchor'), ['kind' => 'student_lesson', 'record' => 1, 'stream' => (string) Str::uuid()])->assertForbidden();
        $this->activitySession(['lms_preview' => null, 'student_auth_expires_at' => CarbonImmutable::now('UTC')->subSecond()->toIso8601String()])
            ->postJson(route('student.estimated-activity.anchor'), [])->assertForbidden();
        $this->assertDatabaseCount('estimated_activity_streams', 0);
    }
}

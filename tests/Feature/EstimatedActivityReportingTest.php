<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Booking\Models\Booking;
use App\Domains\Reporting\Models\TutorWorkEntry;
use App\Domains\Reporting\Services\EstimatedActivityProjection;
use App\Domains\Reporting\Services\EstimatedActivityReport;
use App\Domains\Reporting\Services\StudentInsightsExport;
use App\Domains\Reporting\Services\StudentInsightsReadModel;
use App\Domains\Reporting\Services\TutorWorkContext;
use App\Domains\Reporting\Services\TutorWorkExport;
use App\Domains\Reporting\Services\TutorWorkReport;
use App\Domains\Reporting\Services\TutorWorkWorkspace;
use App\Domains\Students\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityReportingTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    private function workContext(Administrator $actor, string $source = 'all', string $category = 'all'): TutorWorkContext
    {
        return TutorWorkContext::fromRequest(request()->duplicate(['range' => 'today', 'timezone' => 'UTC', 'source' => $source, 'category' => $category]), $actor->id);
    }

    public function test_microsecond_boundaries_preserve_gaps_and_never_reduce_higher_priority_whole_seconds(): void
    {
        $admin = Administrator::factory()->create();
        $student = Student::factory()->verified()->create();
        $this->activityRun($admin, $student);
        $this->activityInterval($student, '2026-11-04 10:00:00.500000', '2026-11-04 10:00:15.500000');
        $this->activityInterval($student, '2026-11-04 10:00:16.000000', '2026-11-04 10:00:31.000000');
        $rows = iterator_to_array(app(EstimatedActivityProjection::class)->allocations($this->activityPeriod($student)));
        $this->assertSame('2026-11-04 10:00:00.500000', $rows[0]['started_at']);
        $this->assertSame('2026-11-04 10:00:15.500000', $rows[0]['ended_at']);
        $this->assertSame('2026-11-04 10:00:16', $rows[1]['started_at']);
        $this->assertSame(30, array_sum(array_column($rows, 'seconds')));
        Booking::factory()->create(['student_id' => null, 'status' => 'completed', 'start_at_utc' => '2026-11-04 10:00:00', 'end_at_utc' => '2026-11-04 11:00:00']);
        $this->activityInterval($admin, '2026-11-04 10:15:00.500000', '2026-11-04 10:45:00.500000', 'feedback');
        $summary = app(TutorWorkReport::class)->summary($this->workContext($admin));
        $this->assertSame(3600, $summary['union']);
        $this->assertSame(['teaching' => 3600], $summary['categories']);
        $this->assertSame(0, $summary['estimated_additional']);
        $this->assertSame(1800, $summary['estimated_shared']);
    }

    public function test_two_tabs_union_per_student_and_independent_students_sum_without_an_average(): void
    {
        $first = Student::factory()->verified()->create();
        $second = Student::factory()->verified()->create();
        $this->activityRun($first, $second);
        foreach ([$first, $first, $second] as $student) {
            $this->activityInterval($student, '2026-11-04 10:00:00', '2026-11-04 10:10:00');
        }
        $read = app(EstimatedActivityReport::class);
        $this->assertSame(600, $read->student($this->activityPeriod($first))['seconds']);
        $aggregate = $read->student($this->activityPeriod());
        $this->assertSame(1200, $aggregate['seconds']);
        $this->assertSame(2, $aggregate['contributors']);
        $this->assertArrayNotHasKey('average', $aggregate);
        $this->assertSame(['2026-11-04' => 1200], $aggregate['days']);
        $this->assertSame('partial', $aggregate['state']);
    }

    public function test_different_simultaneous_contexts_preserve_ambiguity_and_do_not_duplicate_time(): void
    {
        $student = Student::factory()->verified()->create();
        $this->activityRun($student);
        $this->activityInterval($student, '2026-11-04 10:00:00', '2026-11-04 10:10:00', record: 1);
        $this->activityInterval($student, '2026-11-04 10:05:00', '2026-11-04 10:15:00', record: 2);
        $result = app(EstimatedActivityReport::class)->student($this->activityPeriod($student));
        $this->assertSame(900, $result['seconds']);
        $this->assertSame(300, $result['overlap_seconds']);
        $rows = iterator_to_array(app(EstimatedActivityProjection::class)->allocations($this->activityPeriod($student)));
        $this->assertSame(['lms', 'unresolved_overlap', 'lms'], array_column($rows, 'category'));
    }

    public function test_shared_actor_union_and_existing_precedence_produce_both_required_numerical_examples(): void
    {
        $first = Administrator::factory()->create();
        $second = Administrator::factory()->create();
        $this->activityRun($first, $second);
        $this->activityInterval($first, '2026-11-04 10:15:00', '2026-11-04 10:45:00', 'lesson_preparation');
        $this->activityInterval($second, '2026-11-04 10:15:00', '2026-11-04 10:45:00', 'lesson_preparation');
        $timer = TutorWorkEntry::factory()->create(['created_by' => $first->id, 'origin' => 'timer', 'placement' => 'placed', 'work_date' => '2026-11-04', 'unplaced_seconds' => null]);
        DB::table('tutor_work_intervals')->insert(['entry_id' => $timer->id, 'workspace_id' => $timer->workspace_id, 'started_at' => '2026-11-04 10:00:00', 'ended_at' => '2026-11-04 11:00:00']);
        $report = app(TutorWorkReport::class);
        $summary = $report->summary($this->workContext($first));
        $this->assertSame(3600, $summary['union']);
        $this->assertSame(1800, $summary['estimated_shared']);
        $this->assertSame(1800, $summary['estimated_covered']);
        $this->assertSame(0, $summary['estimated_additional']);
        $this->assertSame(0, $report->summary($this->workContext($first, 'estimated_admin'))['allocated']);
        Booking::factory()->create(['student_id' => null, 'status' => 'completed', 'start_at_utc' => '2026-11-04 11:00:00', 'end_at_utc' => '2026-11-04 12:00:00']);
        $this->activityInterval($first, '2026-11-04 11:45:00', '2026-11-04 12:15:00', 'learning_materials');
        $this->travel(1)->hours();
        $summary = $report->summary($this->workContext($first));
        $this->assertSame(8100, $summary['union']); // Existing 60 + teaching 60 + additional 15.
        $this->assertSame(900, $summary['estimated_additional']);
        $this->assertSame(900, $report->summary($this->workContext($first, 'estimated_admin'))['allocated']);
        $this->assertSame($summary, $report->summary($this->workContext($second)));
        $export = iterator_to_array(app(TutorWorkExport::class)->rows($this->workContext($first), 'allocations'));
        $this->assertSame($summary['allocated'], array_sum(array_column($export, 11)));
        $this->assertStringContainsString('/actor:', implode(' ', array_column($export, 14)));
        $summaryExport = collect(app(TutorWorkExport::class)->rows($this->workContext($first), 'summary'))->keyBy(0);
        $this->assertSame('partial', $summaryExport['sources:estimated_admin'][18]);
    }

    public function test_two_actors_share_ten_minutes_and_disagreeing_categories_stay_unresolved(): void
    {
        $first = Administrator::factory()->create();
        $second = Administrator::factory()->create();
        $this->activityRun($first, $second);
        $this->activityInterval($first, '2026-11-04 10:00:00', '2026-11-04 10:10:00', 'feedback');
        $this->activityInterval($second, '2026-11-04 10:00:00', '2026-11-04 10:10:00', 'learning_materials');
        $summary = app(TutorWorkReport::class)->summary($this->workContext($first));
        $this->assertSame(600, $summary['union']);
        $this->assertSame(600, $summary['estimated_shared']);
        $this->assertSame(['unresolved_overlap' => 600], $summary['categories']);
    }

    public function test_open_timer_is_provisional_and_stopped_corrected_or_excluded_work_recomputes_estimates(): void
    {
        $admin = Administrator::factory()->create();
        $this->activityRun($admin);
        $this->activityInterval($admin, '2026-11-04 10:00:00', '2026-11-04 10:10:00', 'feedback');
        $timer = TutorWorkEntry::factory()->create(['created_by' => $admin->id, 'origin' => 'timer', 'placement' => 'placed', 'active_workspace' => app(TutorWorkWorkspace::class)->ready()->id, 'lifecycle' => 'running', 'acceptance' => 'provisional', 'unplaced_seconds' => null, 'work_date' => '2026-11-04']);
        DB::table('tutor_work_intervals')->insert(['entry_id' => $timer->id, 'workspace_id' => $timer->workspace_id, 'started_at' => '2026-11-04 10:00:00', 'ended_at' => '2026-11-04 10:10:00']);
        $read = app(TutorWorkReport::class);
        $this->assertSame(600, $read->summary($this->workContext($admin))['estimated_additional']);
        $timer->update(['active_workspace' => null, 'lifecycle' => 'stopped', 'acceptance' => 'accepted']);
        $this->assertSame(0, $read->summary($this->workContext($admin))['estimated_additional']);
        DB::table('tutor_work_intervals')->where('entry_id', $timer->id)->update(['ended_at' => '2026-11-04 10:05:00']);
        $this->assertSame(300, $read->summary($this->workContext($admin))['estimated_additional']);
        $timer->update(['acceptance' => 'excluded']);
        TutorWorkEntry::factory()->create(['created_by' => $admin->id, 'work_date' => '2026-11-04', 'unplaced_seconds' => 7200]);
        $summary = $read->summary($this->workContext($admin));
        $this->assertSame(600, $summary['estimated_additional']);
        $this->assertSame(600, $summary['union']);
        $this->assertSame(7200, $summary['unplaced']);
    }

    public function test_period_clipping_generation_cutoff_and_dst_midnights_keep_authoritative_seconds(): void
    {
        $student = Student::factory()->verified()->create();
        $this->activityRun($student);
        $this->activityInterval($student, '2026-10-24 22:00:00', '2026-10-25 23:00:00');
        $context = $this->activityPeriod($student, ['range' => 'custom', 'start_date' => '2026-10-25', 'end_date' => '2026-10-25', 'timezone' => 'Europe/Berlin']);
        $this->assertSame(90000, app(EstimatedActivityReport::class)->student($context)['seconds']);
        $this->activityInterval($student, '2026-11-04 11:59:00', '2026-11-04 12:01:00');
        $this->assertSame(60, app(EstimatedActivityReport::class)->student($this->activityPeriod($student))['seconds']);
    }

    public function test_disabled_or_unobserved_history_is_null_and_student_staff_and_exports_reconcile(): void
    {
        $student = Student::factory()->verified()->create();
        $read = app(EstimatedActivityReport::class);
        $this->assertNull($read->student($this->activityPeriod($student))['seconds']);
        $this->assertSame('disabled', $read->student($this->activityPeriod($student))['state']);
        $this->activityRun($student);
        $this->activityInterval($student, '2026-11-04 10:00:00', '2026-11-04 10:10:00');
        $context = $this->activityPeriod($student);
        $this->assertSame($read->student($context), app(StudentInsightsReadModel::class)->page($context, $student)['estimatedActivity']);
        $response = app(StudentInsightsExport::class)->download($context, $student, 'estimated_allocations', 'csv');
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();
        $this->assertStringContainsString('estimated_student', $csv);
        $this->assertStringContainsString('600', $csv);
        $this->assertStringNotContainsString('permit', $csv);
        $this->activityStudent($student)->get(route('student.insights.index', ['range' => 'today', 'timezone' => 'UTC']))->assertOk()->assertSee('Estimated Active Learning Time')->assertSee('0:10:00');
        $this->get('/student/insights/export')->assertNotFound();
    }
}

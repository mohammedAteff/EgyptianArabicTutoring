<?php

namespace Tests\Feature;

use App\Domains\Reporting\Services\EstimatedActivityDefinition;
use App\Domains\Reporting\Services\EstimatedActivityReport;
use App\Domains\Students\Models\Student;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityExperienceTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    #[TestWith(['en', 'Estimated Active Learning Time', 'Collection disabled.'])]
    #[TestWith(['fr', 'Temps d’apprentissage actif estimé', 'Collecte désactivée.'])]
    #[TestWith(['de', 'Geschätzte aktive Lernzeit', 'Erfassung deaktiviert.'])]
    public function test_student_measurement_and_availability_are_localized_without_initializing_collection(string $locale, string $title, string $disabled): void
    {
        $student = Student::factory()->verified()->create();
        $this->activityStudent($student)->get(route('student.insights.index', ['lang' => $locale]))->assertOk()
            ->assertSee($title)->assertSee($disabled)->assertDontSee('data-estimated-activity');
        $english = Arr::dot(require lang_path('en/activity.php'));
        $localized = Arr::dot(require lang_path($locale.'/activity.php'));
        $this->assertSame(array_keys($english), array_keys($localized));
        foreach ($localized as $value) {
            $this->assertNotSame('', trim($value));
        }
        $this->postJson(route('student.estimated-activity.anchor', ['lang' => $locale]), [])->assertForbidden()
            ->assertJsonPath('error', $localized['request_unavailable']);
        $this->assertDatabaseCount('estimated_activity_streams', 0);
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
    }

    public function test_only_granted_eligible_pages_render_bootstrap_and_existing_primary_workflows_stay_available(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityStudent($student)->get(route('student.learning.lessons.show', [$block->lesson->course_id, $block->lesson_id]))
            ->assertOk()->assertDontSee('data-estimated-activity');
        $this->activityRun($student);
        $this->get(route('student.learning.lessons.show', [$block->lesson->course_id, $block->lesson_id]))->assertOk()
            ->assertSee('data-estimated-activity')->assertSee('activity-lesson-text-'.$block->id);
        $this->get(route('student.feedback.index'))->assertOk()->assertDontSee('data-estimated-activity');
        $this->assertDatabaseCount('estimated_activity_intervals', 0);
        $this->post(route('student.learning.notes.store', [$block->lesson->course_id, $block->lesson_id]), ['body' => 'Private fixture note'])->assertRedirect();
        $this->assertDatabaseHas('lms_lesson_notes', ['student_id' => $student->id, 'body' => 'Private fixture note']);
    }

    public function test_collection_endpoints_retain_normal_web_csrf_protection(): void
    {
        $student = Student::factory()->verified()->create();
        $this->activityStudent($student);
        app()->detectEnvironment(fn (): string => 'activity-acceptance');
        $this->postJson(route('student.estimated-activity.anchor'), [])->assertStatus(419);
        $this->assertDatabaseCount('estimated_activity_streams', 0);
    }

    public function test_synthetic_overhead_and_large_history_have_measured_query_and_write_evidence(): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $this->activityRun($student);
        $this->activityStudent($student);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $verb = strtolower(strtok(ltrim($query->sql), ' '));
            $queries[] = ['verb' => $verb, 'ms' => $query->time,
                'collector_write' => in_array($verb, ['insert', 'update', 'delete'], true) && str_contains($query->sql, 'estimated_activity_')];
        });
        $started = hrtime(true);
        $anchor = $this->activityAnchor($block);
        for ($sequence = 1; $sequence <= 4; $sequence++) {
            $this->travel(15)->seconds();
            $this->postJson(route('student.estimated-activity.checkpoint'), ['permit' => $anchor['permit'], 'sequence' => $sequence, 'window_ms' => 15000])->assertOk();
        }
        $collection = ['requests_for_one_anchor_and_four_windows' => 5, 'latency_ms_in_test_process' => round((hrtime(true) - $started) / 1000000, 2),
            'queries' => count($queries), 'collector_writes' => count(array_filter($queries, fn (array $query): bool => $query['collector_write'])),
            'anchor_bytes' => strlen(json_encode(['kind' => 'student_lesson', 'record' => $block->id, 'stream' => (string) Str::uuid()])),
            'checkpoint_bytes' => strlen(json_encode(['permit' => $anchor['permit'], 'sequence' => 4, 'window_ms' => 15000])),
            'payload_limit_bytes' => EstimatedActivityDefinition::MAX_PAYLOAD_BYTES];
        $this->assertDatabaseCount('estimated_activity_intervals', 4);
        $grant = DB::table('estimated_activity_grants')->where('principal_type', 'student')->first();
        $rows = [];
        $base = CarbonImmutable::parse('2026-11-01 00:00:00 UTC');
        for ($number = 0; $number < 12000; $number++) {
            $start = $base->addSeconds($number * 16);
            $rows[] = ['run_id' => $grant->run_id, 'grant_id' => $grant->id, 'stream_id' => (string) Str::uuid(), 'student_id' => $student->id,
                'definition' => EstimatedActivityDefinition::VERSION, 'generation' => 1, 'sequence' => 1, 'kind' => 'student_lesson', 'record_id' => $block->id, 'category' => 'lms',
                'started_at' => $start->toDateTimeString(), 'ended_at' => $start->addSeconds(15)->toDateTimeString()];
            if (count($rows) === 500) {
                DB::table('estimated_activity_intervals')->insert($rows);
                $rows = [];
            }
        }
        $context = $this->activityPeriod($student, ['range' => 'custom', 'start_date' => '2026-11-01', 'end_date' => '2026-11-04']);
        $queries = [];
        $started = hrtime(true);
        $result = app(EstimatedActivityReport::class)->student($context);
        $large = ['rows' => 12004, 'queries' => count($queries), 'latency_ms' => round((hrtime(true) - $started) / 1000000, 2), 'seconds' => $result['seconds'], 'peak_process_memory_bytes' => memory_get_peak_usage(true)];
        $this->assertSame(180060, $result['seconds']);
        $this->assertLessThan(40, $large['queries']);
        file_put_contents($this->acceptanceOutputPath('performance.json'), json_encode(['collection' => $collection, 'large_report' => $large], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}

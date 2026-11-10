<?php

namespace Tests\Feature;

use App\Domains\Administration\Models\Administrator;
use App\Domains\Reporting\Services\EstimatedActivityReport;
use App\Domains\Students\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\Support\EstimatedActivityFixtures;
use Tests\TestCase;

class EstimatedActivityPerformanceComparisonTest extends TestCase
{
    use EstimatedActivityFixtures, RefreshDatabase;

    #[TestWith(['student', false, false])]
    #[TestWith(['student', false, true])]
    #[TestWith(['student', true, false])]
    #[TestWith(['student', true, true])]
    #[TestWith(['admin', false, false])]
    #[TestWith(['admin', false, true])]
    #[TestWith(['admin', true, false])]
    #[TestWith(['admin', true, true])]
    public function test_equivalent_sixty_second_active_and_idle_profiles(string $domain, bool $enabled, bool $active): void
    {
        $student = Student::factory()->verified()->create();
        $block = $this->activityLesson($student);
        $principal = $domain === 'student' ? $student : Administrator::factory()->create();
        $this->activityRun($principal);
        config(['estimated_activity.mode' => $enabled ? 'synthetic' : 'off']);
        $domain === 'student' ? $this->activityStudent($student) : $this->activityAdmin($principal);
        $route = $domain === 'student' ? route('student.learning.lessons.show', [$block->lesson->course_id, $block->lesson_id]) : route('admin.lms.courses.edit', $block->lesson->course_id);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $verb = strtolower(strtok(ltrim($query->sql), ' '));
            $queries[] = ['ms' => $query->time, 'write' => in_array($verb, ['insert', 'update', 'delete'], true) && str_contains($query->sql, 'estimated_activity_')];
        });
        $samples = [];
        for ($sample = 0; $sample < 3; $sample++) {
            $queries = [];
            $started = hrtime(true);
            $this->get($route)->assertOk();
            $page = ['php_ms' => (hrtime(true) - $started) / 1e6, 'queries' => count($queries), 'collector_writes' => count(array_filter($queries, fn (array $query): bool => $query['write']))];
            $before = DB::table('estimated_activity_intervals')->count();
            $queries = [];
            $started = hrtime(true);
            $bytes = 0;
            $requests = 0;
            if ($enabled && $active) {
                $payload = ['kind' => $domain === 'student' ? 'student_lesson' : 'admin_course', 'record' => $domain === 'student' ? $block->id : $block->lesson->course_id, 'stream' => (string) Str::uuid()];
                $anchor = $this->postJson(route($domain.'.estimated-activity.anchor'), $payload)->assertOk()->assertJsonPath('seconds', 0)->json();
                $bytes += strlen(json_encode($payload));
                $requests++;
                for ($sequence = 1; $sequence <= 4; $sequence++) {
                    $this->travel(15)->seconds();
                    $payload = ['permit' => $anchor['permit'], 'sequence' => $sequence, 'window_ms' => 15000];
                    $this->postJson(route($domain.'.estimated-activity.checkpoint'), $payload)->assertOk()->assertJsonPath('seconds', 15);
                    $bytes += strlen(json_encode($payload));
                    $requests++;
                }
            } else {
                $this->travel(60)->seconds();
            }
            $collection = ['php_ms' => (hrtime(true) - $started) / 1e6, 'requests' => $requests, 'payload_bytes' => $bytes, 'queries' => count($queries), 'collector_writes' => count(array_filter($queries, fn (array $query): bool => $query['write']))];
            $this->assertSame($before + ($enabled && $active ? 4 : 0), DB::table('estimated_activity_intervals')->count());
            $queries = [];
            $started = hrtime(true);
            app(EstimatedActivityReport::class)->student($this->activityPeriod($student));
            $samples[] = ['sample' => $sample + 1, 'condition' => $sample === 0 ? 'first request in case; shared PHP process already warm' : 'warm', 'page' => $page,
                'collection' => $collection, 'report_php_ms' => (hrtime(true) - $started) / 1e6, 'report_queries' => count($queries), 'process_peak_bytes' => memory_get_peak_usage(true)];
        }

        file_put_contents($this->acceptanceOutputPath('performance-'.$domain.'-'.($enabled ? 'on' : 'off').'-'.($active ? 'active' : 'idle').'.json'), json_encode([
            'domain' => $domain, 'enabled' => $enabled, 'active' => $active, 'observation_seconds' => 60, 'clock' => 'controlled Laravel test time; not browser wall time',
            'browser_network_elapsed' => 'not measured by this PHP profile', 'hidden' => 'not verified by this profile', 'samples' => $samples,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }
}

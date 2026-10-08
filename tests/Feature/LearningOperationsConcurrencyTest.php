<?php

namespace Tests\Feature;

use App\Domains\Analytics\Models\AnalyticsEvent;
use App\Domains\Audit\Models\AuditLog;
use App\Domains\CMS\Models\Setting;
use App\Domains\Lms\Models\Course;
use App\Domains\Lms\Models\Lesson;
use App\Domains\Lms\Models\LessonProgress;
use App\Domains\Lms\Models\Section;
use App\Domains\Lms\Services\LmsAccessOperations;
use App\Domains\Lms\Services\LmsSettings;
use App\Domains\Students\Models\Student;
use App\Domains\Students\Models\StudentNotification;
use Database\Factories\AdministratorFactory;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class LearningOperationsConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected array $exceptTables = ['entitlement_types', 'meeting_providers', 'settings'];

    protected function beforeTruncatingDatabase(): void
    {
        if (DB::connection()->getDatabaseName() !== 'bolt_landing_test') {
            throw new \RuntimeException('Learning operations races require the dedicated test database.');
        }
        Setting::query()->where('key', LmsSettings::KEY)->delete();
    }

    protected function tearDown(): void
    {
        if (isset($this->app) && DB::connection()->getDatabaseName() === 'bolt_landing_test') {
            Setting::query()->where('key', LmsSettings::KEY)->delete();
        }
        parent::tearDown();
    }

    private function fixture(array $rules = [], array $terms = []): array
    {
        $student = Student::factory()->verified()->create();
        $actor = AdministratorFactory::new()->create();
        $course = Course::factory()->published()->create();
        $section = Section::factory()->published()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'section_id' => $section->id, 'learning_rules' => array_replace([
            'required' => true, 'methods' => ['manual'], 'video_threshold' => 95, 'prerequisite_key' => null, 'drip_mode' => 'immediate', 'drip_days' => null, 'drip_at' => null], $rules)]);
        app(LmsAccessOperations::class)->grant($actor, $student, $course, $terms + ['starts_at' => now('UTC')->toIso8601String()], 'operations-race-'.Str::uuid());

        return ['student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id];
    }

    public function test_concurrent_global_settings_have_one_winner_and_no_lost_version(): void
    {
        $first = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $second = AdministratorFactory::new()->create(['role' => 'super_admin']);
        $payload = ['action' => 'learning_settings_save', 'administrator_id' => $first->id, 'version' => 0, 'values' => array_replace(LmsSettings::DEFAULTS, ['video_threshold' => 90])];
        $other = array_replace($payload, ['administrator_id' => $second->id, 'values' => array_replace(LmsSettings::DEFAULTS, ['video_threshold' => 80])]);
        $this->assertCodes([0, 2], $this->race([$payload, $other]));
        $stored = json_decode(Setting::query()->where('key', LmsSettings::KEY)->sole()->value, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $stored['version']);
        $this->assertContains($stored['values']['video_threshold'], [80, 90]);
        $this->assertSame(1, AuditLog::query()->where('action', 'lms_settings_changed')->count());
        $this->assertSame(1, Setting::query()->where('key', LmsSettings::KEY)->count());
    }

    public function test_concurrent_canonical_completion_records_each_semantic_transition_once(): void
    {
        $payload = $this->fixture() + ['action' => 'learning_semantic_manual'];
        $this->assertCodes([0, 0], $this->race([$payload, $payload]));
        $this->assertDatabaseCount('lms_lesson_progress', 1);
        $this->assertNotNull(LessonProgress::query()->sole()->completed_at);
        foreach (['lms_course_started', 'lms_lesson_completed', 'lms_course_completed'] as $name) {
            $this->assertSame(1, AnalyticsEvent::query()->where('event_name', $name)->count(), $name);
        }
        $this->assertSame(3, AnalyticsEvent::query()->where('event_name', 'like', 'lms_%')->distinct()->count('event_uuid'));
        $this->assertSame(1, AuditLog::query()->where('action', 'lms_lesson_completed')->count());
    }

    public function test_simultaneous_visit_fact_synchronization_preserves_notification_deduplication(): void
    {
        $payload = $this->fixture(['drip_mode' => 'fixed', 'drip_at' => now('UTC')->subMinute()->toIso8601String()], ['access_mode' => 'fixed', 'expires_at' => now('UTC')->addDay()->toIso8601String()]) + ['action' => 'learning_notifications_sync'];
        $this->assertCodes([0, 0], $this->race([$payload, $payload]));
        $this->assertSame(2, StudentNotification::query()->where('student_id', $payload['student_id'])->count());
        $this->assertSame(2, StudentNotification::query()->where('student_id', $payload['student_id'])->distinct()->count('deduplication_key'));
        $this->assertDatabaseCount('lms_lesson_progress', 0);
        $this->assertDatabaseCount('analytics_events', 0);
    }

    private function assertCodes(array $expected, array $results): void
    {
        $codes = array_column($results, 'exit_code');
        sort($codes);
        sort($expected);
        $this->assertSame($expected, $codes, json_encode($results));
    }

    private function race(array $payloads): array
    {
        $gate = sys_get_temp_dir().DIRECTORY_SEPARATOR.'operations-race-'.Str::uuid();
        $processes = [];
        $ready = [];
        try {
            foreach ($payloads as $index => $payload) {
                $worker = 'worker-'.$index;
                $ready[] = $gate.'.ready.'.$worker;
                $payload['start_gate'] = $gate;
                $payload['worker_id'] = $worker;
                $process = new Process([PHP_BINARY, base_path('tests/Feature/Concurrency/booking_worker.php'), json_encode($payload, JSON_THROW_ON_ERROR)]);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            while (count(array_filter($ready, 'is_file')) !== count($ready) && microtime(true) < $deadline) {
                usleep(10000);
            }
            $this->assertCount(count($ready), array_filter($ready, 'is_file'), 'Workers must reach the simultaneous start gate.');
            file_put_contents($gate, 'start', LOCK_EX);
            foreach ($processes as $process) {
                $process->wait();
            }

            return array_map(fn (Process $process) => ['exit_code' => $process->getExitCode(), 'output' => trim($process->getOutput().$process->getErrorOutput())], $processes);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            foreach ([$gate, ...$ready] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
}
